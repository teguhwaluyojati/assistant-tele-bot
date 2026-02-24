<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    use ApiResponse;

    private function requireAdmin()
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $telegramUser = $currentUser->telegramUser;
        if (!$telegramUser) {
            return $this->errorResponse('User not linked to Telegram account.', 403);
        }

        if (!$telegramUser->isAdmin()) {
            return $this->errorResponse('Forbidden.', 403);
        }

        return null;
    }

    public function index(Request $request)
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 20;

        $logs = Activity::with('causer')
            ->latest()
            ->paginate($perPage);

        return $this->successResponse($logs, 'Audit logs retrieved successfully.');
    }
}
