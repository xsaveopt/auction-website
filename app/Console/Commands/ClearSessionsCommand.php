<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ClearSessionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-sessions
                            {--user= : Clear sessions for a specific user (username)}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear user sessions (Redis and database)';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $username = $this->option('user');

        if ($username) {
            $user = User::where('username', $username)->first();
            if (!$user) {
                $this->error("User \"{$username}\" not found.");
                return;
            }

            $count = DB::table('sessions')->where('user_id', $user->id)->count();
            $this->info("Found {$count} database session(s) for \"{$username}\".");

            if (!$this->option('force') && !$this->confirm("Clear all sessions for \"{$username}\"?")) {
                return;
            }

            DB::table('sessions')->where('user_id', $user->id)->delete();
            $this->info("Cleared database sessions for \"{$username}\".");
        } else {
            $count = DB::table('sessions')->count();
            $this->info("Found {$count} database session(s) total.");

            if (!$this->option('force') && !$this->confirm('Clear ALL sessions? This will log out every user.')) {
                return;
            }

            DB::table('sessions')->truncate();

            /** @var string $driver */
            $driver = config('session.driver');
            if ($driver === 'redis') {
                /** @var string $connection */
                $connection = config('session.connection') ?? config('session.store') ?? 'default';
                /** @var string $prefix */
                $prefix = config('cache.prefix', '');
                $pattern = $prefix !== '' ? "{$prefix}:*" : '*';

                try {
                    /** @var \Illuminate\Redis\Connections\Connection $redis */
                    $redis = Redis::connection($connection);
                    /** @var array<int, string> $keys */
                    $keys = $redis->keys($pattern);

                    if ($keys !== []) {
                        /** @var \Redis $client */
                        $client = $redis->client();
                        $client->del(...$keys);
                        $this->info('Cleared Redis session cache (' . count($keys) . ' keys).');
                    }
                } catch (\Exception $e) {
                    $this->warn('Could not clear Redis sessions: ' . $e->getMessage());
                }
            }

            $this->info('All sessions cleared.');
        }
    }
}
