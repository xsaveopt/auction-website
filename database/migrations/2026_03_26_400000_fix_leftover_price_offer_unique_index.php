<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leftover_price_offers', function (Blueprint $table) {
            $table->dropUnique(['auction_id', 'user_id']);
        });

        DB::statement('CREATE UNIQUE INDEX leftover_price_offers_auction_id_user_id_active ON leftover_price_offers(auction_id, user_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS leftover_price_offers_auction_id_user_id_active');

        Schema::table('leftover_price_offers', function (Blueprint $table) {
            $table->unique(['auction_id', 'user_id']);
        });
    }
};
