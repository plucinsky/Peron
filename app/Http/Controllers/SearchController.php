<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Search', [
            'query' => '',
            'answer' => null,
            'sources' => [],
            'error' => null,
        ]);
    }

    public function search(Request $request): Response
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:1000'],
        ]);

        $query = trim($data['query']);
        if ($query === '') {
            return Inertia::render('Search', [
                'query' => '',
                'answer' => null,
                'sources' => [],
                'error' => 'Zadaj vyhľadávanie.',
            ]);
        }

        $embedding = $this->embedText($query);
        if (!$embedding) {
            return Inertia::render('Search', [
                'query' => $query,
                'answer' => null,
                'sources' => [],
                'error' => 'Nepodarilo sa získať embedding.',
            ]);
        }

        $vector = $this->vectorLiteral($embedding);
        $rows = DB::select(
            'SELECT e.archive_document_id, e.chunk, e.chunk_index, d.name, d.original_filename, d.extension, d.preview_page_count, d.preview_status, (e.embedding <-> ?::vector) as distance
             FROM archive_document_embeddings e
             JOIN archive_documents d ON d.id = e.archive_document_id
             ORDER BY e.embedding <-> ?::vector
             LIMIT 12',
            [$vector, $vector]
        );

        $maxDistance = (float) env('RAG_MAX_DISTANCE', 1.0);
        $rows = array_values(array_filter(
            $rows,
            fn ($row) => (float) $row->distance <= $maxDistance
        ));

        logger()->info('RAG search results.', [
            'query' => $query,
            'row_count' => count($rows),
        ]);
        if (count($rows) > 0) {
            $distances = array_map(
                fn ($row) => (float) $row->distance,
                $rows
            );
            logger()->info('RAG distances.', [
                'query' => $query,
                'min' => min($distances),
                'max' => max($distances),
                'top' => array_map(
                    fn ($row) => [
                        'doc' => $row->name,
                        'distance' => (float) $row->distance,
                        'excerpt' => $this->trimExcerpt((string) $row->chunk),
                    ],
                    array_slice($rows, 0, 3)
                ),
            ]);
        }

        $sources = [];
        $contextChunks = [];
        $expandedChunks = [];
        $expandedByDoc = [];

        foreach ($rows as $row) {
            $docId = (int) $row->archive_document_id;
            $index = (int) $row->chunk_index;
            if (!isset($expandedByDoc[$docId])) {
                $expandedByDoc[$docId] = [];
            }
            for ($i = $index - 2; $i <= $index + 2; $i++) {
                if ($i < 0) {
                    continue;
                }
                $expandedByDoc[$docId][$i] = true;
            }
        }

        foreach ($expandedByDoc as $docId => $indicesMap) {
            $indices = array_keys($indicesMap);
            if (count($indices) === 0) {
                continue;
            }

            $chunks = DB::table('archive_document_embeddings')
                ->select('archive_document_id', 'chunk', 'chunk_index')
                ->where('archive_document_id', $docId)
                ->whereIn('chunk_index', $indices)
                ->orderBy('chunk_index')
                ->get();

            foreach ($chunks as $chunk) {
                $key = $docId.':'.(int) $chunk->chunk_index;
                $expandedChunks[$key] = $chunk;
            }
        }
        foreach ($rows as $row) {
            $docId = (int) $row->archive_document_id;
            if (!isset($sources[$docId])) {
                $sources[$docId] = [
                    'id' => $docId,
                    'name' => $row->name,
                    'original_filename' => $row->original_filename,
                    'extension' => $row->extension,
                    'distance' => (float) $row->distance,
                    'preview_page_count' => $row->preview_page_count,
                    'preview_status' => $row->preview_status,
                    'excerpts' => [],
                ];
            }

            $excerpt = $this->trimExcerpt((string) $row->chunk);
            if ($excerpt !== '') {
                $sources[$docId]['excerpts'][] = $excerpt;
            }
        }

        foreach ($expandedChunks as $chunk) {
            $docId = (int) $chunk->archive_document_id;
            $excerpt = $this->trimExcerpt((string) $chunk->chunk);
            if ($excerpt !== '' && isset($sources[$docId])) {
                $sources[$docId]['excerpts'][] = $excerpt;
            }
            if (count($contextChunks) < 8) {
                $name = $sources[$docId]['name'] ?? (string) $docId;
                $contextChunks[] = "Dokument: {$name}\nText: {$excerpt}";
            }
        }

        $sources = array_values($sources);
        $context = implode("\n\n", $contextChunks);

        logger()->info('RAG context built.', [
            'query' => $query,
            'context_chars' => strlen($context),
            'context_chunks' => count($contextChunks),
        ]);

        $answer = $this->generateAnswer($query, $context);

        return Inertia::render('Search', [
            'query' => $query,
            'answer' => $answer,
            'sources' => $sources,
            'error' => $answer ? null : 'Nepodarilo sa získať odpoveď.',
        ]);
    }

    /**
     * @return array<int, float>|null
     */
    private function embedText(string $text): ?array
    {
        $response = Http::withToken((string) env('OPENAI_API_KEY'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
                'input' => $text,
            ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json('data.0.embedding');
        return is_array($data) ? $data : null;
    }

    private function generateAnswer(string $query, string $context): ?string
    {
        $response = Http::withToken((string) env('OPENAI_API_KEY'))
            ->timeout(60)
            ->post('https://api.openai.com/v1/responses', [
                'model' => env('OPENAI_MODEL', 'gpt-4o'),
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => 'Si speleologický asistent pre vyhľadávanie informácií v archívnych dokumentoch. Odpovedz vecne a podrobne iba na základe poskytnutého kontextu. Nepridávaj žiadne všeobecné informácie ani domnienky mimo archívnych dokumentov. Ak kontext nestačí, jasne uveď, že v poskytnutých dokumentoch sa odpoveď nenachádza. Nezačínaj odpoveď frázou "Na základe poskytnutého kontextu"; ak potrebuješ úvod, použi "Na základe informácií z archivovaných dokumentov".',
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => "Otázka: {$query}\n\nKontext:\n{$context}",
                            ],
                        ],
                    ],
                ],
            ]);

        if (!$response->successful()) {
            logger()->warning('OpenAI responses request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $text = $this->extractResponseText($response->json());
        return $text !== '' ? $text : null;
    }

    /**
     * @param array<int, float> $embedding
     */
    private function vectorLiteral(array $embedding): string
    {
        return '['.implode(',', $embedding).']';
    }

    private function trimExcerpt(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if (mb_strlen($text) <= 220) {
            return $text;
        }

        return mb_substr($text, 0, 220).'...';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractResponseText(array $payload): string
    {
        if (!empty($payload['output_text'])) {
            return (string) $payload['output_text'];
        }

        $output = $payload['output'] ?? [];
        $chunks = [];
        foreach ($output as $item) {
            $contents = $item['content'] ?? [];
            foreach ($contents as $content) {
                if (($content['type'] ?? '') === 'output_text') {
                    $chunks[] = $content['text'] ?? '';
                }
            }
        }

        return trim(implode("\n", array_filter($chunks)));
    }
}
