<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('override_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('leftover_purchases', function (Blueprint $table) {
            $table->foreignId('override_sale_id')->nullable()->after('leftover_price_offer_id')->index()->constrained()->cascadeOnDelete();
        });

        DB::statement('DROP INDEX IF EXISTS leftover_purchases_auction_id_user_id_active');
        DB::statement('CREATE UNIQUE INDEX leftover_purchases_auction_id_user_id_active ON leftover_purchases(auction_id, user_id) WHERE deleted_at IS NULL AND is_override = 0');

        $orphans = DB::table('leftover_purchases')->where('is_override', true)->whereNull('override_sale_id')->get();

        foreach ($orphans as $purchase) {
            $saleId = DB::table('override_sales')->insertGetId([
                'user_id' => $purchase->user_id,
                'created_at' => $purchase->created_at,
                'updated_at' => $purchase->updated_at,
                'deleted_at' => $purchase->deleted_at,
            ]);

            DB::table('leftover_purchases')->where('id', $purchase->id)->update(['override_sale_id' => $saleId]);
        }
    }

    public function down(): void
    {
        Schema::table('leftover_purchases', function (Blueprint $table) {
            $table->dropForeign(['override_sale_id']);
            $table->dropIndex(['override_sale_id']);
        });

        Schema::table('leftover_purchases', function (Blueprint $table) {
            $table->dropColumn('override_sale_id');
        });

        DB::statement('DROP INDEX IF EXISTS leftover_purchases_auction_id_user_id_active');
        DB::statement('CREATE UNIQUE INDEX leftover_purchases_auction_id_user_id_active ON leftover_purchases(auction_id, user_id) WHERE deleted_at IS NULL AND is_override = 0');

        Schema::dropIfExists('override_sales');
    }
};
