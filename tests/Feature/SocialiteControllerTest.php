<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class SocialiteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_endpoint_reflects_sso_configuration(): void
    {
        $this->getJson('/api/auth/sso/enabled')->assertOk()->assertJsonPath('enabled', false);

        config([
            'services.microsoft.client_id' => 'client-id',
            'services.microsoft.client_secret' => 'client-secret',
        ]);

        $this->getJson('/api/auth/sso/enabled')->assertOk()->assertJsonPath('enabled', true);
    }

    public function test_sso_middleware_redirects_browser_requests_and_blocks_json_requests_when_enabled(): void
    {
        config([
            'services.microsoft.client_id' => 'client-id',
            'services.microsoft.client_secret' => 'client-secret',
        ]);

        $this->get('/')->assertRedirect(route('auth.microsoft.redirect'));
        $this->getJson('/api/auctions')->assertUnauthorized();
    }

    public function test_redirect_endpoint_uses_socialite_when_enabled(): void
    {
        config([
            'services.microsoft.client_id' => 'client-id',
            'services.microsoft.client_secret' => 'client-secret',
        ]);

        $provider = Mockery::mock();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://microsoft.test/oauth'));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('microsoft')
            ->andReturn($provider);

        $this->get('/auth/microsoft/redirect')->assertRedirect('https://microsoft.test/oauth');
    }

    public function test_redirect_and_callback_endpoints_return_forbidden_when_sso_is_disabled(): void
    {
        $this->get('/auth/microsoft/redirect')->assertForbidden()->assertJsonPath('message', 'SSO is disabled.');
        $this->get('/api/auth/microsoft/callback')->assertForbidden()->assertJsonPath('message', 'SSO is disabled.');
    }

    public function test_callback_creates_a_user_and_logs_them_in(): void
    {
        config([
            'services.microsoft.client_id' => 'client-id',
            'services.microsoft.client_secret' => 'client-secret',
        ]);

        $provider = Mockery::mock();
        $provider
            ->shouldReceive('user')
            ->once()
            ->andReturn(new class {
                public function getId(): string
                {
                    return 'microsoft-user-1';
                }

                public function getEmail(): string
                {
                    return 'microsoft@example.com';
                }
            });

        Socialite::shouldReceive('driver')
            ->once()
            ->with('microsoft')
            ->andReturn($provider);

        $this->get('/api/auth/microsoft/callback')->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'username' => 'microsoft@example.com',
            'microsoft_id' => 'microsoft-user-1',
        ]);
    }

    public function test_callback_redirects_to_login_when_socialite_fails(): void
    {
        config([
            'services.microsoft.client_id' => 'client-id',
            'services.microsoft.client_secret' => 'client-secret',
        ]);

        $provider = Mockery::mock();
        $provider
            ->shouldReceive('user')
            ->once()
            ->andThrow(new \Exception('boom'));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('microsoft')
            ->andReturn($provider);

        $this->get('/api/auth/microsoft/callback')->assertRedirect('/login?error=sso_failed');
    }

    public function test_callback_rejects_invalid_profiles_and_updates_existing_users(): void
    {
        config([
            'services.microsoft.client_id' => 'client-id',
            'services.microsoft.client_secret' => 'client-secret',
        ]);

        $invalidProvider = Mockery::mock();
        $invalidProvider
            ->shouldReceive('user')
            ->once()
            ->andReturn(new class {
                public function getId(): string
                {
                    return '';
                }

                public function getEmail(): string
                {
                    return '';
                }
            });

        Socialite::shouldReceive('driver')
            ->once()
            ->with('microsoft')
            ->andReturn($invalidProvider);

        $this->get('/api/auth/microsoft/callback')->assertRedirect('/login?error=sso_profile_invalid');

        $existingUser = $this->createUser([
            'username' => 'existing@example.com',
            'password' => 'password',
        ]);

        $updateProvider = Mockery::mock();
        $updateProvider
            ->shouldReceive('user')
            ->once()
            ->andReturn(new class {
                public function getId(): string
                {
                    return 'existing-microsoft-id';
                }

                public function getEmail(): string
                {
                    return 'existing@example.com';
                }
            });

        Socialite::shouldReceive('driver')
            ->once()
            ->with('microsoft')
            ->andReturn($updateProvider);

        $this->get('/api/auth/microsoft/callback')->assertRedirect('/');

        $this->assertDatabaseHas('users', [
            'id' => $existingUser->id,
            'username' => 'existing@example.com',
            'microsoft_id' => 'existing-microsoft-id',
            'password' => null,
        ]);
    }
}
