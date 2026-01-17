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
            if (!Schema::hasColumn('diaries', 'work_time')) {
                $table->string('work_time')->nullable();
            }

            if (!Schema::hasColumn('diaries', 'leader_signed_person_id')) {
                $table->unsignedBigInteger('leader_signed_person_id')->nullable();
            }

            if (!Schema::hasColumn('diaries', 'club_signed_person_id')) {
                $table->unsignedBigInteger('club_signed_person_id')->nullable();
            }

            if (Schema::hasColumn('diaries', 'work_time_from')) {
                $table->dropColumn('work_time_from');
            }

            if (Schema::hasColumn('diaries', 'work_time_to')) {
                $table->dropColumn('work_time_to');
            }

            if (Schema::hasColumn('diaries', 'attachments_count')) {
                $table->dropColumn('attachments_count');
            }

            if (Schema::hasColumn('diaries', 'leader_signed_name')) {
                $table->dropColumn('leader_signed_name');
            }

            if (Schema::hasColumn('diaries', 'club_signed_name')) {
                $table->dropColumn('club_signed_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diaries', function (Blueprint $table) {
            if (!Schema::hasColumn('diaries', 'work_time_from')) {
                $table->time('work_time_from')->nullable();
            }

            if (!Schema::hasColumn('diaries', 'work_time_to')) {
                $table->time('work_time_to')->nullable();
            }

            if (!Schema::hasColumn('diaries', 'attachments_count')) {
                $table->unsignedInteger('attachments_count')->nullable();
            }

            if (!Schema::hasColumn('diaries', 'leader_signed_name')) {
                $table->string('leader_signed_name')->nullable();
            }

            if (!Schema::hasColumn('diaries', 'club_signed_name')) {
                $table->string('club_signed_name')->nullable();
            }

            if (Schema::hasColumn('diaries', 'leader_signed_person_id')) {
                $table->dropColumn('leader_signed_person_id');
            }

            if (Schema::hasColumn('diaries', 'club_signed_person_id')) {
                $table->dropColumn('club_signed_person_id');
            }

            if (Schema::hasColumn('diaries', 'work_time')) {
                $table->dropColumn('work_time');
            }
        });
    }
};
