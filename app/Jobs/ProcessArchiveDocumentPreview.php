<?php

namespace App\Jobs;

use App\Models\ArchiveDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessArchiveDocumentPreview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $archiveDocumentId)
    {
    }

    public function handle(): void
    {
        $document = ArchiveDocument::find($this->archiveDocumentId);
        if (!$document || !$document->storage_path || !Storage::exists($document->storage_path)) {
            Log::warning('Preview job skipped: missing document or file.', [
                'archive_document_id' => $this->archiveDocumentId,
            ]);
            return;
        }

        if (in_array($document->preview_status, ['processing', 'done'], true)) {
            Log::info('Preview job skipped: already processing.', [
                'archive_document_id' => $document->id,
                'preview_status' => $document->preview_status,
            ]);
            return;
        }

        $document->update([
            'processing_status' => 'queued',
            'processing_step' => 'generatePreview',
            'preview_status' => 'queued',
        ]);
        $document->appendProcessingLog('generatePreview', 'info', 'Nahled bol prevzaty do fronty.');

        Log::info('Preview job started.', [
            'archive_document_id' => $document->id,
            'extension' => $document->extension,
            'storage_path' => $document->storage_path,
        ]);

        $document->update([
            'processing_status' => 'processing',
            'processing_step' => 'generatePreview',
            'preview_status' => 'processing',
            'processing_at' => now(),
            'preview_error' => null,
        ]);
        $document->appendProcessingLog('generatePreview', 'info', 'Zacina spracovanie nahladu.');

        $tmpDir = storage_path('app/tmp/preview-'.$document->id.'-'.Str::uuid());
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $sourcePath = Storage::path($document->storage_path);
        $extension = strtolower($document->extension ?? '');
        $pageCount = 0;
        $previewExtension = null;

        try {
            if ($extension === 'pdf') {
                Log::info('Preview job: converting PDF to images.', [
                    'archive_document_id' => $document->id,
                ]);
                $pageCount = $this->convertPdfToImages($sourcePath, $tmpDir);
                $previewExtension = 'png';
            } elseif (in_array($extension, ['doc', 'docx'], true)) {
                Log::info('Preview job: converting Word to PDF.', [
                    'archive_document_id' => $document->id,
                ]);
                $pdfPath = $this->convertWordToPdf($sourcePath, $tmpDir);
                Log::info('Preview job: converting PDF to images.', [
                    'archive_document_id' => $document->id,
                ]);
                $pageCount = $this->convertPdfToImages($pdfPath, $tmpDir);
                $previewExtension = 'png';
            } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg'], true)) {
                Log::info('Preview job: copying image as preview.', [
                    'archive_document_id' => $document->id,
                ]);
                $pageCount = $this->copyImageAsPreview($sourcePath, $tmpDir, $extension);
                $previewExtension = $extension;
            } else {
                throw new \RuntimeException('Nepodporovaný formát pre náhľad.');
            }

            $storedCount = $this->storePreviewImages($tmpDir, $document->id);

            $document->update([
                'processing_status' => 'done',
                'preview_status' => 'done',
                'preview_page_count' => $storedCount,
                'preview_extension' => $previewExtension,
                'processing_at' => null,
            ]);
            $document->appendProcessingLog('generatePreview', 'info', 'Nahled bol uspesne dokoncen');
            $document->processing();

            Log::info('Preview job finished.', [
                'archive_document_id' => $document->id,
                'pages' => $storedCount,
                'preview_extension' => $previewExtension,
            ]);
        } catch (Throwable $exception) {
            $document->update([
                'processing_status' => 'failed',
                'preview_status' => 'failed',
                'preview_error' => $exception->getMessage(),
                'processing_at' => null,
            ]);
            $document->appendProcessingLog('generatePreview', 'error', $exception->getMessage());

            Log::error('Preview job failed.', [
                'archive_document_id' => $document->id,
                'error' => $exception->getMessage(),
            ]);
        } finally {
            $this->cleanupDir($tmpDir);
        }
    }

    public function failed(Throwable $exception): void
    {
        ArchiveDocument::whereKey($this->archiveDocumentId)->update([
            'processing_status' => 'failed',
            'processing_step' => 'generatePreview',
            'preview_status' => 'failed',
            'preview_error' => $exception->getMessage(),
            'processing_at' => null,
        ]);
        $document = ArchiveDocument::find($this->archiveDocumentId);
        if ($document) {
            $document->appendProcessingLog('generatePreview', 'error', $exception->getMessage());
        }
    }

    private function convertPdfToImages(string $pdfPath, string $tmpDir): int
    {
        $outputPrefix = $tmpDir.'/page';
        $process = new Process([
            'pdftoppm',
            '-png',
            '-scale-to',
            '2000',
            $pdfPath,
            $outputPrefix,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $files = glob($tmpDir.'/page-*.png') ?: [];
        return count($files);
    }

    private function convertWordToPdf(string $sourcePath, string $tmpDir): string
    {
        $process = new Process([
            'soffice',
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            $tmpDir,
            $sourcePath,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $pdfFiles = glob($tmpDir.'/*.pdf') ?: [];
        if (count($pdfFiles) === 0) {
            throw new \RuntimeException('Nepodarilo sa vytvoriť PDF z Word súboru.');
        }

        return $pdfFiles[0];
    }

    private function copyImageAsPreview(string $sourcePath, string $tmpDir, string $extension): int
    {
        $target = $tmpDir.'/page-1.'.$extension;
        copy($sourcePath, $target);
        return 1;
    }

    private function storePreviewImages(string $tmpDir, int $documentId): int
    {
        $files = glob($tmpDir.'/page-*.*') ?: [];
        sort($files);

        $count = 0;
        Storage::makeDirectory("previews/{$documentId}");
        @chmod(Storage::path("previews/{$documentId}"), 0777);
        foreach ($files as $filePath) {
            $basename = basename($filePath);
            Storage::putFileAs("previews/{$documentId}", new File($filePath), $basename);
            @chmod(Storage::path("previews/{$documentId}/{$basename}"), 0777);
            $count++;
        }

        return $count;
    }

    private function cleanupDir(string $tmpDir): void
    {
        if (!is_dir($tmpDir)) {
            return;
        }

        $files = glob($tmpDir.'/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($tmpDir);
    }
}
