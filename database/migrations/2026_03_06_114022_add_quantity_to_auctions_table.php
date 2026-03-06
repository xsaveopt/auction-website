<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('starting_price');
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->unique(['auction_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropUnique(['auction_id', 'user_id']);
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
