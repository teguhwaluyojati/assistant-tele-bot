<?php

namespace Tests\Unit;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\UseAuthTokenFromCookie;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class MiddlewareBehaviorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_use_auth_token_from_cookie_sets_authorization_header_when_missing(): void
    {
        $middleware = new UseAuthTokenFromCookie();
        $request = Request::create('/api/test', 'GET', [], ['auth_token' => 'cookie-token']);

        $middleware->handle($request, function (Request $request) {
            return response()->json([
                'authorization' => $request->header('Authorization'),
            ]);
        });

        $response = $middleware->handle($request, function (Request $request) {
            return response()->json([
                'authorization' => $request->header('Authorization'),
            ]);
        });

        $this->assertSame('Bearer cookie-token', $response->getData(true)['authorization']);
    }

    public function test_use_auth_token_from_cookie_keeps_existing_bearer_header(): void
    {
        $middleware = new UseAuthTokenFromCookie();
        $request = Request::create('/api/test', 'GET', [], ['auth_token' => 'cookie-token']);
        $request->headers->set('Authorization', 'Bearer existing-token');

        $response = $middleware->handle($request, function (Request $request) {
            return response()->json([
                'authorization' => $request->header('Authorization'),
            ]);
        });

        $this->assertSame('Bearer existing-token', $response->getData(true)['authorization']);
    }

    public function test_redirect_if_authenticated_redirects_logged_in_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $middleware = new RedirectIfAuthenticated();
        $request = Request::create('/login', 'GET');

        $response = $middleware->handle($request, fn () => new Response('next'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString(RouteServiceProvider::HOME, (string) $response->headers->get('Location'));
    }

    public function test_redirect_if_authenticated_allows_guest_request(): void
    {
        auth()->logout();

        $middleware = new RedirectIfAuthenticated();
        $request = Request::create('/login', 'GET');

        $response = $middleware->handle($request, fn () => new Response('next'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('next', $response->getContent());
    }

    public function test_authenticate_redirect_to_returns_null_for_json_request(): void
    {
        $middleware = new class(app('auth')) extends Authenticate {
            public function exposeRedirectTo(Request $request): ?string
            {
                return $this->redirectTo($request);
            }
        };

        $request = Request::create('/api/protected', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertNull($middleware->exposeRedirectTo($request));
    }

    public function test_authenticate_redirect_to_returns_root_for_web_request(): void
    {
        $middleware = new class(app('auth')) extends Authenticate {
            public function exposeRedirectTo(Request $request): ?string
            {
                return $this->redirectTo($request);
            }
        };

        $request = Request::create('/dashboard', 'GET');

        $this->assertSame('/', $middleware->exposeRedirectTo($request));
    }
}
