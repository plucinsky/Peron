<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\ArchiveDocument;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('archives:process-unprocessed {--limit=200}', function () {
    $limit = (int) $this->option('limit');
    $processed = 0;

    ArchiveDocument::query()
        ->whereNull('processing_status')
        ->orderBy('id')
        ->limit($limit)
        ->get()
        ->each(function (ArchiveDocument $document) use (&$processed) {
            $document->processing();
            $processed += 1;
        });

    $this->info("Queued {$processed} document(s) for processing.");
})->purpose('Queue processing for unprocessed archive documents');

Artisan::command('archives:reset-stuck {--minutes=10} {--limit=200}', function () {
    $minutes = (int) $this->option('minutes');
    $limit = (int) $this->option('limit');
    $threshold = Carbon::now()->subMinutes($minutes);
    $reset = 0;

    ArchiveDocument::query()
        ->whereIn('processing_status', ['queued', 'processing'])
        ->whereNotNull('processing_at')
        ->where('processing_at', '<=', $threshold)
        ->orderBy('processing_at')
        ->limit($limit)
        ->get()
        ->each(function (ArchiveDocument $document) use (&$reset) {
            $updates = [
                'processing_status' => null,
                'processing_step' => null,
                'processing_at' => null,
            ];

            if ($document->processing_step === 'generatePreview') {
                $updates['preview_status'] = null;
                $updates['preview_error'] = null;
            } elseif ($document->processing_step === 'ocr') {
                $updates['ocr_status'] = null;
            } elseif ($document->processing_step === 'analyzeText') {
                $updates['analyze_text_status'] = null;
            } elseif ($document->processing_step === 'rag') {
                $updates['rag_status'] = null;
            }

            $document->update($updates);
            $document->appendProcessingLog('reset', 'warning', 'Spracovanie bolo resetovane kvoli dlhemu behu.');
            $reset += 1;
        });

    $this->info("Resetnutych {$reset} document(s).");
})->purpose('Reset stuck archive document processing');

Artisan::command('archives:restart-incomplete {--limit=200}', function () {
    $limit = (int) $this->option('limit');
    $processed = 0;

    ArchiveDocument::query()
        ->where(function ($query) {
            $query->whereNull('processing_status')
                ->orWhere('processing_status', '!=', 'complete');
        })
        ->orderBy('id')
        ->limit($limit)
        ->get()
        ->each(function (ArchiveDocument $document) use (&$processed) {
            $document->restartProcessingFull();
            $processed += 1;
        });

    $this->info("Restartnute {$processed} document(s) na plne spracovanie.");
})->purpose('Restart full processing for incomplete archive documents');
