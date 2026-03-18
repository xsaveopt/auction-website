<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetPasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-password
                            {username : The username to reset}
                            {password : The new password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset a user\'s password';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        /** @var string $username */
        $username = $this->argument('username');
        $user = User::where('username', $username)->first();

        if (!$user) {
            $this->error("User \"{$username}\" not found.");
            return;
        }

        /** @var string $password */
        $password = $this->argument('password');
        $user->password = Hash::make($password);
        $user->save();

        $this->info("Password reset for \"{$username}\".");
    }
}
