<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_settings_with_defaults_for_unset_values(): void
    {
        $this
            ->actingAs($this->createAdmin())
            ->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('settings.lock_message', '')
            ->assertJsonPath('settings.bidding_schedule_enabled', false)
            ->assertJsonPath('settings.anti_sniping_enabled', false)
            ->assertJsonPath('settings.leftover_sales_enabled', false)
            ->assertJsonPath('settings.leftover_price_factor', 0.75)
            ->assertJsonPath('settings.company_name', '')
            ->assertJsonStructure([
                'settings' => [
                    'is_locked',
                    'lock_message',
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
                ],
            ]);
    }

    public function test_admin_can_update_settings_and_receives_the_new_values(): void
    {
        $this
            ->actingAs($this->createAdmin())
            ->putJson('/api/admin/settings', [
                'is_locked' => true,
                'lock_message' => 'Closed for stocktake',
                'bidding_schedule_enabled' => true,
                'bidding_closed_start' => '08:30',
                'bidding_closed_end' => '17:15',
                'currency_symbol' => '€',
                'anti_sniping_window' => 120,
                'leftover_price_factor' => 0.5,
                'company_name' => 'Acme BV',
                'invoice_btw_percentage' => 9,
                'invoice_payment_days' => 14,
            ])
            ->assertOk()
            ->assertJsonPath('settings.is_locked', true)
            ->assertJsonPath('settings.lock_message', 'Closed for stocktake')
            ->assertJsonPath('settings.bidding_schedule_enabled', true)
            ->assertJsonPath('settings.bidding_closed_start', '08:30')
            ->assertJsonPath('settings.bidding_closed_end', '17:15')
            ->assertJsonPath('settings.currency_symbol', '€')
            ->assertJsonPath('settings.anti_sniping_window', 120)
            ->assertJsonPath('settings.leftover_price_factor', 0.5)
            ->assertJsonPath('settings.company_name', 'Acme BV')
            ->assertJsonPath('settings.invoice_payment_days', 14);

        $settings = SiteSetting::instance();
        $this->assertTrue($settings->is_locked);
        $this->assertSame('Closed for stocktake', $settings->lock_message);
        $this->assertSame(9.0, $settings->invoice_btw_percentage);
    }

    public function test_partial_update_leaves_other_settings_untouched(): void
    {
        $settings = SiteSetting::instance();
        $settings->company_city = 'Utrecht';
        $settings->save();

        $this
            ->actingAs($this->createAdmin())
            ->putJson('/api/admin/settings', ['company_name' => 'Only Name'])
            ->assertOk()
            ->assertJsonPath('settings.company_name', 'Only Name')
            ->assertJsonPath('settings.company_city', 'Utrecht');
    }

    public function test_update_rejects_invalid_values(): void
    {
        $this
            ->actingAs($this->createAdmin())
            ->putJson('/api/admin/settings', [
                'is_locked' => 'maybe',
                'bidding_closed_start' => '9am',
                'anti_sniping_window' => -1,
                'leftover_price_factor' => 11,
                'invoice_btw_percentage' => 101,
                'invoice_payment_days' => 0,
                'lock_message' => str_repeat('a', 501),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'is_locked',
                'bidding_closed_start',
                'anti_sniping_window',
                'leftover_price_factor',
                'invoice_btw_percentage',
                'invoice_payment_days',
                'lock_message',
            ]);

        $this->assertFalse(SiteSetting::instance()->is_locked);
    }

    public function test_settings_are_admin_only(): void
    {
        $this->getJson('/api/admin/settings')->assertUnauthorized();
        $this->putJson('/api/admin/settings', ['is_locked' => true])->assertUnauthorized();

        $user = $this->createUser();
        $this->actingAs($user)->getJson('/api/admin/settings')->assertForbidden();
        $this->actingAs($user)->putJson('/api/admin/settings', ['is_locked' => true])->assertForbidden();

        $this->assertFalse(SiteSetting::instance()->is_locked);
    }
}
