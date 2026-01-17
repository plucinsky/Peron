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
            $table->longText('ocr_text')->nullable()->after('meta');
            $table->string('ocr_status', 20)->nullable()->after('ocr_text');
            $table->text('ocr_error')->nullable()->after('ocr_status');
            $table->timestamp('ocr_processed_at')->nullable()->after('ocr_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archive_documents', function (Blueprint $table) {
            $table->dropColumn([
                'ocr_text',
                'ocr_status',
                'ocr_error',
                'ocr_processed_at',
            ]);
        });
    }
};
