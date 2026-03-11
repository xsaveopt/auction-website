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
                            {--search= : Filter by username or Microsoft ID}
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
        /** @var string|null $search */
        $search = $this->option('search');

        if (is_string($search) && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")->orWhere('microsoft_id', 'like', "%{$search}%");
            });
        }

        if ($this->option('admins')) {
            $query->where('is_admin', true);
        }

        $headers = ['ID', 'Username', 'Microsoft ID', 'Is Admin', 'Created At'];

        $users = $query
            ->latest()
            ->limit((int) $this->option('limit'))
            ->get(['id', 'username', 'microsoft_id', 'is_admin', 'created_at'])
            ->map(function (User $user): array {
                return [
                    $user->id,
                    $user->username,
                    $user->microsoft_id ?? '-',
                    $user->is_admin ? 'Yes' : 'No',
                    $user->created_at?->toDateTimeString() ?? 'Unknown',
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
