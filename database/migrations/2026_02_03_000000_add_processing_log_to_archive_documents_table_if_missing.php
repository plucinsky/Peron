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
        if (!Schema::hasColumn('archive_documents', 'processing_log')) {
            Schema::table('archive_documents', function (Blueprint $table) {
                $table->json('processing_log')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('archive_documents', 'processing_log')) {
            Schema::table('archive_documents', function (Blueprint $table) {
                $table->dropColumn('processing_log');
            });
        }
    }
};
