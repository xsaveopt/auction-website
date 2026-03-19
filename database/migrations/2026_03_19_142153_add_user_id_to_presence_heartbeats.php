<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presence_heartbeats', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('client_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('presence_heartbeats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
