<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdminCommand extends Command
{
    /** @var string */
    protected $signature = 'app:make-admin {identifier}';

    /** @var string */
    protected $description = 'Promote a user to admin';

    public function handle(): int
    {
        $identifier = $this->argument('identifier');

        $user = User::query()
            ->where('username', $identifier)
            ->orWhere('microsoft_id', $identifier)
            ->first();

        if (! $user) {
            $this->error("User '{$identifier}' not found.");

            return self::FAILURE;
        }

        $user->is_admin = true;
        $user->save();

        $this->info("User '{$identifier}' is now an admin.");

        return self::SUCCESS;
    }
}
