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
        DB::statement('ALTER TABLE archive_documents ALTER COLUMN archive_id DROP NOT NULL');

        Schema::table('archive_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('archive_documents', 'diary_id')) {
                $table->unsignedBigInteger('diary_id')->nullable()->index();
            }
            if (!Schema::hasColumn('archive_documents', 'relation_type')) {
                $table->string('relation_type', 50)->nullable();
            }
            if (!Schema::hasColumn('archive_documents', 'caption')) {
                $table->text('caption')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archive_documents', function (Blueprint $table) {
            if (Schema::hasColumn('archive_documents', 'diary_id')) {
                $table->dropColumn('diary_id');
            }
            if (Schema::hasColumn('archive_documents', 'relation_type')) {
                $table->dropColumn('relation_type');
            }
            if (Schema::hasColumn('archive_documents', 'caption')) {
                $table->dropColumn('caption');
            }
        });

        DB::statement('ALTER TABLE archive_documents ALTER COLUMN archive_id SET NOT NULL');
    }
};
