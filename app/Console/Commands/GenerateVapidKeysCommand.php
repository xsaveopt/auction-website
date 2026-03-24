<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeysCommand extends Command
{
    protected $signature = 'app:generate-vapid-keys';

    protected $description = 'Generate a VAPID key pair for browser push notifications';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $publicKey = is_string($keys['publicKey'] ?? null) ? $keys['publicKey'] : '';
        $privateKey = is_string($keys['privateKey'] ?? null) ? $keys['privateKey'] : '';

        $this->line('WEB_PUSH_VAPID_PUBLIC_KEY=' . $publicKey);
        $this->line('WEB_PUSH_VAPID_PRIVATE_KEY=' . $privateKey);

        return self::SUCCESS;
    }
}
