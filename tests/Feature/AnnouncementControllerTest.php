<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_publish_a_new_active_announcement(): void
    {
        $admin = $this->createAdmin();
        $oldAnnouncement = $this->createAnnouncement($admin, [
            'message' => 'Older update',
            'is_active' => true,
        ]);

        $this
            ->actingAs($admin)
            ->postJson('/api/announcement', [
                'message' => 'Fresh announcement',
            ])
            ->assertCreated()
            ->assertJsonPath('announcement.message', 'Fresh announcement');

        $this->assertDatabaseHas('announcements', [
            'id' => $oldAnnouncement->id,
            'is_active' => false,
        ]);

        $this
            ->getJson('/api/announcement')
            ->assertOk()
            ->assertJsonPath('announcement.message', 'Fresh announcement')
            ->assertJsonPath('announcement.author', $admin->username);
    }

    public function test_admin_can_remove_an_announcement(): void
    {
        $admin = $this->createAdmin();
        $announcement = $this->createAnnouncement($admin);

        $this
            ->actingAs($admin)
            ->deleteJson("/api/announcements/{$announcement->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Announcement removed.');

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'is_active' => false,
        ]);
    }

    public function test_active_endpoint_returns_null_when_no_active_announcement_exists(): void
    {
        $this->getJson('/api/announcement')->assertOk()->assertJsonPath('announcement', null);
    }
}
