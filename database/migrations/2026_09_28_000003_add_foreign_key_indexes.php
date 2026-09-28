<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('auction_questions', function (Blueprint $table) {
            $table->index('auction_id');
        });

        Schema::table('auction_images', function (Blueprint $table) {
            $table->index('auction_id');
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->index('auction_round_id');
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('auction_questions', function (Blueprint $table) {
            $table->dropIndex(['auction_id']);
        });

        Schema::table('auction_images', function (Blueprint $table) {
            $table->dropIndex(['auction_id']);
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->dropIndex(['auction_round_id']);
        });
    }
};
