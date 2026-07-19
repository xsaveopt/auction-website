<?php

namespace Tests\Feature\Commands;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_admin_creates_an_admin_user(): void
    {
        $this->artisan('app:create-admin', ['username' => 'newadmin', 'password' => 'secret123'])->assertExitCode(0);

        $this->assertDatabaseHas('users', ['username' => 'newadmin', 'is_admin' => true]);
    }

    public function test_create_admin_fails_when_username_already_exists(): void
    {
        $this->createUser(['username' => 'existing']);

        $this->artisan('app:create-admin', ['username' => 'existing', 'password' => 'secret123'])->assertExitCode(1);
    }

    public function test_make_admin_promotes_an_existing_user(): void
    {
        $user = $this->createUser(['username' => 'plain']);

        $this->artisan('app:make-admin', ['identifier' => 'plain'])->assertExitCode(0);

        $user->refresh();
        $this->assertTrue((bool) $user->is_admin);
    }

    public function test_make_admin_fails_for_unknown_user(): void
    {
        $this->artisan('app:make-admin', ['identifier' => 'ghost'])->assertExitCode(1);
    }

    public function test_remove_admin_demotes_an_admin(): void
    {
        $admin = $this->createAdmin(['username' => 'boss']);

        $this->artisan('app:remove-admin', ['identifier' => 'boss'])->assertExitCode(0);

        $admin->refresh();
        $this->assertFalse((bool) $admin->is_admin);
    }

    public function test_remove_admin_is_a_no_op_for_non_admins(): void
    {
        $user = $this->createUser(['username' => 'regular']);

        $this->artisan('app:remove-admin', ['identifier' => 'regular'])->assertExitCode(0);

        $user->refresh();
        $this->assertFalse((bool) $user->is_admin);
    }

    public function test_remove_admin_fails_for_unknown_user(): void
    {
        $this->artisan('app:remove-admin', ['identifier' => 'ghost'])->assertExitCode(1);
    }

    public function test_reset_password_updates_the_users_password(): void
    {
        $user = $this->createUser(['username' => 'resetme']);

        $this->artisan('app:reset-password', ['username' => 'resetme', 'password' => 'newpassword'])->assertExitCode(0);

        $this->assertTrue(Hash::check('newpassword', $user->fresh()->password));
    }

    public function test_reset_password_fails_for_unknown_user(): void
    {
        $this->artisan('app:reset-password', ['username' => 'ghost', 'password' => 'whatever'])->assertExitCode(0);
    }

    public function test_delete_user_removes_user_and_bids_with_confirmation(): void
    {
        $user = $this->createUser(['username' => 'deleteme']);
        $auction = $this->createAuction();
        $this->createBid($auction, $user);

        $this
            ->artisan('app:delete-user', ['identifier' => 'deleteme'])
            ->expectsConfirmation('Delete user "deleteme" and their 1 bid(s)?', 'yes')
            ->assertExitCode(0);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertSoftDeleted('bids', ['user_id' => $user->id]);
    }

    public function test_delete_user_force_skips_confirmation(): void
    {
        $user = $this->createUser(['username' => 'forcedelete']);

        $this->artisan('app:delete-user', ['identifier' => 'forcedelete', '--force' => true])->assertExitCode(0);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_delete_user_declining_confirmation_keeps_user(): void
    {
        $user = $this->createUser(['username' => 'keepme']);

        $this
            ->artisan('app:delete-user', ['identifier' => 'keepme'])
            ->expectsConfirmation('Delete user "keepme" and their 0 bid(s)?', 'no')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_delete_user_fails_for_unknown_user(): void
    {
        $this->artisan('app:delete-user', ['identifier' => 'ghost'])->assertExitCode(0);
    }

    public function test_list_users_shows_matching_users(): void
    {
        $this->createUser(['username' => 'alice']);
        $this->createAdmin(['username' => 'bob']);

        $this->artisan('app:list-users')->assertExitCode(0);
    }

    public function test_list_users_filters_by_admins_only(): void
    {
        $this->createUser(['username' => 'carol']);
        $this->createAdmin(['username' => 'dave']);

        $this->artisan('app:list-users', ['--admins' => true])->assertExitCode(0);
    }

    public function test_list_users_reports_when_none_found(): void
    {
        User::query()->delete();

        $this
            ->artisan('app:list-users', ['--search' => 'nomatch'])
            ->expectsOutput('No users found.')
            ->assertExitCode(0);
    }
}
