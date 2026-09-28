<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_keys_authenticate_as_their_owner_and_flag_audit_entries(): void
    {
        $this->createAdmin();
        $owner = $this->createAdmin(['username' => 'key-owner']);
        $key = $owner->generateApiKey();

        $this
            ->withHeader('Authorization', "Bearer {$key}")
            ->postJson('/api/announcement', [
                'message' => 'Authenticated via api key',
            ])
            ->assertCreated()
            ->assertJsonPath('announcement.author', 'key-owner');

        $log = AuditLog::where('action', 'announcement.create')->sole();
        $this->assertSame($owner->id, $log->user_id);
        $this->assertTrue($log->via_api_key);
    }

    public function test_session_actions_are_not_flagged_as_api_key_actions(): void
    {
        $admin = $this->createAdmin();

        $this
            ->actingAs($admin)
            ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfUnlessApiKey::class)
            ->postJson('/api/announcement', ['message' => 'Via session'])
            ->assertCreated();

        $this->assertFalse(AuditLog::where('action', 'announcement.create')->sole()->via_api_key);
    }

    public function test_invalid_api_keys_do_not_bypass_normal_authentication(): void
    {
        $this->createAdmin()->generateApiKey();

        $this
            ->withHeader('Authorization', 'Bearer auk_wrong')
            ->postJson('/api/announcement', [
                'message' => 'Blocked request',
            ])
            ->assertUnauthorized();
    }

    public function test_api_keys_stop_working_once_the_owner_is_no_longer_admin(): void
    {
        $admin = $this->createAdmin();
        $key = $admin->generateApiKey();
        $admin->forceFill(['is_admin' => false])->save();

        $this
            ->withHeader('Authorization', "Bearer {$key}")
            ->postJson('/api/announcement', ['message' => 'Demoted'])
            ->assertUnauthorized();
    }

    public function test_revoked_api_keys_no_longer_authenticate(): void
    {
        $admin = $this->createAdmin();
        $key = $admin->generateApiKey();
        $admin->revokeApiKey();

        $this
            ->withHeader('Authorization', "Bearer {$key}")
            ->postJson('/api/announcement', ['message' => 'Revoked'])
            ->assertUnauthorized();
    }
}
