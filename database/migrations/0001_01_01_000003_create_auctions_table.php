<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->decimal('starting_price', 10, 2);
            $table->dateTime('ends_at');
            $table->string('status')->default('active'); // active, ended, cancelled
            $table->timestamps();
        });

        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->index(['auction_id', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
        Schema::dropIfExists('auctions');
    }
};
