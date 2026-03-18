<?php

namespace App\Console\Commands;

use App\Models\PresenceHeartbeat;
use Illuminate\Console\Command;

class ClearPresenceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-presence
                            {--stale : Only delete records older than TTL (default: delete all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge presence heartbeat records';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if ($this->option('stale')) {
            $cutoff = now()->subSeconds(9);
            $count = PresenceHeartbeat::where('last_seen_at', '<', $cutoff)->count();

            if ($count === 0) {
                $this->info('No stale presence records found.');
                return;
            }

            PresenceHeartbeat::where('last_seen_at', '<', $cutoff)->delete();
            $this->info("Deleted {$count} stale presence record(s).");
        } else {
            $count = PresenceHeartbeat::count();

            if ($count === 0) {
                $this->info('No presence records found.');
                return;
            }

            PresenceHeartbeat::truncate();
            $this->info("Deleted all {$count} presence record(s).");
        }
    }
}
