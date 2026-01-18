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
            'SELECT e.archive_document_id, e.chunk, e.chunk_index, d.name, d.original_filename, d.extension, (e.embedding <-> ?::vector) as distance
             FROM archive_document_embeddings e
             JOIN archive_documents d ON d.id = e.archive_document_id
             ORDER BY e.embedding <-> ?::vector
             LIMIT 12',
            [$vector, $vector]
        );

        $sources = [];
        $contextChunks = [];
        foreach ($rows as $row) {
            $docId = (int) $row->archive_document_id;
            if (!isset($sources[$docId])) {
                $sources[$docId] = [
                    'id' => $docId,
                    'name' => $row->name,
                    'original_filename' => $row->original_filename,
                    'extension' => $row->extension,
                    'distance' => (float) $row->distance,
                    'excerpts' => [],
                ];
            }

            $excerpt = $this->trimExcerpt((string) $row->chunk);
            if ($excerpt !== '') {
                $sources[$docId]['excerpts'][] = $excerpt;
            }

            if (count($contextChunks) < 8) {
                $contextChunks[] = "Dokument: {$row->name}\nText: {$excerpt}";
            }
        }

        $sources = array_values($sources);
        $context = implode("\n\n", $contextChunks);

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
                                'text' => 'Odpovedz stručne a vecne na základe poskytnutého kontextu. Ak kontext nestačí, povedz to.',
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
            return null;
        }

        return $response->json('output_text') ?: null;
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
}
