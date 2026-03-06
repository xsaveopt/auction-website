<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakeAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_make_admin_command_can_promote_by_microsoft_identifier(): void
    {
        $user = User::create([
            'username' => 'person@example.com',
            'microsoft_id' => 'microsoft-123',
            'password' => null,
        ]);

        $this->artisan('app:make-admin', ['identifier' => 'microsoft-123'])
            ->expectsOutput("User 'microsoft-123' is now an admin.")
            ->assertSuccessful();

        $this->assertTrue((bool) $user->fresh()?->is_admin);
    }
}
