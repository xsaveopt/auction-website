<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfUnlessApiKey::class);
    }

    public function test_admins_start_without_an_api_key(): void
    {
        $this
            ->actingAs($this->createAdmin())
            ->getJson('/api/admin/api-key')
            ->assertOk()
            ->assertJsonPath('api_key.exists', false)
            ->assertJsonPath('api_key.created_at', null);
    }

    public function test_generating_returns_the_key_once_and_stores_only_its_hash(): void
    {
        $admin = $this->createAdmin();

        $key = $this
            ->actingAs($admin)
            ->postJson('/api/admin/api-key')
            ->assertCreated()
            ->assertJsonPath('api_key.exists', true)
            ->json('key');

        $this->assertIsString($key);
        $this->assertStringStartsWith('auk_', $key);

        $admin->refresh();
        $this->assertSame(hash('sha256', $key), $admin->api_key_hash);
        $this->assertTrue(User::findByApiKey($key)?->is($admin));

        $this
            ->getJson('/api/admin/api-key')
            ->assertOk()
            ->assertJsonPath('api_key.exists', true)
            ->assertJsonMissingPath('key');

        $this->assertSame(1, AuditLog::where('action', 'api_key.create')->where('user_id', $admin->id)->count());
    }

    public function test_regenerating_replaces_the_previous_key(): void
    {
        $admin = $this->createAdmin();
        $old = $admin->generateApiKey();

        $new = $this->actingAs($admin)->postJson('/api/admin/api-key')->assertCreated()->json('key');

        $this->assertNotSame($old, $new);
        $this->assertNull(User::findByApiKey($old));
        $this->assertSame(1, AuditLog::where('action', 'api_key.regenerate')->count());
    }

    public function test_revoking_removes_the_key(): void
    {
        $admin = $this->createAdmin();
        $key = $admin->generateApiKey();

        $this->actingAs($admin)->deleteJson('/api/admin/api-key')->assertOk()->assertJsonPath('api_key.exists', false);

        $this->assertNull(User::findByApiKey($key));
        $this->assertSame(1, AuditLog::where('action', 'api_key.delete')->count());
    }

    public function test_each_admin_manages_only_their_own_key(): void
    {
        $first = $this->createAdmin();
        $second = $this->createAdmin(['username' => 'second-admin']);
        $firstKey = $first->generateApiKey();

        $this->actingAs($second)->postJson('/api/admin/api-key')->assertCreated();
        $this->actingAs($second)->deleteJson('/api/admin/api-key')->assertOk();

        $this->assertTrue(User::findByApiKey($firstKey)?->is($first));
    }

    public function test_non_admins_cannot_manage_api_keys(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->getJson('/api/admin/api-key')->assertForbidden();
        $this->actingAs($user)->postJson('/api/admin/api-key')->assertForbidden();
        $this->actingAs($user)->deleteJson('/api/admin/api-key')->assertForbidden();
    }

    public function test_api_key_hash_is_never_serialized(): void
    {
        $admin = $this->createAdmin();
        $admin->generateApiKey();

        $this->assertArrayNotHasKey('api_key_hash', $admin->toArray());
    }
}
