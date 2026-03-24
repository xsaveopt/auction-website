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
            $table->softDeletes();
            $table->dropUnique(['auction_id', 'user_id']);
        });

        DB::statement('CREATE UNIQUE INDEX leftover_purchases_auction_id_user_id_active ON leftover_purchases(auction_id, user_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS leftover_purchases_auction_id_user_id_active');

        Schema::table('leftover_purchases', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->unique(['auction_id', 'user_id']);
        });
    }
};
