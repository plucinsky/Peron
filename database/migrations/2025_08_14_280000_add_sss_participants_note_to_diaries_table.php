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
        Schema::table('diaries', function (Blueprint $table) {
            if (!Schema::hasColumn('diaries', 'sss_participants_note')) {
                $table->text('sss_participants_note')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diaries', function (Blueprint $table) {
            if (Schema::hasColumn('diaries', 'sss_participants_note')) {
                $table->dropColumn('sss_participants_note');
            }
        });
    }
};
