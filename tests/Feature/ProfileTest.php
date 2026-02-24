<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_update_profile_changes_name_and_email(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/update-profile', [
            'name' => 'New Name',
            'email' => 'new.email@example.com',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('user.email', 'new.email@example.com')
            ->assertJsonPath('user.name', 'New Name');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new.email@example.com',
            'name' => 'New Name',
        ]);
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/change-password', [
            'current_password' => 'wrong-password',
            'new_password' => 'new-password-123',
            'new_password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(400)->assertJsonPath('message', 'Current password is incorrect');
    }

    public function test_change_password_updates_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/change-password', [
            'current_password' => 'old-password',
            'new_password' => 'new-password-123',
            'new_password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(200)->assertJsonPath('message', 'Password changed successfully');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }
}
