<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_locked')->default(false);
            $table->string('lock_message')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        DB::table('site_settings')->insert([
            'id' => 1,
            'is_locked' => false,
            'lock_message' => null,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
