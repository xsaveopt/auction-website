<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->foreignId('auction_round_id')->nullable()->constrained('auction_rounds')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\AuctionRound::class);
            $table->dropColumn('auction_round_id');
        });
    }
};
