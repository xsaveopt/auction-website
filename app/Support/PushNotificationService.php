<?php

namespace App\Support;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription as WebPushSubscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    public function isConfigured(): bool
    {
        return $this->publicKey() !== null && $this->privateKey() !== null && $this->subject() !== null;
    }

    public function publicKey(): ?string
    {
        $key = config('services.webpush.public_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function sendToUser(User $user, array $payload): void
    {
        $this->sendToUsers([$user], $payload);
    }

    /**
     * @param iterable<int, User> $users
     * @param array<string, mixed> $payload
     */
    public function sendToUsers(iterable $users, array $payload): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        /** @var \Illuminate\Support\Collection<int, int> $userIds */
        $userIds = collect($users)->pluck('id')->filter(fn(mixed $id) => is_int($id))->values();

        if ($userIds->isEmpty()) {
            return;
        }

        $subscriptions = PushSubscription::query()->whereIn('user_id', $userIds->all())->get();

        $this->sendToSubscriptions($subscriptions, $payload);
    }

    /**
     * @param Collection<int, PushSubscription> $subscriptions
     * @param array<string, mixed> $payload
     */
    public function sendToSubscriptions(Collection $subscriptions, array $payload): void
    {
        if (!$this->isConfigured() || $subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $this->subject(),
                'publicKey' => $this->publicKey(),
                'privateKey' => $this->privateKey(),
            ],
        ]);
        $webPush->setReuseVAPIDHeaders(true);

        $encodedPayload = json_encode($this->normalizePayload($payload), JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $subscription) {
            try {
                $webPush->queueNotification(WebPushSubscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]), $encodedPayload);
            } catch (\Throwable $e) {
                Log::warning('Dropping invalid push subscription.', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'error' => $e->getMessage(),
                ]);
                $subscription->delete();
            }
        }

        foreach ($webPush->flush() as $report) {
            if (!$report instanceof MessageSentReport) {
                continue;
            }

            if ($report->isSuccess()) {
                continue;
            }

            /** @var PushSubscription|null $subscription */
            $subscription = $subscriptions->firstWhere('endpoint', $report->getEndpoint());

            Log::warning('Push notification delivery failed.', [
                'subscription_id' => $subscription?->id,
                'user_id' => $subscription?->user_id,
                'endpoint' => $report->getEndpoint(),
                'reason' => $report->getReason(),
                'expired' => $report->isSubscriptionExpired(),
            ]);

            if ($subscription && $report->isSubscriptionExpired()) {
                $subscription->delete();
            }
        }
    }

    private function privateKey(): ?string
    {
        $key = config('services.webpush.private_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    private function subject(): ?string
    {
        $subject = config('services.webpush.subject');

        return is_string($subject) && $subject !== '' ? $subject : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $url = isset($payload['url']) && is_string($payload['url']) ? $payload['url'] : '/';
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];

        return [
            'title' => is_string($payload['title'] ?? null) ? $payload['title'] : config('app.name', 'Auction House'),
            'body' => is_string($payload['body'] ?? null) ? $payload['body'] : '',
            'icon' => is_string($payload['icon'] ?? null) ? $payload['icon'] : '/favicon.ico',
            'badge' => is_string($payload['badge'] ?? null) ? $payload['badge'] : '/favicon.ico',
            'tag' => is_string($payload['tag'] ?? null) ? $payload['tag'] : null,
            'data' => array_merge($data, ['url' => $url]),
        ];
    }
}
