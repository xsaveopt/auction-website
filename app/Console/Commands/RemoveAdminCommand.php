<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class RemoveAdminCommand extends Command
{
    /** @var string */
    protected $signature = 'app:remove-admin {identifier}';

    /** @var string */
    protected $description = 'Remove admin privileges from a user';

    public function handle(): int
    {
        $identifier = $this->argument('identifier');

        $user = User::query()
            ->where('username', $identifier)
            ->orWhere('microsoft_id', $identifier)
            ->first();

        if (!$user) {
            $this->error("User '{$identifier}' not found.");

            return self::FAILURE;
        }

        if (!$user->is_admin) {
            $this->warn("User '{$identifier}' is not an admin.");

            return self::SUCCESS;
        }

        $user->is_admin = false;
        $user->save();

        $this->info("User '{$identifier}' is no longer an admin.");

        return self::SUCCESS;
    }
}
