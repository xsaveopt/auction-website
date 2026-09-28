<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leftover_purchases', function (Blueprint $table) {
            $table->boolean('is_override')->default(false)->after('price_per_item');
        });

        DB::statement('DROP INDEX IF EXISTS leftover_purchases_auction_id_user_id_active');
        DB::statement('CREATE UNIQUE INDEX leftover_purchases_auction_id_user_id_active ON leftover_purchases(auction_id, user_id) WHERE deleted_at IS NULL AND is_override = 0');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS leftover_purchases_auction_id_user_id_active');
        DB::table('leftover_purchases')->where('is_override', true)->delete();
        DB::statement('CREATE UNIQUE INDEX leftover_purchases_auction_id_user_id_active ON leftover_purchases(auction_id, user_id) WHERE deleted_at IS NULL');

        Schema::table('leftover_purchases', function (Blueprint $table) {
            $table->dropColumn('is_override');
        });
    }
};
