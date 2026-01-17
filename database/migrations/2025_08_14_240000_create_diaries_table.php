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
        Schema::create('diaries', function (Blueprint $table) {
            $table->id();
            $table->string('report_number')->nullable();
            $table->string('locality_name');
            $table->string('locality_position');
            $table->string('karst_area');
            $table->string('orographic_unit');
            $table->date('action_date')->nullable();
            $table->string('work_time')->nullable();
            $table->text('weather')->nullable();
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->json('member_person_ids')->nullable();
            $table->json('other_person_ids')->nullable();
            $table->text('sss_participants_note')->nullable();
            $table->text('other_participants')->nullable();
            $table->longText('work_description')->nullable();
            $table->decimal('excavated_length_m', 8, 2)->nullable();
            $table->decimal('discovered_length_m', 8, 2)->nullable();
            $table->decimal('surveyed_length_m', 8, 2)->nullable();
            $table->decimal('surveyed_depth_m', 8, 2)->nullable();
            $table->unsignedBigInteger('leader_signed_person_id')->nullable();
            $table->date('leader_signed_at')->nullable();
            $table->unsignedBigInteger('club_signed_person_id')->nullable();
            $table->date('club_signed_at')->nullable();
            $table->userstamps();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diaries');
    }
};
