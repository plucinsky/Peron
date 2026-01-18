<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\ArchiveDocument;

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
