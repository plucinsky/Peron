<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archive_documents', function (Blueprint $table) {
            $table->json('processed_diary_data')->nullable()->after('ocr_text');
        });
    }

    public function down(): void
    {
        Schema::table('archive_documents', function (Blueprint $table) {
            $table->dropColumn('processed_diary_data');
        });
    }
};
