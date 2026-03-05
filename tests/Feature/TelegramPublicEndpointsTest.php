<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TelegramPublicEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_webhook_accepts_empty_payload_without_crashing(): void
    {
        $response = $this->postJson('/api/webhook', []);

        $response
            ->assertStatus(200)
            ->assertJsonPath('ok', true);
    }

    public function test_daily_expenses_broadcast_endpoint_returns_success_without_transactions(): void
    {
        $response = $this->get('/api/daily-expenses');

        $response->assertStatus(200);
    }
}
