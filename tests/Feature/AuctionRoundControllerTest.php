<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionRoundControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_returns_null_active_and_empty_ended_when_no_rounds_exist(): void
    {
        $this->getJson('/api/rounds/current')->assertOk()->assertJson(['active' => null, 'ended' => []]);
    }

    public function test_current_returns_active_round_and_empty_ended_array(): void
    {
        $round = $this->createRound(['name' => 'Spring Round', 'status' => 'active']);

        $this
            ->getJson('/api/rounds/current')
            ->assertOk()
            ->assertJsonPath('active.id', $round->id)
            ->assertJsonPath('active.name', 'Spring Round')
            ->assertJsonPath('ended', []);
    }

    public function test_current_returns_all_ended_rounds_newest_first(): void
    {
        $older = $this->createRound(['name' => 'Round 1', 'status' => 'ended', 'ends_at' => now()->subDays(3)]);
        $newer = $this->createRound(['name' => 'Round 2', 'status' => 'ended', 'ends_at' => now()->subDay()]);

        $this
            ->getJson('/api/rounds/current')
            ->assertOk()
            ->assertJsonPath('active', null)
            ->assertJsonPath('ended.0.id', $newer->id)
            ->assertJsonPath('ended.1.id', $older->id);
    }

    public function test_current_returns_active_and_all_ended_rounds(): void
    {
        $active = $this->createRound(['name' => 'Active Round', 'status' => 'active']);
        $ended1 = $this->createRound(['name' => 'Old Round', 'status' => 'ended', 'ends_at' => now()->subDays(2)]);
        $ended2 = $this->createRound(['name' => 'Recent Ended', 'status' => 'ended', 'ends_at' => now()->subHour()]);

        $response = $this->getJson('/api/rounds/current')->assertOk()->assertJsonPath('active.id', $active->id);

        $endedIds = collect($response->json('ended'))->pluck('id');
        $this->assertContains($ended1->id, $endedIds);
        $this->assertContains($ended2->id, $endedIds);
        $this->assertSame($ended2->id, $endedIds->first());
    }

    public function test_current_response_includes_required_round_fields(): void
    {
        $active = $this->createRound(['name' => 'Test Round', 'status' => 'active']);

        $this
            ->getJson('/api/rounds/current')
            ->assertOk()
            ->assertJsonStructure([
                'active' => ['id', 'name', 'status', 'ends_at', 'created_at'],
                'ended',
            ]);
    }

    public function test_admin_index_lists_rounds_newest_first_with_auction_counts(): void
    {
        $older = $this->createRound(['name' => 'Old', 'status' => 'ended', 'ends_at' => now()->subDay()]);
        $older->created_at = now()->subDays(5);
        $older->save();
        $newer = $this->createRound(['name' => 'New', 'status' => 'active']);
        $this->createAuction(null, ['auction_round_id' => $newer->id]);
        $this->createAuction(null, ['auction_round_id' => $newer->id]);
        $this->createAuction(null, ['auction_round_id' => $older->id]);

        $this
            ->actingAs($this->createAdmin())
            ->getJson('/api/rounds')
            ->assertOk()
            ->assertJsonCount(2, 'rounds')
            ->assertJsonPath('rounds.0.id', $newer->id)
            ->assertJsonPath('rounds.0.auction_count', 2)
            ->assertJsonPath('rounds.1.id', $older->id)
            ->assertJsonPath('rounds.1.auction_count', 1)
            ->assertJsonStructure(['rounds' => [['id', 'name', 'status', 'ends_at', 'created_at', 'auction_count']]]);
    }

    public function test_admin_can_create_a_round_and_it_is_audited(): void
    {
        $admin = $this->createAdmin();

        $response = $this
            ->actingAs($admin)
            ->postJson('/api/rounds', ['name' => 'Autumn Round'])
            ->assertCreated()
            ->assertJsonPath('round.name', 'Autumn Round')
            ->assertJsonPath('round.status', 'active')
            ->assertJsonPath('round.ends_at', null);

        $this->assertDatabaseHas('auction_rounds', ['name' => 'Autumn Round', 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'round.create',
            'target_type' => 'AuctionRound',
            'target_id' => $response->json('round.id'),
        ]);
    }

    public function test_creating_a_round_requires_a_name(): void
    {
        $this
            ->actingAs($this->createAdmin())
            ->postJson('/api/rounds', ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->actingAs($this->createAdmin())->postJson('/api/rounds', [])->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('auction_rounds', 0);
    }

    public function test_creating_a_round_is_rejected_while_another_round_is_active(): void
    {
        $this->createRound(['status' => 'active']);

        $this
            ->actingAs($this->createAdmin())
            ->postJson('/api/rounds', ['name' => 'Second'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'An active round already exists. Close it before creating a new one.');

        $this->assertDatabaseCount('auction_rounds', 1);
    }

    public function test_closing_a_round_ends_its_running_auctions_only(): void
    {
        $admin = $this->createAdmin();
        $round = $this->createRound(['status' => 'active']);
        $otherRound = $this->createRound(['status' => 'ended', 'ends_at' => now()->subDay()]);
        $running = $this->createAuction(null, ['auction_round_id' => $round->id, 'ends_at' => now()->addDay()]);
        $this->createBid($running);
        $alreadyEnded = $this->createAuction(null, [
            'auction_round_id' => $round->id,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $elsewhere = $this->createAuction(null, ['auction_round_id' => $otherRound->id, 'ends_at' => now()->addDay()]);
        $unassigned = $this->createAuction(null, ['ends_at' => now()->addDay()]);

        $this
            ->actingAs($admin)
            ->postJson("/api/rounds/{$round->id}/close")
            ->assertOk()
            ->assertJsonPath('round.id', $round->id)
            ->assertJsonPath('round.status', 'ended');

        $this->assertNotNull($round->fresh()?->ends_at);
        $this->assertSame('ended', $running->fresh()?->status);
        $this->assertSame('ended', $alreadyEnded->fresh()?->status);
        $this->assertSame('active', $elsewhere->fresh()?->status);
        $this->assertSame('active', $unassigned->fresh()?->status);

        $log = AuditLog::query()->where('action', 'round.close')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($round->id, $log->target_id);
        $this->assertSame(1, $log->data['auctions_ended'] ?? null);
    }

    public function test_closing_a_round_that_is_not_active_is_rejected(): void
    {
        $round = $this->createRound(['status' => 'ended', 'ends_at' => now()->subDay()]);

        $this
            ->actingAs($this->createAdmin())
            ->postJson("/api/rounds/{$round->id}/close")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This round is not active.');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_round_management_routes_are_admin_only(): void
    {
        $round = $this->createRound(['status' => 'active']);
        $user = $this->createUser();

        $this->getJson('/api/rounds')->assertUnauthorized();
        $this->postJson('/api/rounds', ['name' => 'X'])->assertUnauthorized();
        $this->postJson("/api/rounds/{$round->id}/close")->assertUnauthorized();

        $this->actingAs($user)->getJson('/api/rounds')->assertForbidden();
        $this->actingAs($user)->postJson('/api/rounds', ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->postJson("/api/rounds/{$round->id}/close")->assertForbidden();
        $this->actingAs($user)->getJson("/api/rounds/{$round->id}/users/{$user->id}/quotes")->assertForbidden();

        $this->assertSame('active', $round->fresh()?->status);
    }
}
