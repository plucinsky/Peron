<?php

use App\Http\Controllers\ArchiveDocumentsController;
use App\Http\Controllers\ArchivesController;
use App\Http\Controllers\CavesController;
use App\Http\Controllers\DiariesController;
use App\Http\Controllers\PersonsController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('users', [UsersController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('users.index');
Route::post('users', [UsersController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('users.store');
Route::put('users/{user}', [UsersController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('users.update');
Route::put('users/{user}/password', [UsersController::class, 'updatePassword'])
    ->middleware(['auth', 'verified'])
    ->name('users.password');
Route::put('users/{user}/status', [UsersController::class, 'updateStatus'])
    ->middleware(['auth', 'verified'])
    ->name('users.status');

Route::get('persons', [PersonsController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('persons.index');
Route::post('persons', [PersonsController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('persons.store');
Route::put('persons/{person}', [PersonsController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('persons.update');

Route::get('caves', [CavesController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('caves.index');
Route::post('caves', [CavesController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('caves.store');
Route::put('caves/{cave}', [CavesController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('caves.update');

Route::get('denniky', [DiariesController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('diaries.index');
Route::get('denniky/create', [DiariesController::class, 'create'])
    ->middleware(['auth', 'verified'])
    ->name('diaries.create');
Route::post('denniky', [DiariesController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('diaries.store');
Route::get('denniky/{diary}/edit', [DiariesController::class, 'edit'])
    ->middleware(['auth', 'verified'])
    ->name('diaries.edit');
Route::put('denniky/{diary}', [DiariesController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('diaries.update');
Route::get('denniky/{diary}/pdf', [DiariesController::class, 'downloadPdf'])
    ->middleware(['auth', 'verified'])
    ->name('diaries.pdf');
Route::post('denniky/{diary}/attachments', [DiariesController::class, 'storeAttachments'])
    ->middleware(['auth', 'verified'])
    ->name('diaries.attachments.store');
Route::put('denniky/{diary}/attachments/{archiveDocument}', [DiariesController::class, 'updateAttachment'])
    ->middleware(['auth', 'verified'])
    ->name('diaries.attachments.update');
Route::delete('denniky/{diary}/attachments/{archiveDocument}', [DiariesController::class, 'destroyAttachment'])
    ->middleware(['auth', 'verified'])
    ->name('diaries.attachments.destroy');

Route::get('archives', [ArchivesController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('archives.index');
Route::post('archives', [ArchivesController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('archives.store');
Route::put('archives/{archive}', [ArchivesController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('archives.update');

Route::post('archive-documents', [ArchiveDocumentsController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('archive-documents.store');
Route::put('archive-documents/{archiveDocument}', [ArchiveDocumentsController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('archive-documents.update');
Route::get('archive-documents/{archiveDocument}/download', [ArchiveDocumentsController::class, 'download'])
    ->middleware(['auth', 'verified'])
    ->name('archive-documents.download');
Route::post('archive-documents/{archiveDocument}/ocr', [ArchiveDocumentsController::class, 'startOcr'])
    ->middleware(['auth', 'verified'])
    ->name('archive-documents.ocr');
Route::post('archive-documents/{archiveDocument}/generate-diary', [ArchiveDocumentsController::class, 'generateDiary'])
    ->middleware(['auth', 'verified'])
    ->name('archive-documents.generate-diary');
Route::post('archive-documents/{archiveDocument}/process-diary', [ArchiveDocumentsController::class, 'processDiary'])
    ->middleware(['auth', 'verified'])
    ->name('archive-documents.process-diary');
Route::post('archive-documents/{archiveDocument}/process', [ArchiveDocumentsController::class, 'startProcessing'])
    ->middleware(['auth', 'verified'])
    ->name('archive-documents.process');
Route::post('archive-documents/{archiveDocument}/preview', [ArchiveDocumentsController::class, 'startPreview'])
    ->middleware(['auth', 'verified'])
    ->name('archive-documents.preview');
Route::post('archive-documents/{archiveDocument}/preview/regenerate', [ArchiveDocumentsController::class, 'regeneratePreview'])
    ->middleware(['auth', 'verified'])
    ->name('archive-documents.preview.regenerate');
Route::get('archive-documents/{archiveDocument}/preview/{page}', [ArchiveDocumentsController::class, 'previewPage'])
    ->middleware(['auth', 'verified'])
    ->name('archive-documents.preview-page');

require __DIR__.'/settings.php';
