<?php

namespace App\Http\Controllers;

use App\Models\ArchiveDocument;
use App\Jobs\ProcessArchiveDocumentOcr;
use App\Jobs\ProcessArchiveDocumentPreview;
use Carbon\Carbon;
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

        if (in_array($archiveDocument->ocr_status, ['pending', 'queued', 'processing'], true)) {
            return to_route('archives.index');
        }

        $archiveDocument->update([
            'processing_status' => 'pending',
            'processing_step' => 'ocr',
            'ocr_status' => 'pending',
        ]);
        $archiveDocument->appendProcessingLog('ocr', 'info', 'OCR bol manualne spusteny.');

        ProcessArchiveDocumentOcr::dispatch($archiveDocument->id);

        return to_route('archives.index');
    }

    public function generateDiary(ArchiveDocument $archiveDocument): RedirectResponse
    {
        $parsed = $archiveDocument->processed_diary_data;
        if (!$parsed) {
            $parsed = $archiveDocument->processDiaryData();
        }

        if (!$parsed) {
            return to_route('archives.index');
        }

        $prefill = $this->buildDiaryPrefill($parsed);

        return to_route('diaries.create')->with('diary_prefill', $prefill);
    }

    public function processDiary(ArchiveDocument $archiveDocument): RedirectResponse
    {
        $archiveDocument->processDiaryData();

        return to_route('archives.index');
    }

    public function startProcessing(Request $request, ArchiveDocument $archiveDocument): RedirectResponse
    {
        $mode = (string) $request->input('mode', 'missing');
        if ($mode === 'full') {
            $archiveDocument->restartProcessingFull();
        } else {
            $archiveDocument->startProcessingMissing();
        }

        return to_route('archives.index');
    }

    public function startPreview(ArchiveDocument $archiveDocument): RedirectResponse
    {
        if (!$archiveDocument->storage_path || !Storage::exists($archiveDocument->storage_path)) {
            return to_route('archives.index');
        }

        if (in_array($archiveDocument->preview_status, ['pending', 'queued', 'processing'], true)) {
            return to_route('archives.index');
        }

        $archiveDocument->update([
            'processing_status' => 'pending',
            'processing_step' => 'generatePreview',
            'preview_status' => 'pending',
            'preview_error' => null,
        ]);
        $archiveDocument->appendProcessingLog('generatePreview', 'info', 'Nahled bol manualne spusteny.');

        ProcessArchiveDocumentPreview::dispatch($archiveDocument->id);

        return to_route('archives.index');
    }

    public function regeneratePreview(ArchiveDocument $archiveDocument): RedirectResponse
    {
        if (!$archiveDocument->storage_path || !Storage::exists($archiveDocument->storage_path)) {
            return to_route('archives.index');
        }

        Storage::deleteDirectory("previews/{$archiveDocument->id}");

        $archiveDocument->update([
            'processing_status' => 'pending',
            'processing_step' => 'generatePreview',
            'preview_status' => 'pending',
            'preview_error' => null,
            'preview_page_count' => null,
            'preview_extension' => null,
        ]);
        $archiveDocument->appendProcessingLog('generatePreview', 'info', 'Regeneracia nahladu bola manualne spustena.');

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

    private function buildDiaryPrefill(array $parsed): array
    {
        $leader = trim((string) ($parsed['leader_name'] ?? ''));
        $leaderNote = $leader !== '' ? "Vedúci akcie: {$leader}" : '';
        $sssParticipants = $this->normalizeParticipants($parsed['sss_participants'] ?? null);
        $otherParticipants = $this->normalizeParticipants($parsed['other_participants'] ?? null);
        $sssNote = trim(implode(', ', $sssParticipants));
        $otherNote = trim(implode(', ', $otherParticipants));
        $combinedNote = trim(implode("\n", array_filter([$leaderNote, $sssNote])));

        return [
            'report_number' => (string) ($parsed['report_number'] ?? ''),
            'locality_name' => (string) ($parsed['locality_name'] ?? ''),
            'locality_position' => (string) ($parsed['locality_position'] ?? ''),
            'karst_area' => (string) ($parsed['karst_area'] ?? ''),
            'orographic_unit' => (string) ($parsed['orographic_unit'] ?? ''),
            'action_date' => $this->parseDateToIso((string) ($parsed['action_date'] ?? '')),
            'work_time' => (string) ($parsed['work_time'] ?? ''),
            'weather' => (string) ($parsed['weather'] ?? ''),
            'work_description' => (string) ($parsed['work_description'] ?? ''),
            'excavated_length_m' => (string) ($parsed['excavated_length_m'] ?? ''),
            'discovered_length_m' => (string) ($parsed['discovered_length_m'] ?? ''),
            'surveyed_length_m' => (string) ($parsed['surveyed_length_m'] ?? ''),
            'surveyed_depth_m' => (string) ($parsed['surveyed_depth_m'] ?? ''),
            'sss_participants_note' => $combinedNote,
            'other_participants' => $otherNote,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeParticipants(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        if (is_string($value) && $value !== '') {
            return [trim($value)];
        }

        return [];
    }

    private function parseDateToIso(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        try {
            $parsed = Carbon::createFromFormat('d.m.Y', $date);
        } catch (\Throwable) {
            return '';
        }

        return $parsed->format('Y-m-d');
    }
}
