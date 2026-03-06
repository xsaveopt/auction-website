<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->unsignedInteger('max_per_bidder')->default(1)->after('quantity');
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('max_per_bidder');
        });
    }
};
