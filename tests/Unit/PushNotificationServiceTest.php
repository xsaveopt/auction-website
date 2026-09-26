<?php

namespace Tests\Unit;

use App\Models\PushSubscription;
use App\Support\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\VAPID;
use Mockery;
use Tests\TestCase;

class PushNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function configureVapid(): void
    {
        $keys = VAPID::createVapidKeys();

        config([
            'services.webpush.public_key' => $keys['publicKey'],
            'services.webpush.private_key' => $keys['privateKey'],
            'services.webpush.subject' => 'mailto:test@example.com',
        ]);
    }

    public function test_service_is_not_configured_without_all_keys(): void
    {
        $service = new PushNotificationService();

        $this->assertFalse($service->isConfigured());
        $this->assertNull($service->publicKey());

        config([
            'services.webpush.public_key' => 'public',
            'services.webpush.private_key' => 'private',
            'services.webpush.subject' => '',
        ]);

        $this->assertFalse($service->isConfigured());
        $this->assertSame('public', $service->publicKey());

        config(['services.webpush.subject' => 'mailto:test@example.com']);

        $this->assertTrue($service->isConfigured());
    }

    public function test_public_key_ignores_empty_and_non_string_values(): void
    {
        $service = new PushNotificationService();

        config(['services.webpush.public_key' => '']);
        $this->assertNull($service->publicKey());

        config(['services.webpush.public_key' => 123]);
        $this->assertNull($service->publicKey());
    }

    public function test_sending_is_a_no_op_when_not_configured(): void
    {
        Log::spy();
        $user = $this->createUser();
        $subscription = $this->createPushSubscription($user, ['content_encoding' => 'unsupported']);

        $service = new PushNotificationService();
        $service->sendToUser($user, ['body' => 'Hello']);
        $service->sendToSubscriptions(PushSubscription::query()->get(), ['body' => 'Hello']);

        $this->assertNotSoftDeleted('push_subscriptions', ['id' => $subscription->id]);
        Log::shouldNotHaveReceived('warning');
    }

    public function test_sending_to_users_without_subscriptions_does_nothing(): void
    {
        $this->configureVapid();
        Log::spy();

        new PushNotificationService()->sendToUsers([$this->createUser()], ['body' => 'Hello']);
        new PushNotificationService()->sendToUsers([], ['body' => 'Hello']);

        Log::shouldNotHaveReceived('warning');
    }

    public function test_invalid_subscriptions_are_dropped_and_logged(): void
    {
        $this->configureVapid();
        Log::spy();

        $user = $this->createUser();
        $other = $this->createUser();
        $invalid = $this->createPushSubscription($user, ['content_encoding' => 'unsupported']);
        $untouched = $this->createPushSubscription($other, ['content_encoding' => 'unsupported']);

        new PushNotificationService()->sendToUser($user, ['title' => 'Outbid', 'body' => 'Hello']);

        $this->assertSoftDeleted('push_subscriptions', ['id' => $invalid->id]);
        $this->assertNotSoftDeleted('push_subscriptions', ['id' => $untouched->id]);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'Dropping invalid push subscription.',
                Mockery::on(
                    fn(array $context) => (
                        $context['subscription_id'] === $invalid->id
                        && $context['user_id'] === $user->id
                    ),
                ),
            );
    }
}
