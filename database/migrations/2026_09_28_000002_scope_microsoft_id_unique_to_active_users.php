<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_microsoft_id_unique');
        DB::statement('CREATE UNIQUE INDEX users_microsoft_id_active ON users(microsoft_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_microsoft_id_active');
        DB::statement('CREATE UNIQUE INDEX users_microsoft_id_unique ON users(microsoft_id)');
    }
};
