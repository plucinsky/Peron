<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArchiveDocumentEmbedding extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'archive_document_id',
        'chunk_index',
        'chunk',
        'embedding',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function document()
    {
        return $this->belongsTo(ArchiveDocument::class, 'archive_document_id');
    }
}
