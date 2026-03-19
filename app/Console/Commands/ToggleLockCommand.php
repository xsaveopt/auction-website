<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use Illuminate\Console\Command;

class ToggleLockCommand extends Command
{
    /** @var string */
    protected $signature = 'app:toggle-lock {--message= : Custom lock message shown to users}';

    /** @var string */
    protected $description = 'Toggle the site lock for non-admin users';

    public function handle(): int
    {
        $settings = SiteSetting::instance();

        $settings->is_locked = !$settings->is_locked;

        if ($settings->is_locked) {
            $settings->lock_message = $this->option('message');
        }

        $settings->updated_at = now();
        $settings->save();

        if ($settings->is_locked) {
            $msg = $settings->lock_message ?? '(default banner)';
            $this->warn("Site is now LOCKED. Message: {$msg}");
        } else {
            $this->info('Site is now UNLOCKED.');
        }

        return self::SUCCESS;
    }
}
