<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE diaries ALTER COLUMN locality_name DROP NOT NULL');
        DB::statement('ALTER TABLE diaries ALTER COLUMN locality_position DROP NOT NULL');
        DB::statement('ALTER TABLE diaries ALTER COLUMN karst_area DROP NOT NULL');
        DB::statement('ALTER TABLE diaries ALTER COLUMN orographic_unit DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE diaries SET locality_name = '' WHERE locality_name IS NULL");
        DB::statement("UPDATE diaries SET locality_position = '' WHERE locality_position IS NULL");
        DB::statement("UPDATE diaries SET karst_area = '' WHERE karst_area IS NULL");
        DB::statement("UPDATE diaries SET orographic_unit = '' WHERE orographic_unit IS NULL");

        DB::statement('ALTER TABLE diaries ALTER COLUMN locality_name SET NOT NULL');
        DB::statement('ALTER TABLE diaries ALTER COLUMN locality_position SET NOT NULL');
        DB::statement('ALTER TABLE diaries ALTER COLUMN karst_area SET NOT NULL');
        DB::statement('ALTER TABLE diaries ALTER COLUMN orographic_unit SET NOT NULL');
    }
};
