<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_key_hash', 64)->nullable()->unique();
            $table->timestamp('api_key_created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['api_key_hash']);
            $table->dropColumn(['api_key_hash', 'api_key_created_at']);
        });
    }
};
