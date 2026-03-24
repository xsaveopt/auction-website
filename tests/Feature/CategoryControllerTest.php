<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_categories_sorted_by_order_then_name(): void
    {
        $this->createCategory(['name' => 'Zeta', 'slug' => 'zeta', 'sort_order' => 2]);
        $this->createCategory(['name' => 'Alpha', 'slug' => 'alpha', 'sort_order' => 1]);
        $this->createCategory(['name' => 'Beta', 'slug' => 'beta', 'sort_order' => 1]);

        $this
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('categories.0.name', 'Alpha')
            ->assertJsonPath('categories.1.name', 'Beta')
            ->assertJsonPath('categories.2.name', 'Zeta');
    }

    public function test_admin_can_create_update_and_delete_categories(): void
    {
        $admin = $this->createAdmin();

        $first = $this->actingAs($admin)->postJson('/api/categories', [
            'name' => 'Heavy Machinery',
            'sort_order' => 3,
        ]);
        $second = $this->actingAs($admin)->postJson('/api/categories', [
            'name' => 'Heavy Machinery',
            'sort_order' => 4,
        ]);

        $firstId = $first->json('category.id');

        $first->assertCreated()->assertJsonPath('category.slug', 'heavy-machinery');
        $second->assertCreated()->assertJsonPath('category.slug', 'heavy-machinery-1');

        $this
            ->actingAs($admin)
            ->putJson("/api/categories/{$firstId}", [
                'name' => 'Industrial Equipment',
                'sort_order' => 9,
            ])
            ->assertOk()
            ->assertJsonPath('category.slug', 'industrial-equipment');

        $this
            ->actingAs($admin)
            ->deleteJson("/api/categories/{$firstId}")
            ->assertOk()
            ->assertJsonPath('message', 'Category deleted.');

        $this->assertSoftDeleted('categories', ['id' => $firstId]);
    }
}
