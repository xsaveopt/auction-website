<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    /** @var string */
    protected $signature = 'app:create-admin {username} {password}';

    /** @var string */
    protected $description = 'Create an admin user';

    public function handle(): int
    {
        $username = $this->argument('username');
        $password = $this->argument('password');

        if (User::where('username', $username)->exists()) {
            $this->error("User '{$username}' already exists.");

            return self::FAILURE;
        }

        $user = new User();
        $user->username = $username;
        $user->password = Hash::make($password);
        $user->is_admin = true;
        $user->save();

        $this->info("Admin user '{$username}' created.");

        return self::SUCCESS;
    }
}
