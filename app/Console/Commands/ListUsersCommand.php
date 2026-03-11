<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:list
                            {--search= : Filter by username or email}
                            {--admins : Show only administrators}
                            {--limit=20 : Number of users to display}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List registered users';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $query = User::query();

        if ($this->option('search')) {
            $search = $this->option('search');
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($this->option('admins')) {
            $query->where('is_admin', true);
        }

        $headers = ['ID', 'Username', 'Email', 'Is Admin', 'Created At'];

        $users = $query
            ->latest()
            ->limit((int) $this->option('limit'))
            ->get(['id', 'username', 'email', 'is_admin', 'created_at'])
            ->map(function ($user) {
                return [
                    $user->id,
                    $user->username,
                    $user->email,
                    $user->is_admin ? 'Yes' : 'No',
                    $user->created_at->toDateTimeString(),
                ];
            });

        $this->table($headers, $users);

        if ($users->isEmpty()) {
            $this->info('No users found.');
        } else {
            $count = $users->count();
            $total = User::count(); // Approximate total
            $this->info("Showing {$count} of approximately {$total} users.");
        }
    }
}
