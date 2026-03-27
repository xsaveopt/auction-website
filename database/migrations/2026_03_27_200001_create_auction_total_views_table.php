<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_total_views', function (Blueprint $table) {
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->string('client_id', 100);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['auction_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_total_views');
    }
};
