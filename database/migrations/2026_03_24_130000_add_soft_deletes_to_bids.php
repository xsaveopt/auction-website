<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->softDeletes();
            $table->dropUnique(['auction_id', 'user_id']);
        });

        // Partial unique index so deleted bids don't block new ones for the same user+auction
        DB::statement('CREATE UNIQUE INDEX bids_auction_id_user_id_active ON bids(auction_id, user_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bids_auction_id_user_id_active');

        Schema::table('bids', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->unique(['auction_id', 'user_id']);
        });
    }
};
