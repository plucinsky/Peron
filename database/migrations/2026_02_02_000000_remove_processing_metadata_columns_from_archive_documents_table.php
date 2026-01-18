<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('archive_documents', function (Blueprint $table) {
            $table->dropColumn([
                'ocr_error',
                'ocr_processed_at',
                'preview_generated_at',
                'analyze_text_error',
                'analyze_text_processed_at',
                'rag_error',
                'rag_processed_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archive_documents', function (Blueprint $table) {
            $table->text('ocr_error')->nullable()->after('ocr_status');
            $table->timestamp('ocr_processed_at')->nullable()->after('ocr_error');
            $table->timestamp('preview_generated_at')->nullable()->after('preview_extension');
            $table->text('analyze_text_error')->nullable()->after('analyze_text_status');
            $table->timestamp('analyze_text_processed_at')->nullable()->after('analyze_text_error');
            $table->text('rag_error')->nullable()->after('rag_status');
            $table->timestamp('rag_processed_at')->nullable()->after('rag_error');
        });
    }
};
