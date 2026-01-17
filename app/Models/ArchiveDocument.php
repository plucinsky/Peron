<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mattiverse\Userstamps\Traits\Userstamps;

class ArchiveDocument extends Model
{
    /** @use HasFactory<\Database\Factories\ArchiveDocumentFactory> */
    use HasFactory, SoftDeletes, Userstamps;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'archive_id',
        'diary_id',
        'relation_type',
        'caption',
        'seq',
        'name',
        'type',
        'mime_type',
        'extension',
        'size',
        'storage_path',
        'original_filename',
        'checksum',
        'meta',
        'ocr_text',
        'ocr_status',
        'ocr_error',
        'ocr_processed_at',
        'preview_status',
        'preview_error',
        'preview_page_count',
        'preview_extension',
        'preview_generated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'ocr_processed_at' => 'datetime',
            'preview_generated_at' => 'datetime',
        ];
    }
}
