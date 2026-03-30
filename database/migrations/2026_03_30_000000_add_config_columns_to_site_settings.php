<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->boolean('bidding_schedule_enabled')->default(true);
            $table->string('bidding_closed_start', 5)->default('09:00');
            $table->string('bidding_closed_end', 5)->default('18:00');
            $table->boolean('bidding_weekends_open')->default(true);
            $table->string('currency_symbol', 10)->default('$');
            $table->boolean('anti_sniping_enabled')->default(true);
            $table->integer('anti_sniping_window')->default(60);
            $table->integer('anti_sniping_extension')->default(300);
            $table->boolean('leftover_sales_enabled')->default(false);
            $table->decimal('leftover_price_factor', 5, 4)->default(0.75);
            $table->string('company_name')->nullable();
            $table->string('company_street')->nullable();
            $table->string('company_postal_code', 20)->nullable();
            $table->string('company_city')->nullable();
            $table->string('company_kvk', 50)->nullable();
            $table->string('company_btw', 50)->nullable();
            $table->string('company_iban_1', 50)->nullable();
            $table->string('company_iban_2', 50)->nullable();
            $table->decimal('invoice_btw_percentage', 5, 2)->default(21);
            $table->integer('invoice_payment_days')->default(30);
        });

        // Defaults — configure via the admin settings panel after migration
        DB::table('site_settings')->where('id', 1)->update([
            'bidding_schedule_enabled' => true,
            'bidding_closed_start' => '09:00',
            'bidding_closed_end' => '18:00',
            'bidding_weekends_open' => true,
            'currency_symbol' => '$',
            'anti_sniping_enabled' => true,
            'anti_sniping_window' => 60,
            'anti_sniping_extension' => 300,
            'leftover_sales_enabled' => false,
            'leftover_price_factor' => 0.75,
            'invoice_btw_percentage' => 21,
            'invoice_payment_days' => 30,
        ]);
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'bidding_schedule_enabled',
                'bidding_closed_start',
                'bidding_closed_end',
                'bidding_weekends_open',
                'currency_symbol',
                'anti_sniping_enabled',
                'anti_sniping_window',
                'anti_sniping_extension',
                'leftover_sales_enabled',
                'leftover_price_factor',
                'company_name',
                'company_street',
                'company_postal_code',
                'company_city',
                'company_kvk',
                'company_btw',
                'company_iban_1',
                'company_iban_2',
                'invoice_btw_percentage',
                'invoice_payment_days',
            ]);
        });
    }
};
