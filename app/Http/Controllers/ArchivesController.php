<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Models\ArchiveDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArchivesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Archives', [
            'archives' => Archive::query()
                ->select('id', 'name', 'parent_id')
                ->orderBy('name')
                ->get(),
            'documents' => ArchiveDocument::query()
                ->select(
                    'id',
                    'archive_id',
                    'name',
                    'type',
                    'extension',
                    'size',
                    'storage_path',
                    'original_filename',
                    'created_at',
                    'processing_status',
                    'processing_step',
                    'processing_log',
                    'ocr_status',
                    'analyze_text_status',
                    'rag_status',
                    'preview_status',
                    'preview_page_count',
                    'preview_extension',
                    'ocr_text',
                    'processed_diary_data'
                )
                ->orderBy('name')
                ->get()
                ->each
                ->append([
                    'processing_status_label',
                    'processing_step_label',
                    'preview_status_label',
                    'ocr_status_label',
                    'analyze_text_status_label',
                    'rag_status_label',
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'min:1'],
        ]);

        Archive::create($data);

        return to_route('archives.index');
    }

    public function update(Request $request, Archive $archive): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (($data['parent_id'] ?? null) === $archive->id) {
            return to_route('archives.index');
        }

        $archive->update($data);

        return to_route('archives.index');
    }
}
