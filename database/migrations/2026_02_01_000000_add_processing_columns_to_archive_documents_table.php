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
            $table->string('processing_status', 20)->nullable()->after('preview_generated_at');
            $table->string('processing_step', 50)->nullable()->after('processing_status');
            $table->string('analyze_text_status', 20)->nullable()->after('processing_step');
            $table->string('rag_status', 20)->nullable()->after('analyze_text_status');
            $table->json('processing_log')->nullable()->after('rag_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archive_documents', function (Blueprint $table) {
            $table->dropColumn([
                'processing_status',
                'processing_step',
                'analyze_text_status',
                'rag_status',
                'processing_log',
            ]);
        });
    }
};
