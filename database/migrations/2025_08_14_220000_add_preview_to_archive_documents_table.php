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
            $table->string('preview_status', 20)->nullable()->after('ocr_processed_at');
            $table->text('preview_error')->nullable()->after('preview_status');
            $table->unsignedInteger('preview_page_count')->nullable()->after('preview_error');
            $table->string('preview_extension', 20)->nullable()->after('preview_page_count');
            $table->timestamp('preview_generated_at')->nullable()->after('preview_extension');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archive_documents', function (Blueprint $table) {
            $table->dropColumn([
                'preview_status',
                'preview_error',
                'preview_page_count',
                'preview_extension',
                'preview_generated_at',
            ]);
        });
    }
};
