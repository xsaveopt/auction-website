<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_fetch_the_current_user(): void
    {
        $response = $this->postJson('/api/register', [
            'username' => 'bidder-one',
            'password' => 'password',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.username', 'bidder-one')
            ->assertJsonPath('user.is_admin', false);

        $this->assertAuthenticated();
        $this->getJson('/api/user')->assertOk()->assertJsonPath('user.username', 'bidder-one');
    }

    public function test_user_can_log_in_and_log_out_with_username_and_password(): void
    {
        $user = $this->createUser([
            'username' => 'returning-bidder',
            'password' => 'password',
        ]);

        $this
            ->postJson('/api/login', [
                'username' => $user->username,
                'password' => 'password',
            ])
            ->assertOk()
            ->assertJsonPath('user.username', 'returning-bidder');

        $this->assertAuthenticatedAs($user);

        $this->postJson('/api/logout')->assertOk()->assertJsonPath('message', 'Logged out.');
        $this->assertGuest();
        $this->getJson('/api/user')->assertOk()->assertJsonPath('user', null);
    }

    public function test_password_auth_endpoints_are_blocked_when_sso_is_enabled(): void
    {
        config([
            'services.microsoft.client_id' => 'client-id',
            'services.microsoft.client_secret' => 'client-secret',
        ]);

        $this
            ->postJson('/api/register', [
                'username' => 'blocked-user',
                'password' => 'password',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'SSO is enabled. Please sign in with Microsoft.');

        $this
            ->postJson('/api/login', [
                'username' => 'blocked-user',
                'password' => 'password',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'SSO is enabled. Please sign in with Microsoft.');
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = $this->createUser([
            'username' => 'known-user',
            'password' => 'password',
        ]);

        $this
            ->postJson('/api/login', [
                'username' => $user->username,
                'password' => 'wrong-password',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_registration_rejects_duplicate_usernames(): void
    {
        $this->createUser(['username' => 'duplicate-user']);

        $this
            ->postJson('/api/register', [
                'username' => 'duplicate-user',
                'password' => 'password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }
}
