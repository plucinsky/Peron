<?php

namespace App\Jobs;

use App\Models\ArchiveDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
            return;
        }

        $document->update([
            'preview_status' => 'processing',
            'preview_error' => null,
        ]);

        $tmpDir = storage_path('app/tmp/preview-'.$document->id.'-'.Str::uuid());
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $sourcePath = Storage::path($document->storage_path);
        $extension = strtolower($document->extension ?? '');
        $pageCount = 0;
        $previewExtension = null;

        try {
            if ($extension === 'pdf') {
                $pageCount = $this->convertPdfToImages($sourcePath, $tmpDir);
                $previewExtension = 'png';
            } elseif (in_array($extension, ['doc', 'docx'], true)) {
                $pdfPath = $this->convertWordToPdf($sourcePath, $tmpDir);
                $pageCount = $this->convertPdfToImages($pdfPath, $tmpDir);
                $previewExtension = 'png';
            } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg'], true)) {
                $pageCount = $this->copyImageAsPreview($sourcePath, $tmpDir, $extension);
                $previewExtension = $extension;
            } else {
                throw new \RuntimeException('Nepodporovaný formát pre náhľad.');
            }

            $storedCount = $this->storePreviewImages($tmpDir, $document->id);

            $document->update([
                'preview_status' => 'done',
                'preview_page_count' => $storedCount,
                'preview_extension' => $previewExtension,
                'preview_generated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $document->update([
                'preview_status' => 'failed',
                'preview_error' => $exception->getMessage(),
            ]);
        } finally {
            $this->cleanupDir($tmpDir);
        }
    }

    public function failed(Throwable $exception): void
    {
        ArchiveDocument::whereKey($this->archiveDocumentId)->update([
            'preview_status' => 'failed',
            'preview_error' => $exception->getMessage(),
        ]);
    }

    private function convertPdfToImages(string $pdfPath, string $tmpDir): int
    {
        $outputPrefix = $tmpDir.'/page';
        $process = new Process(['pdftoppm', '-png', $pdfPath, $outputPrefix]);
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
