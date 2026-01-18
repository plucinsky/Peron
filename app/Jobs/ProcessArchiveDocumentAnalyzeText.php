<?php

namespace App\Jobs;

use App\Models\ArchiveDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessArchiveDocumentAnalyzeText implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 900;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    public function __construct(private readonly int $archiveDocumentId)
    {
    }

    public function handle(): void
    {
        $document = ArchiveDocument::find($this->archiveDocumentId);
        if (!$document) {
            return;
        }

        if (in_array($document->analyze_text_status, ['processing', 'done'], true)) {
            return;
        }

        $document->update([
            'processing_status' => 'queued',
            'processing_step' => 'analyzeText',
            'analyze_text_status' => 'queued',
        ]);
        $document->appendProcessingLog('analyzeText', 'info', 'Analyza textu bola prevzata do fronty.');

        $document->update([
            'processing_status' => 'processing',
            'processing_step' => 'analyzeText',
            'analyze_text_status' => 'processing',
        ]);
        $document->appendProcessingLog('analyzeText', 'info', 'Zacina analyza textu.');

        if (!$document->ocr_text) {
            $document->update([
                'processing_status' => 'failed',
                'analyze_text_status' => 'failed',
            ]);
            $document->appendProcessingLog('analyzeText', 'error', 'Chyba OCR text pre analyzu.');
            return;
        }

        $parsed = $document->processDiaryData();
        if (!$parsed) {
            $document->update([
                'processing_status' => 'failed',
                'analyze_text_status' => 'failed',
            ]);
            $document->appendProcessingLog('analyzeText', 'error', 'Analyza textu zlyhala.');
            return;
        }

        $document->update([
            'processing_status' => 'done',
            'analyze_text_status' => 'done',
        ]);
        $document->appendProcessingLog('analyzeText', 'info', 'Analyza textu bola uspesne dokoncena.');
        $document->processing();
    }

    public function failed(Throwable $exception): void
    {
        ArchiveDocument::whereKey($this->archiveDocumentId)->update([
            'processing_status' => 'failed',
            'processing_step' => 'analyzeText',
            'analyze_text_status' => 'failed',
        ]);
        $document = ArchiveDocument::find($this->archiveDocumentId);
        if ($document) {
            $document->appendProcessingLog('analyzeText', 'error', $exception->getMessage());
        }
    }
}
