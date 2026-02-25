<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use App\Models\User;
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
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $perPage = $validated['per_page'] ?? 20;

        $query = Activity::with('causer');

        if (!empty($validated['start_date'])) {
            $query->whereDate('created_at', '>=', $validated['start_date']);
        }

        if (!empty($validated['end_date'])) {
            $query->whereDate('created_at', '<=', $validated['end_date']);
        }

        if (!empty($validated['search'])) {
            $keyword = trim($validated['search']);
            $query->where(function ($builder) use ($keyword) {
                $builder
                    ->where('description', 'like', "%{$keyword}%")
                    ->orWhere('subject_type', 'like', "%{$keyword}%")
                    ->orWhere('log_name', 'like', "%{$keyword}%")
                    ->orWhere('event', 'like', "%{$keyword}%")
                    ->orWhere('properties', 'like', "%{$keyword}%")
                    ->orWhereHasMorph('causer', [User::class], function ($causerQuery) use ($keyword) {
                        $causerQuery
                            ->where('name', 'like', "%{$keyword}%")
                            ->orWhere('email', 'like', "%{$keyword}%");
                    });
            });
        }

        $logs = $query->latest()->paginate($perPage);

        return $this->successResponse($logs, 'Audit logs retrieved successfully.');
    }

    public function export(Request $request)
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Activity::with('causer');

        if (!empty($validated['start_date'])) {
            $query->whereDate('created_at', '>=', $validated['start_date']);
        }

        if (!empty($validated['end_date'])) {
            $query->whereDate('created_at', '<=', $validated['end_date']);
        }

        if (!empty($validated['search'])) {
            $keyword = trim($validated['search']);
            $query->where(function ($builder) use ($keyword) {
                $builder
                    ->where('description', 'like', "%{$keyword}%")
                    ->orWhere('subject_type', 'like', "%{$keyword}%")
                    ->orWhere('log_name', 'like', "%{$keyword}%")
                    ->orWhere('event', 'like', "%{$keyword}%")
                    ->orWhere('properties', 'like', "%{$keyword}%")
                    ->orWhereHasMorph('causer', [User::class], function ($causerQuery) use ($keyword) {
                        $causerQuery
                            ->where('name', 'like', "%{$keyword}%")
                            ->orWhere('email', 'like', "%{$keyword}%");
                    });
            });
        }

        $logs = $query->latest()->get();
        $filename = 'audit-logs-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Date', 'Action', 'User', 'Subject']);

            foreach ($logs as $log) {
                $causer = $log->causer;
                $causerLabel = $causer?->email ?: ($causer?->name ?: 'system');
                $subject = $log->subject_type ? class_basename($log->subject_type) : '-';

                fputcsv($handle, [
                    optional($log->created_at)->format('Y-m-d H:i:s'),
                    $log->description,
                    $causerLabel,
                    $subject,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
