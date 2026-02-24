<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginLogoutTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_rejects_wrong_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_logout_returns_success_and_forgets_auth_cookie(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token', ['*'], now()->addHours(2));
        $plainTextToken = $token->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $plainTextToken)
            ->withCookie('auth_token', $plainTextToken)
            ->postJson('/api/logout');

        $response
            ->assertStatus(200)
            ->assertJsonPath('message', 'Logout berhasil!')
            ->assertCookieExpired('auth_token');
    }
}
