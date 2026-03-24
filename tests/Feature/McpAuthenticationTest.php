<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_mcp_api_keys_authenticate_as_the_first_admin(): void
    {
        config(['auction.mcp_api_key' => 'mcp-secret']);

        $admin = $this->createAdmin();
        $this->createAdmin(['username' => 'second-admin']);

        $this
            ->withHeader('Authorization', 'Bearer mcp-secret')
            ->postJson('/api/announcement', [
                'message' => 'Authenticated via MCP',
            ])
            ->assertCreated()
            ->assertJsonPath('announcement.author', $admin->username);
    }

    public function test_invalid_mcp_api_keys_do_not_bypass_normal_authentication(): void
    {
        config(['auction.mcp_api_key' => 'mcp-secret']);

        $this->createAdmin();

        $this
            ->withHeader('Authorization', 'Bearer wrong-secret')
            ->postJson('/api/announcement', [
                'message' => 'Blocked MCP',
            ])
            ->assertUnauthorized();
    }
}
