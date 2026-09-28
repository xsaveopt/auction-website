<?php

namespace App\Support;

class MicrosoftSso
{
    public static function enabled(): bool
    {
        return filled(config('services.microsoft.client_id')) && filled(config('services.microsoft.client_secret'));
    }
}
