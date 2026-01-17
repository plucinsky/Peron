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
            if (!Schema::hasColumn('archive_documents', 'seq')) {
                $table->unsignedInteger('seq')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archive_documents', function (Blueprint $table) {
            if (Schema::hasColumn('archive_documents', 'seq')) {
                $table->dropColumn('seq');
            }
        });
    }
};
