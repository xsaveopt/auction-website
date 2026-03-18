<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class DeleteUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-user
                            {identifier : Username or Microsoft ID}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a user and their bids';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $identifier = $this->argument('identifier');
        $user = User::withCount(['bids', 'auctions'])
            ->where('username', $identifier)
            ->orWhere('microsoft_id', $identifier)
            ->first();

        if (!$user) {
            $this->error("User \"{$identifier}\" not found.");
            return;
        }

        $this->table(['Field', 'Value'], [
            ['ID', $user->id],
            ['Username', $user->username],
            ['Microsoft ID', $user->microsoft_id ?? '-'],
            ['Admin', $user->is_admin ? 'Yes' : 'No'],
            ['Bids', $user->bids_count],
            ['Auctions (seller)', $user->auctions_count],
        ]);

        if ($user->auctions_count > 0) {
            $this->warn(
                "This user is the seller on {$user->auctions_count} auction(s). Those auctions will keep their seller_id reference but the user row will be gone.",
            );
        }

        if (
            !$this->option('force')
            && !$this->confirm("Delete user \"{$user->username}\" and their {$user->bids_count} bid(s)?")
        ) {
            return;
        }

        /** @var int $bidCount */
        $bidCount = $user->bids()->delete();
        $user->delete();

        $this->info("Deleted user \"{$user->username}\" and {$bidCount} bid(s).");
    }
}
