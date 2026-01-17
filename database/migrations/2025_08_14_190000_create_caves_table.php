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
        Schema::create('caves', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('total_length')->nullable();
            $table->unsignedInteger('total_drop')->nullable();
            $table->text('description')->nullable();
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
        Schema::dropIfExists('caves');
    }
};
