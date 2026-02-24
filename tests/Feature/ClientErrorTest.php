<?php

namespace Tests\Feature;

use App\Services\TelegramNotifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class ClientErrorTest extends TestCase
{
    use DatabaseTransactions;
    use MockeryPHPUnitIntegration;

    public function test_client_error_requires_message(): void
    {
        $response = $this->postJson('/api/client-error', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['message']);
    }

    public function test_client_error_notifies_telegram(): void
    {
        $mock = Mockery::mock(TelegramNotifier::class);
        $mock->shouldReceive('notifyError')
            ->once()
            ->withArgs(function (string $title, array $context) {
                return $title === 'Frontend Error'
                    && $context['message'] === 'Something broke'
                    && $context['component'] === 'AuditLogsView'
                    && $context['url'] === 'https://example.test/audit-logs'
                    && $context['user'] === 'guest';
            });

        $this->app->instance(TelegramNotifier::class, $mock);

        $response = $this->postJson('/api/client-error', [
            'message' => 'Something broke',
            'url' => 'https://example.test/audit-logs',
            'component' => 'AuditLogsView',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }
}
