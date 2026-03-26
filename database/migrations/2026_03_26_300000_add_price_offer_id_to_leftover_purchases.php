<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leftover_purchases', function (Blueprint $table) {
            $table->foreignId('leftover_price_offer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leftover_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leftover_price_offer_id');
        });
    }
};
