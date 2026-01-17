<?php

namespace App\Http\Controllers;

use App\Models\ArchiveDocument;
use App\Jobs\ProcessArchiveDocumentOcr;
use App\Jobs\ProcessArchiveDocumentPreview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchiveDocumentsController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'archive_id' => ['required', 'integer', 'min:1'],
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $mimeType = $file->getClientMimeType() ?? 'application/octet-stream';
        $originalName = $file->getClientOriginalName();
        $size = $file->getSize();
        $name = pathinfo($originalName, PATHINFO_FILENAME) ?: $originalName;
        $type = $this->guessType($extension, $mimeType);
        $checksum = hash_file('sha256', $file->getRealPath());

        $duplicate = ArchiveDocument::query()
            ->where('archive_id', $data['archive_id'])
            ->where('checksum', $checksum)
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'file' => 'Súbor už existuje v tomto adresári.',
            ]);
        }

        $document = ArchiveDocument::create([
            'archive_id' => $data['archive_id'],
            'name' => $name,
            'type' => $type,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size' => $size,
            'storage_path' => '',
            'original_filename' => $originalName,
            'checksum' => $checksum,
            'ocr_status' => 'pending',
        ]);

        $bucket = (int) floor($document->id / 50);
        $path = "documents/{$bucket}/{$document->id}.{$extension}";

        Storage::makeDirectory("documents/{$bucket}");
        @chmod(Storage::path("documents/{$bucket}"), 0777);
        Storage::putFileAs("documents/{$bucket}", $file, "{$document->id}.{$extension}");
        @chmod(Storage::path($path), 0777);

        $document->update(['storage_path' => $path]);

        return to_route('archives.index');
    }

    public function update(Request $request, ArchiveDocument $archiveDocument): RedirectResponse
    {
        $data = $request->validate([
            'archive_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'file' => ['nullable', 'file', 'max:51200'],
        ]);

        $archiveDocument->update([
            'archive_id' => $data['archive_id'],
            'name' => $data['name'],
            'type' => strtolower($data['type']),
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $mimeType = $file->getClientMimeType() ?? 'application/octet-stream';
            $originalName = $file->getClientOriginalName();
            $size = $file->getSize();

            $bucket = (int) floor($archiveDocument->id / 50);
            $path = "documents/{$bucket}/{$archiveDocument->id}.{$extension}";

            Storage::put($path, file_get_contents($file->getRealPath()));

            $archiveDocument->update([
                'mime_type' => $mimeType,
                'extension' => $extension,
                'size' => $size,
                'storage_path' => $path,
                'original_filename' => $originalName,
                'checksum' => hash_file('sha256', $file->getRealPath()),
            ]);
        }

        return to_route('archives.index');
    }

    public function download(ArchiveDocument $archiveDocument): StreamedResponse
    {
        if (!$archiveDocument->storage_path || !Storage::exists($archiveDocument->storage_path)) {
            abort(404);
        }

        $filename = $archiveDocument->original_filename
            ?: $archiveDocument->name.($archiveDocument->extension ? '.'.$archiveDocument->extension : '');

        return Storage::download($archiveDocument->storage_path, $filename);
    }

    public function startOcr(ArchiveDocument $archiveDocument): RedirectResponse
    {
        if (!$archiveDocument->storage_path || !Storage::exists($archiveDocument->storage_path)) {
            return to_route('archives.index');
        }

        $archiveDocument->update([
            'ocr_status' => 'queued',
            'ocr_error' => null,
        ]);

        ProcessArchiveDocumentOcr::dispatch($archiveDocument->id);

        return to_route('archives.index');
    }

    public function startPreview(ArchiveDocument $archiveDocument): RedirectResponse
    {
        if (!$archiveDocument->storage_path || !Storage::exists($archiveDocument->storage_path)) {
            return to_route('archives.index');
        }

        $archiveDocument->update([
            'preview_status' => 'queued',
            'preview_error' => null,
        ]);

        ProcessArchiveDocumentPreview::dispatch($archiveDocument->id);

        return to_route('archives.index');
    }

    public function previewPage(ArchiveDocument $archiveDocument, int $page): StreamedResponse
    {
        if ($page < 1) {
            abort(404);
        }

        $extension = $archiveDocument->preview_extension ?: 'png';
        $path = "previews/{$archiveDocument->id}/page-{$page}.{$extension}";

        if (!Storage::exists($path)) {
            abort(404);
        }

        return Storage::response($path);
    }

    private function guessType(string $extension, string $mimeType): string
    {
        $extension = strtolower($extension);

        if ($extension === 'pdf') {
            return 'pdf';
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg'], true)) {
            return 'image';
        }

        if (in_array($extension, ['doc', 'docx', 'rtf', 'odt'], true)) {
            return 'word';
        }

        if (in_array($extension, ['txt', 'md', 'csv', 'log'], true)) {
            return 'txt';
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        return 'other';
    }
}
