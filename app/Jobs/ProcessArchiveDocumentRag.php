<?php

namespace App\Jobs;

use App\Models\ArchiveDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class ProcessArchiveDocumentRag implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $archiveDocumentId)
    {
    }

    public function handle(): void
    {
        $document = ArchiveDocument::find($this->archiveDocumentId);
        if (!$document) {
            return;
        }

        if (in_array($document->rag_status, ['processing', 'done'], true)) {
            return;
        }

        $document->update([
            'processing_status' => 'queued',
            'processing_step' => 'rag',
            'rag_status' => 'queued',
        ]);
        $document->appendProcessingLog('rag', 'info', 'RAG spracovanie bolo prevzate do fronty.');

        $document->update([
            'processing_status' => 'processing',
            'processing_step' => 'rag',
            'rag_status' => 'processing',
            'processing_at' => now(),
        ]);
        $document->appendProcessingLog('rag', 'info', 'Zacina RAG spracovanie.');

        if (!$document->ocr_text) {
            $document->update([
                'processing_status' => 'failed',
                'rag_status' => 'failed',
                'processing_at' => null,
            ]);
            $document->appendProcessingLog('rag', 'error', 'Chyba OCR text pre RAG spracovanie.');
            return;
        }

        $document->embeddings()->delete();
        $chunks = $this->chunkText($document->ocr_text);
        if (count($chunks) === 0) {
            $document->update([
                'processing_status' => 'failed',
                'rag_status' => 'failed',
                'processing_at' => null,
            ]);
            $document->appendProcessingLog('rag', 'error', 'OCR text je prazdny.');
            return;
        }

        $model = env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small');
        $batchSize = 50;
        $chunkIndex = 0;

        foreach (array_chunk($chunks, $batchSize) as $batch) {
            $response = Http::withToken((string) env('OPENAI_API_KEY'))
                ->timeout(60)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $model,
                    'input' => $batch,
                ]);

            if (!$response->successful()) {
                $document->update([
                    'processing_status' => 'failed',
                    'rag_status' => 'failed',
                    'processing_at' => null,
                ]);
                $document->appendProcessingLog('rag', 'error', 'Embedding request zlyhal.');
                return;
            }

            $data = $response->json('data', []);
            if (!is_array($data)) {
                $document->update([
                    'processing_status' => 'failed',
                    'rag_status' => 'failed',
                    'processing_at' => null,
                ]);
                $document->appendProcessingLog('rag', 'error', 'Neplatna odpoved z embeddings.');
                return;
            }

            $byIndex = [];
            foreach ($data as $item) {
                if (!isset($item['index'], $item['embedding']) || !is_array($item['embedding'])) {
                    continue;
                }
                $byIndex[(int) $item['index']] = $item['embedding'];
            }

            $rows = [];
            foreach ($batch as $index => $chunk) {
                $embedding = $byIndex[$index] ?? null;
                if (!is_array($embedding)) {
                    continue;
                }

                $rows[] = [
                    'archive_document_id' => $document->id,
                    'chunk_index' => $chunkIndex,
                    'chunk' => $chunk,
                    'embedding' => $this->vectorLiteral($embedding),
                    'meta' => json_encode(['model' => $model]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $chunkIndex++;
            }

            if (count($rows) > 0) {
                DB::table('archive_document_embeddings')->insert($rows);
            }
        }

        $document->update([
            'processing_status' => 'done',
            'rag_status' => 'done',
            'processing_at' => null,
        ]);
        $document->appendProcessingLog('rag', 'info', 'RAG spracovanie bolo uspesne dokoncene.');
        $document->processing();
    }

    public function failed(Throwable $exception): void
    {
        ArchiveDocument::whereKey($this->archiveDocumentId)->update([
            'processing_status' => 'failed',
            'processing_step' => 'rag',
            'rag_status' => 'failed',
            'processing_at' => null,
        ]);
        $document = ArchiveDocument::find($this->archiveDocumentId);
        if ($document) {
            $document->appendProcessingLog('rag', 'error', $exception->getMessage());
        }
    }

    /**
     * @return array<int, string>
     */
    private function chunkText(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $maxLength = 1000;
        $overlap = 200;
        $chunks = [];
        $start = 0;
        $length = strlen($text);

        while ($start < $length) {
            $chunk = substr($text, $start, $maxLength);
            $chunks[] = trim($chunk);
            if ($start + $maxLength >= $length) {
                break;
            }
            $start += ($maxLength - $overlap);
        }

        return array_values(array_filter($chunks, fn (string $value) => $value !== ''));
    }

    /**
     * @param array<int, float> $embedding
     */
    private function vectorLiteral(array $embedding): string
    {
        return '['.implode(',', $embedding).']';
    }
}
