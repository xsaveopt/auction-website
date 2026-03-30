<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leftover_price_offers', function (Blueprint $table) {
            $table->timestamp('rebid_requested_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('leftover_price_offers', function (Blueprint $table) {
            $table->dropColumn('rebid_requested_at');
        });
    }
};
