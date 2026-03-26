<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS leftover_purchases_auction_id_user_id_unique');
        DB::statement('DROP INDEX IF EXISTS leftover_purchases_auction_id_user_id_active');
        DB::statement('CREATE UNIQUE INDEX leftover_purchases_auction_id_user_id_active ON leftover_purchases(auction_id, user_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS leftover_purchases_auction_id_user_id_active');
        DB::statement('CREATE UNIQUE INDEX leftover_purchases_auction_id_user_id_unique ON leftover_purchases(auction_id, user_id)');
    }
};
