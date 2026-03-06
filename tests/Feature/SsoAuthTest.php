<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SsoAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_sso_enabled_endpoint_requires_both_client_id_and_secret(): void
    {
        Config::set('services.microsoft.client_id', null);
        Config::set('services.microsoft.client_secret', null);

        $this->getJson('/api/auth/sso/enabled')
            ->assertOk()
            ->assertJson(['enabled' => false]);

        Config::set('services.microsoft.client_id', 'client-id');
        Config::set('services.microsoft.client_secret', null);

        $this->getJson('/api/auth/sso/enabled')
            ->assertOk()
            ->assertJson(['enabled' => false]);

        Config::set('services.microsoft.client_secret', 'client-secret');

        $this->getJson('/api/auth/sso/enabled')
            ->assertOk()
            ->assertJson(['enabled' => true]);
    }

    public function test_register_is_forbidden_when_sso_is_enabled(): void
    {
        Config::set('services.microsoft.client_id', 'client-id');
        Config::set('services.microsoft.client_secret', 'client-secret');

        $this->postJson('/api/register', [
            'username' => 'person@example.com',
            'password' => 'password123',
        ])->assertForbidden()
            ->assertJson(['message' => 'SSO is enabled. Please sign in with Microsoft.']);
    }

    public function test_login_is_forbidden_when_sso_is_enabled(): void
    {
        User::create([
            'username' => 'person@example.com',
            'password' => 'password123',
        ]);

        Config::set('services.microsoft.client_id', 'client-id');
        Config::set('services.microsoft.client_secret', 'client-secret');

        $this->postJson('/api/login', [
            'username' => 'person@example.com',
            'password' => 'password123',
        ])->assertForbidden()
            ->assertJson(['message' => 'SSO is enabled. Please sign in with Microsoft.']);
    }
}
