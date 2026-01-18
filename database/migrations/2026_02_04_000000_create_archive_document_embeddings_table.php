<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('archive_document_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_document_id')->constrained('archive_documents')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('chunk');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE archive_document_embeddings ADD COLUMN embedding vector(1536)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archive_document_embeddings');
    }
};
