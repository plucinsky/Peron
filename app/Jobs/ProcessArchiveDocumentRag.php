<?php

namespace App\Jobs;

use App\Models\ArchiveDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
        ]);
        $document->appendProcessingLog('rag', 'info', 'Zacina RAG spracovanie.');

        if (!$document->processed_diary_data) {
            $document->update([
                'processing_status' => 'failed',
                'rag_status' => 'failed',
            ]);
            $document->appendProcessingLog('rag', 'error', 'Chybaju data pre RAG spracovanie.');
            return;
        }

        $document->update([
            'processing_status' => 'done',
            'rag_status' => 'done',
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
        ]);
        $document = ArchiveDocument::find($this->archiveDocumentId);
        if ($document) {
            $document->appendProcessingLog('rag', 'error', $exception->getMessage());
        }
    }
}
