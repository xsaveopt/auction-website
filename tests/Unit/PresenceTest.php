<?php

namespace Tests\Unit;

use App\Models\PresenceHeartbeat;
use App\Support\Presence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PresenceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_heartbeat_upserts_a_single_row_per_page(): void
    {
        $auction = $this->createAuction();

        Presence::heartbeat('page-1', 'client-1', 'home', '/');
        Presence::heartbeat('page-1', 'client-1', 'auction', "/auctions/{$auction->id}", $auction->id);

        $this->assertSame(1, PresenceHeartbeat::query()->count());
        $this->assertDatabaseHas('presence_heartbeats', [
            'page_id' => 'page-1',
            'page_type' => 'auction',
            'auction_id' => $auction->id,
        ]);
    }

    public function test_watchers_count_distinct_recent_clients_per_auction(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24 12:00:00'));
        $auction = $this->createAuction();
        $other = $this->createAuction();

        Presence::heartbeat('page-1', 'client-1', 'auction', '/a', $auction->id);
        Presence::heartbeat('page-2', 'client-1', 'auction', '/a', $auction->id);
        Presence::heartbeat('page-3', 'client-2', 'auction', '/a', $auction->id);
        Presence::heartbeat('page-4', 'client-3', 'auction', '/b', $other->id);

        Carbon::setTestNow(now()->addSeconds(Presence::HEARTBEAT_TTL_SECONDS - 1));
        Presence::heartbeat('page-5', 'client-4', 'auction', '/b', $other->id);

        $this->assertSame(2, Presence::watchersForAuction($auction->id));
        $this->assertSame(
            [$auction->id => 2, $other->id => 2],
            Presence::watcherCountsForAuctions([
                $auction->id,
                $other->id,
            ]),
        );
        $this->assertSame(4, Presence::onlineUsers());

        Carbon::setTestNow(now()->addSeconds(2));

        $this->assertSame(0, Presence::watchersForAuction($auction->id));
        $this->assertSame([$other->id => 1], Presence::watcherCountsForAuctions([$auction->id, $other->id]));
        $this->assertSame(1, Presence::onlineUsers());
        $this->assertSame([], Presence::watcherCountsForAuctions([]));
    }

    public function test_heartbeat_prunes_stale_rows_at_most_once_per_cleanup_interval(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24 12:00:00'));

        PresenceHeartbeat::query()->create([
            'page_id' => 'stale-1',
            'client_id' => 'client-old',
            'page_type' => 'home',
            'path' => '/',
            'last_seen_at' => now()->subMinutes(5),
        ]);

        Presence::heartbeat('page-1', 'client-1', 'home', '/');

        $this->assertDatabaseMissing('presence_heartbeats', ['page_id' => 'stale-1']);

        PresenceHeartbeat::query()->create([
            'page_id' => 'stale-2',
            'client_id' => 'client-old',
            'page_type' => 'home',
            'path' => '/',
            'last_seen_at' => now()->subMinutes(5),
        ]);

        Presence::heartbeat('page-2', 'client-2', 'home', '/');

        $this->assertDatabaseHas('presence_heartbeats', ['page_id' => 'stale-2']);
    }

    public function test_total_views_count_each_client_once_per_auction(): void
    {
        $auction = $this->createAuction(null, ['title' => 'Viewed']);
        $other = $this->createAuction(null, ['title' => 'Other']);

        Presence::heartbeat('page-1', 'client-1', 'auction', '/a', $auction->id);
        Presence::heartbeat('page-2', 'client-1', 'auction', '/a', $auction->id);
        Presence::heartbeat('page-3', 'client-2', 'auction', '/a', $auction->id);
        Presence::heartbeat('page-4', 'client-1', 'auction', '/b', $other->id);
        Presence::heartbeat('page-5', 'client-3', 'home', '/');

        $this->assertSame(3, DB::table('auction_total_views')->count());

        $views = collect(Presence::totalViewsByAuction())->keyBy('auction_id');
        $this->assertSame(['auction_id' => $auction->id, 'title' => 'Viewed', 'view_count' => 2], $views[$auction->id]);
        $this->assertSame(1, $views[$other->id]['view_count'] ?? null);
    }

    public function test_online_user_details_list_recent_non_admin_users_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24 12:00:00'));
        $user = $this->createUser();
        $admin = $this->createAdmin();

        Presence::heartbeat('page-1', 'client-1', 'home', '/', null, $user->id);
        Presence::heartbeat('page-2', 'client-2', 'home', '/', null, $admin->id);
        Presence::heartbeat('page-3', 'client-3', 'home', '/');

        $details = Presence::onlineUserDetails();

        $this->assertCount(1, $details);
        $this->assertSame($user->username, $details[0]['username']);
        $this->assertSame('/', $details[0]['path']);
        $this->assertSame(now()->getTimestamp() * 1000, $details[0]['last_seen_at']);
    }
}
