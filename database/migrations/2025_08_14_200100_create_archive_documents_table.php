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
        Schema::create('archive_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('archive_id')->nullable()->index();
            $table->unsignedBigInteger('diary_id')->nullable()->index();
            $table->string('relation_type', 50)->nullable();
            $table->text('caption')->nullable();
            $table->unsignedInteger('seq')->nullable();
            $table->string('name');
            $table->string('type', 50);
            $table->string('mime_type')->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->json('meta')->nullable();
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
        Schema::dropIfExists('archive_documents');
    }
};
