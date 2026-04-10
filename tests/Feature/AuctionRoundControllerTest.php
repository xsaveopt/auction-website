<?php

namespace Tests\Feature;

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
        // Newest ended first
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
}
