<?php

namespace Tests\Unit;

use App\Support\MicrosoftSso;
use Tests\TestCase;

class MicrosoftSsoTest extends TestCase
{
    public function test_is_disabled_without_credentials(): void
    {
        $this->assertFalse(MicrosoftSso::enabled());
    }

    public function test_requires_both_client_id_and_secret(): void
    {
        config(['services.microsoft.client_id' => 'client-id']);
        $this->assertFalse(MicrosoftSso::enabled());

        config(['services.microsoft.client_id' => null, 'services.microsoft.client_secret' => 'client-secret']);
        $this->assertFalse(MicrosoftSso::enabled());

        config(['services.microsoft.client_id' => 'client-id']);
        $this->assertTrue(MicrosoftSso::enabled());
    }
}
