<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use Illuminate\Console\Command;

class AnnounceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:announce
                            {message? : The announcement message (omit to clear)}
                            {--clear : Deactivate the current announcement}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or clear a site-wide announcement';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if ($this->option('clear') || $this->argument('message') === null) {
            $count = Announcement::where('is_active', true)->count();

            if ($count === 0) {
                $this->info('No active announcement to clear.');
                return;
            }

            Announcement::where('is_active', true)->update(['is_active' => false]);
            $this->info("Cleared {$count} active announcement(s).");
            return;
        }

        // Deactivate existing announcements
        Announcement::where('is_active', true)->update(['is_active' => false]);

        /** @var string $message */
        $message = $this->argument('message');

        Announcement::create([
            'message' => $message,
            'is_active' => true,
            'author_id' => null,
        ]);

        $this->info('Announcement published: "' . $message . '"');
    }
}
