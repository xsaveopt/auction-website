<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
            $table->dropUnique(['username']);
        });

        DB::statement('CREATE UNIQUE INDEX users_username_active ON users(username) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_username_active');

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->unique(['username']);
        });
    }
};
