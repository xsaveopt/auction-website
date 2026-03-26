<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_configuration_endpoint_reflects_the_current_keys(): void
    {
        config([
            'services.webpush.public_key' => 'public-key',
            'services.webpush.private_key' => 'private-key',
            'services.webpush.subject' => 'mailto:test@example.com',
        ]);

        $this
            ->actingAs($this->createUser())
            ->getJson('/api/push/config')
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('public_key', 'public-key');
    }

    public function test_authenticated_users_can_store_and_delete_push_subscriptions(): void
    {
        $user = $this->createUser();
        $endpoint = 'https://push.example/subscriptions/one';

        $this
            ->actingAs($user)
            ->putJson('/api/push/subscription', [
                'subscription' => [
                    'endpoint' => $endpoint,
                    'contentEncoding' => 'aes128gcm',
                    'keys' => [
                        'p256dh' => 'p256dh-key',
                        'auth' => 'auth-key',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Push subscription saved.');

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => $endpoint,
        ]);

        $this
            ->actingAs($user)
            ->deleteJson('/api/push/subscription', [
                'endpoint' => $endpoint,
            ])
            ->assertOk();

        $this->assertSoftDeleted('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => $endpoint,
        ]);
    }

    public function test_push_subscriptions_validate_content_encoding_and_can_be_reassigned_by_endpoint(): void
    {
        $firstUser = $this->createUser();
        $secondUser = $this->createUser();
        $endpoint = 'https://push.example/subscriptions/shared';

        $this
            ->actingAs($firstUser)
            ->putJson('/api/push/subscription', [
                'subscription' => [
                    'endpoint' => $endpoint,
                    'contentEncoding' => 'invalid',
                    'keys' => [
                        'p256dh' => 'p256dh-key',
                        'auth' => 'auth-key',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subscription.contentEncoding');

        $this
            ->actingAs($firstUser)
            ->putJson('/api/push/subscription', [
                'subscription' => [
                    'endpoint' => $endpoint,
                    'keys' => [
                        'p256dh' => 'first-key',
                        'auth' => 'first-auth',
                    ],
                ],
            ])
            ->assertCreated();

        $this
            ->actingAs($secondUser)
            ->putJson('/api/push/subscription', [
                'subscription' => [
                    'endpoint' => $endpoint,
                    'keys' => [
                        'p256dh' => 'second-key',
                        'auth' => 'second-auth',
                    ],
                ],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => $endpoint,
            'user_id' => $secondUser->id,
            'public_key' => 'second-key',
        ]);
    }
}
