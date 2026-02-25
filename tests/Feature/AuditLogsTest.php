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

        $baseTime = Carbon::parse('2099-03-01 10:00:00');
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
        $this->assertGreaterThanOrEqual(3, $data['total']);
        $this->assertCount(2, $data['data']);
        $this->assertSame('third', $data['data'][0]['description']);
        $this->assertSame('second', $data['data'][1]['description']);
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

    public function test_admin_can_filter_audit_logs_by_search(): void
    {
        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
            'email' => 'search-admin@example.com',
        ]);

        Sanctum::actingAs($adminUser);

        activity()->causedBy($adminUser)->log('create_transaction');
        activity()->causedBy($adminUser)->log('delete_transaction');

        $response = $this->getJson('/api/audit-logs?search=create_transaction');

        $response->assertStatus(200)->assertJsonPath('success', true);

        $rows = $response->json('data.data');
        $this->assertNotEmpty($rows);
        $this->assertTrue(collect($rows)->every(fn ($row) => str_contains($row['description'], 'create_transaction')));
    }

    public function test_admin_can_filter_audit_logs_by_date_range(): void
    {
        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
        ]);

        Sanctum::actingAs($adminUser);

        Carbon::setTestNow(Carbon::parse('2099-04-01 09:00:00'));
        activity()->causedBy($adminUser)->log('old_log');

        Carbon::setTestNow(Carbon::parse('2099-04-10 09:00:00'));
        activity()->causedBy($adminUser)->log('new_log');
        Carbon::setTestNow();

        $response = $this->getJson('/api/audit-logs?start_date=2099-04-05&end_date=2099-04-12');

        $response->assertStatus(200)->assertJsonPath('success', true);

        $rows = $response->json('data.data');
        $descriptions = collect($rows)->pluck('description')->all();

        $this->assertContains('new_log', $descriptions);
        $this->assertNotContains('old_log', $descriptions);
    }

    public function test_admin_can_export_audit_logs_csv(): void
    {
        $adminTelegramUser = TelegramUser::factory()->admin()->create();
        $adminUser = User::factory()->create([
            'telegram_user_id' => $adminTelegramUser->id,
        ]);

        Sanctum::actingAs($adminUser);

        activity()->causedBy($adminUser)->log('export_target');

        $response = $this->get('/api/audit-logs/export?search=export_target');

        $response->assertStatus(200);

        $contentDisposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment;', $contentDisposition);
        $this->assertStringContainsString('audit-logs-', $contentDisposition);
        $this->assertStringContainsString('.csv', $contentDisposition);
    }
}
