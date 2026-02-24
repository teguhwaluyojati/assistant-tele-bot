<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WebRoutesTest extends TestCase
{
    public function test_public_web_pages_return_success(): void
    {
        $this->get('/')->assertStatus(200);
        $this->get('/login')->assertStatus(200);
        $this->get('/register')->assertStatus(200);
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/audit-logs')->assertStatus(200);
        $this->get('/style')->assertStatus(200);
        $this->get('/profile')->assertStatus(200);
    }

    public function test_avatar_route_rejects_unsigned_request(): void
    {
        $this->get('/avatar/sample.jpg')->assertStatus(403);
    }

    public function test_avatar_route_returns_file_for_valid_signed_url(): void
    {
        $directory = storage_path('app/avatars');
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $filename = 'test-avatar.jpg';
        $filePath = $directory . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($filePath, 'avatar-content');

        $url = URL::temporarySignedRoute('avatar.show', now()->addMinutes(5), [
            'filename' => $filename,
        ]);

        $this->get($url)->assertStatus(200);

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    public function test_avatar_route_returns_404_for_missing_file_with_valid_signature(): void
    {
        $url = URL::temporarySignedRoute('avatar.show', now()->addMinutes(5), [
            'filename' => 'not-found-avatar.jpg',
        ]);

        $this->get($url)->assertStatus(404);
    }
}
