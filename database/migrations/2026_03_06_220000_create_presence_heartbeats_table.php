<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presence_heartbeats', function (Blueprint $table) {
            $table->string('page_id')->primary();
            $table->string('client_id')->index();
            $table->string('page_type');
            $table->foreignId('auction_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();

            $table->index(['auction_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presence_heartbeats');
    }
};
