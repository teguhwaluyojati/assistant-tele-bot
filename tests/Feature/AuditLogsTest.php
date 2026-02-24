<?php

namespace Tests\Feature;

use App\Models\TelegramUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_list_audit_logs_with_pagination(): void
    {
        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
        ]);

        Sanctum::actingAs($adminUser);

        $baseTime = now();
        Carbon::setTestNow($baseTime->copy()->subMinutes(2));
        activity()->causedBy($adminUser)->log('first');
        Carbon::setTestNow($baseTime->copy()->subMinute());
        activity()->causedBy($adminUser)->log('second');
        Carbon::setTestNow($baseTime->copy());
        activity()->causedBy($adminUser)->log('third');
        Carbon::setTestNow();

        $response = $this->getJson('/api/audit-logs?per_page=2');

        $response->assertStatus(200)->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertSame(2, $data['per_page']);
        $this->assertSame(3, $data['total']);
        $this->assertCount(2, $data['data']);
        $this->assertSame('third', $data['data'][0]['description']);
    }

    public function test_non_admin_cannot_access_audit_logs(): void
    {
        $telegramUser = TelegramUser::factory()->create(['level' => 2]);
        $user = User::factory()->create([
            'telegram_user_id' => $telegramUser->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/audit-logs');

        $response->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_user_without_telegram_link_cannot_access_audit_logs(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/audit-logs');

        $response->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_audit_logs_validation_rejects_large_per_page(): void
    {
        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->getJson('/api/audit-logs?per_page=200');

        $response->assertStatus(422)->assertJsonValidationErrors(['per_page']);
    }
}
