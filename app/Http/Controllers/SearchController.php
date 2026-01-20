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
            'SELECT e.archive_document_id, e.chunk, e.chunk_index, d.name, d.original_filename, d.extension, d.preview_page_count, d.preview_status, d.processed_diary_data, (e.embedding <=> ?::vector) as distance
             FROM archive_document_embeddings e
             JOIN archive_documents d ON d.id = e.archive_document_id
             ORDER BY e.embedding <=> ?::vector
             LIMIT 12',
            [$vector, $vector]
        );

        if (count($rows) > 0) {
            $rawDistances = array_map(
                fn ($row) => (float) $row->distance,
                $rows
            );
            logger()->info('RAG raw distances.', [
                'query' => $query,
                'min' => min($rawDistances),
                'max' => max($rawDistances),
            ]);
        }

        $maxDistance = (float) env('RAG_MAX_DISTANCE_COSINE', (float) env('RAG_MAX_DISTANCE', 0.8));
        $rows = array_values(array_filter(
            $rows,
            fn ($row) => (float) $row->distance <= $maxDistance
        ));

        if (count($rows) === 0) {
            $tokens = preg_split('/[^\p{L}\p{N}]+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
            $tokens = array_values(array_filter(array_unique($tokens), fn (string $token) => mb_strlen($token) >= 3));
            if (count($tokens) > 0) {
                $likeClauses = implode(' OR ', array_fill(0, count($tokens), 'unaccent(e.chunk) ILIKE unaccent(?)'));
                $bindings = array_map(fn (string $token) => '%'.$token.'%', $tokens);
                $rows = DB::select(
                    'SELECT e.archive_document_id, e.chunk, e.chunk_index, d.name, d.original_filename, d.extension, d.preview_page_count, d.preview_status, d.processed_diary_data, NULL as distance
                     FROM archive_document_embeddings e
                     JOIN archive_documents d ON d.id = e.archive_document_id
                     WHERE '.$likeClauses.'
                     ORDER BY e.archive_document_id, e.chunk_index
                     LIMIT 24',
                    $bindings
                );
                logger()->info('RAG keyword fallback.', [
                    'query' => $query,
                    'tokens' => $tokens,
                    'row_count' => count($rows),
                ]);
            }
        }

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
        $sourceExcerptKeys = [];
        $maxContextDocs = (int) env('RAG_CONTEXT_CHUNKS', 16);
        $processedByDoc = [];
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
                $sourceExcerptKeys[$docId] = [];
            }

            $processed = $this->normalizeProcessedDiaryData($row->processed_diary_data ?? null);
            if (!isset($processedByDoc[$docId])) {
                $processedByDoc[$docId] = $processed;
            }

            if (!empty($processed)) {
                foreach ($this->formatDiaryDataExcerpts($processed) as $excerpt) {
                    if ($excerpt === '' || isset($sourceExcerptKeys[$docId][$excerpt])) {
                        continue;
                    }
                    $sources[$docId]['excerpts'][] = $excerpt;
                    $sourceExcerptKeys[$docId][$excerpt] = true;
                }
            }
        }

        foreach ($processedByDoc as $docId => $processed) {
            if (count($contextChunks) >= $maxContextDocs) {
                break;
            }
            if (empty($processed)) {
                continue;
            }
            $payload = json_encode($processed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payload === false || $payload === '') {
                continue;
            }
            $name = $sources[$docId]['name'] ?? (string) $docId;
            $contextChunks[] = "Dokument: {$name}\nSpracovane data: {$payload}";
        }

        $sources = array_values($sources);
        $context = implode("\n\n", $contextChunks);

        logger()->info('RAG context built.', [
            'query' => $query,
            'context_chars' => strlen($context),
            'context_chunks' => count($contextChunks),
            'context_chunks_payload' => $contextChunks,
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
                                'text' => 'Si speleologický asistent pre vyhľadávanie informácií v archívnych dokumentoch. Odpovedz vecne, ale detailne a výstižne iba na základe poskytnutého kontextu; zahrň všetky relevantné fakty z kontextu. Nepridávaj žiadne všeobecné informácie ani domnienky mimo archívnych dokumentov. Ak kontext nestačí, jasne uveď, že v poskytnutých dokumentoch sa odpoveď nenachádza. Nezačínaj odpoveď frázou "Na základe poskytnutého kontextu"; ak potrebuješ úvod, použi "Na základe informácií z archivovaných dokumentov".',
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
     * @return array<string, mixed>
     */
    private function normalizeProcessedDiaryData($data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if (is_string($data) && $data !== '') {
            $decoded = json_decode($data, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function formatDiaryDataExcerpts(array $data): array
    {
        $fields = [
            'report_number' => 'C. spravy',
            'action_date' => 'Datum',
            'locality_name' => 'Lokalita',
            'leader_name' => 'Veduci',
            'work_description' => 'Popis',
        ];
        $excerpts = [];

        foreach ($fields as $key => $label) {
            $value = $data[$key] ?? '';
            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map('strval', $value)));
            }
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $excerpts[] = $this->trimExcerpt("{$label}: {$value}");
        }

        return $excerpts;
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
