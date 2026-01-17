<?php

namespace App\Http\Controllers;

use App\Models\ArchiveDocument;
use App\Models\Diary;
use App\Models\Person;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;

class DiariesController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'report_number' => $request->string('report_number')->trim()->toString(),
            'locality_name' => $request->string('locality_name')->trim()->toString(),
            'leader_person_id' => $request->string('leader_person_id')->trim()->toString(),
            'date_from' => $request->string('date_from')->trim()->toString(),
            'date_to' => $request->string('date_to')->trim()->toString(),
        ];

        $query = Diary::query()
            ->select(
                'id',
                'report_number',
                'locality_name',
                'action_date',
                'leader_person_id',
                'work_time'
            )
            ->orderByDesc('action_date')
            ->orderByDesc('id');

        if ($filters['report_number'] !== '') {
            $query->where('report_number', 'ILIKE', "%{$filters['report_number']}%");
        }

        if ($filters['locality_name'] !== '') {
            $query->where('locality_name', 'ILIKE', "%{$filters['locality_name']}%");
        }

        if ($filters['leader_person_id'] !== '') {
            $query->where('leader_person_id', (int) $filters['leader_person_id']);
        }

        if ($filters['date_from'] !== '') {
            $query->whereDate('action_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('action_date', '<=', $filters['date_to']);
        }

        return Inertia::render('Diaries/Index', [
            'diaries' => $query->get(),
            'persons' => Person::query()
                ->select('id', 'first_name', 'last_name')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Diaries/Form', [
            'diary' => null,
            'attachments' => [],
            'persons' => Person::query()
                ->select('id', 'first_name', 'last_name')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        Diary::create($data);

        return to_route('diaries.index');
    }

    public function edit(Diary $diary): Response
    {
        return Inertia::render('Diaries/Form', [
            'diary' => $diary->only([
                'id',
                'report_number',
                'locality_name',
                'locality_position',
                'karst_area',
                'orographic_unit',
                'action_date',
                'work_time',
                'weather',
                'leader_person_id',
                'member_person_ids',
                'other_person_ids',
                'sss_participants_note',
                'other_participants',
                'work_description',
                'excavated_length_m',
                'discovered_length_m',
                'surveyed_length_m',
                'surveyed_depth_m',
                'leader_signed_person_id',
                'leader_signed_at',
                'club_signed_person_id',
                'club_signed_at',
            ]),
            'attachments' => ArchiveDocument::query()
                ->select('id', 'name', 'original_filename', 'caption', 'created_at')
                ->where('diary_id', $diary->id)
                ->where('relation_type', 'attachment')
                ->orderByRaw('seq is null, seq asc')
                ->orderByDesc('id')
                ->get()
                ->map(fn (ArchiveDocument $document) => [
                    'id' => $document->id,
                    'name' => $document->name,
                    'original_filename' => $document->original_filename,
                    'caption' => $document->caption,
                    'seq' => $document->seq,
                    'download_url' => "/archive-documents/{$document->id}/download",
                    'created_at' => $document->created_at?->toDateTimeString(),
                ]),
            'persons' => Person::query()
                ->select('id', 'first_name', 'last_name')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function update(Request $request, Diary $diary): RedirectResponse
    {
        $data = $this->validatedData($request);

        $diary->update($data);

        return to_route('diaries.index');
    }

    public function downloadPdf(Diary $diary): HttpResponse
    {
        $persons = Person::query()
            ->select('id', 'first_name', 'last_name')
            ->get()
            ->keyBy('id');

        $logoPath = public_path('assets/images/logo.png');
        $logoFilePath = is_file($logoPath) ? 'file://'.$logoPath : null;

        $attachments = ArchiveDocument::query()
            ->where('diary_id', $diary->id)
            ->where('relation_type', 'attachment')
            ->orderByRaw('seq is null, seq asc')
            ->orderBy('id')
            ->get()
            ->map(function (ArchiveDocument $document) {
                $path = $document->storage_path
                    ? Storage::path($document->storage_path)
                    : null;
                $filePath = $path && is_file($path) ? 'file://'.$path : null;

                return [
                    'caption' => $document->caption,
                    'seq' => $document->seq,
                    'file_path' => $filePath,
                ];
            });

        $viewData = [
            'diary' => $diary,
            'leader' => $persons->get($diary->leader_person_id),
            'members' => collect($diary->member_person_ids ?? [])
                ->map(fn ($id) => $persons->get($id))
                ->filter(),
            'other_members' => collect($diary->other_person_ids ?? [])
                ->map(fn ($id) => $persons->get($id))
                ->filter(),
            'attachments' => $attachments,
            'attachmentsCount' => $attachments->count(),
            'logoFilePath' => $logoFilePath,
            'formatDate' => fn ($date) => $date
                ? Carbon::parse($date)->format('d.m.Y')
                : '',
        ];

        $mpdfTempDir = storage_path('app/mpdf');
        if (! is_dir($mpdfTempDir)) {
            mkdir($mpdfTempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'format' => 'A4',
            'margin_top' => 10,
            'margin_right' => 10,
            'margin_bottom' => 10,
            'margin_left' => 10,
            'tempDir' => $mpdfTempDir,
            'allow_local_files' => true,
        ]);

        $mpdf->WriteHTML(view('diaries.pdf-styles')->render(), HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML(view('diaries.pdf-main', $viewData)->render(), HTMLParserMode::HTML_BODY);

        foreach ($attachments as $index => $attachment) {
            $mpdf->AddPage();
            $mpdf->WriteHTML(
                view('diaries.pdf-attachment', array_merge($viewData, [
                    'attachment' => $attachment,
                    'index' => $index,
                ]))->render(),
                HTMLParserMode::HTML_BODY
            );
        }

        $pdf = $mpdf->Output('', 'S');

        $filename = sprintf('dennik-%s.pdf', $diary->id);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    public function storeAttachments(Request $request, Diary $diary): RedirectResponse
    {
        $data = $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'image', 'max:51200'],
            'captions' => ['nullable', 'array'],
            'captions.*' => ['nullable', 'string', 'max:255'],
            'seqs' => ['nullable', 'array'],
            'seqs.*' => ['nullable', 'integer', 'min:0'],
            'relation_type' => ['nullable', 'string', 'max:50'],
            'archive_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $files = $request->file('files', []);
        $captions = $data['captions'] ?? [];
        $relationType = $data['relation_type'] ?? 'attachment';
        $seqs = $data['seqs'] ?? [];

        foreach ($files as $index => $file) {
            if (!$file) {
                continue;
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $mimeType = $file->getClientMimeType() ?? 'application/octet-stream';
            $originalName = $file->getClientOriginalName();
            $size = $file->getSize();
            $name = pathinfo($originalName, PATHINFO_FILENAME) ?: $originalName;
            $checksum = hash_file('sha256', $file->getRealPath());

            $document = ArchiveDocument::create([
                'archive_id' => $data['archive_id'] ?? null,
                'diary_id' => $diary->id,
                'relation_type' => $relationType,
                'caption' => $captions[$index] ?? null,
                'seq' => isset($seqs[$index]) ? (int) $seqs[$index] : null,
                'name' => $name,
                'type' => 'image',
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
        }

        return back();
    }

    public function updateAttachment(
        Request $request,
        Diary $diary,
        ArchiveDocument $archiveDocument
    ): RedirectResponse {
        if ($archiveDocument->diary_id !== $diary->id) {
            abort(404);
        }

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
            'seq' => ['nullable', 'integer', 'min:0'],
        ]);

        $archiveDocument->update([
            'caption' => $data['caption'] ?? null,
            'seq' => $data['seq'] ?? null,
        ]);

        return back();
    }

    public function destroyAttachment(Diary $diary, ArchiveDocument $archiveDocument): RedirectResponse
    {
        if ($archiveDocument->diary_id !== $diary->id) {
            abort(404);
        }

        $archiveDocument->delete();

        return back();
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'report_number' => ['nullable', 'string', 'max:255'],
            'locality_name' => ['nullable', 'string', 'max:255'],
            'locality_position' => ['nullable', 'string', 'max:255'],
            'karst_area' => ['nullable', 'string', 'max:255'],
            'orographic_unit' => ['nullable', 'string', 'max:255'],
            'action_date' => ['nullable', 'date'],
            'work_time' => ['nullable', 'string', 'max:255'],
            'weather' => ['nullable', 'string'],
            'leader_person_id' => ['nullable', 'integer'],
            'member_person_ids' => ['nullable', 'array'],
            'member_person_ids.*' => ['integer'],
            'other_person_ids' => ['nullable', 'array'],
            'other_person_ids.*' => ['integer'],
            'sss_participants_note' => ['nullable', 'string'],
            'other_participants' => ['nullable', 'string'],
            'work_description' => ['nullable', 'string'],
            'excavated_length_m' => ['nullable', 'numeric', 'min:0'],
            'discovered_length_m' => ['nullable', 'numeric', 'min:0'],
            'surveyed_length_m' => ['nullable', 'numeric', 'min:0'],
            'surveyed_depth_m' => ['nullable', 'numeric', 'min:0'],
            'leader_signed_person_id' => ['nullable', 'integer'],
            'leader_signed_at' => ['nullable', 'date'],
            'club_signed_person_id' => ['nullable', 'integer'],
            'club_signed_at' => ['nullable', 'date'],
        ]);

        $memberIds = $data['member_person_ids'] ?? [];
        $data['member_person_ids'] = array_values(array_unique(array_filter($memberIds)));
        $otherIds = $data['other_person_ids'] ?? [];
        $data['other_person_ids'] = array_values(array_unique(array_filter($otherIds)));

        return $data;
    }
}
