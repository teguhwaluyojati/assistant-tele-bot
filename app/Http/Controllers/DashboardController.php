<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramUser;
use App\Models\User;
use App\Models\LoginModel;
use App\Models\TelegramUserCommand;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Traits\ApiResponse;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\StocksImport;
use App\Exports\UserCommandsExport;


class DashboardController extends Controller
{
    protected $model_login;
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

    private function canManageTargetUser(TelegramUser $actor, TelegramUser $target): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return (int) $target->level === 2;
    }

    private function configuredAdminChatIds(): array
    {
        $adminIdsRaw = (string) env('TELEGRAM_ADMIN_ID', '');
        if ($adminIdsRaw === '') {
            return [];
        }

        return collect(explode(',', $adminIdsRaw))
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '')
            ->values()
            ->all();
    }

    private function canBootstrapSuperAdmin(?TelegramUser $actor, TelegramUser $target, int $newLevel): bool
    {
        if (!$actor || !$actor->isAdmin()) {
            return false;
        }

        if ($newLevel !== 0) {
            return false;
        }

        if (TelegramUser::where('level', 0)->exists()) {
            return false;
        }

        return in_array((string) $target->user_id, $this->configuredAdminChatIds(), true);
    }

    public function getUsers()
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        $users = TelegramUser::with('webUser:id,telegram_user_id,name,avatar')
            ->latest('last_interaction_at')
            ->paginate(15);

        return response()->json($users);
    }

    public function storeUser(Request $request)
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'telegram_user_id' => ['required', 'integer', 'min:1'],
                'telegram_username' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:6', 'confirmed'],
            ]);

            $telegramNumericId = (int) $validated['telegram_user_id'];
            $normalizedUsername = ltrim(trim($validated['telegram_username']), '@');

            $usernameOwner = TelegramUser::where('username', $normalizedUsername)
                ->where('user_id', '!=', $telegramNumericId)
                ->first();
            if ($usernameOwner) {
                return $this->errorResponse('Telegram username is already used by another Telegram ID.', 409);
            }

            $telegramUser = TelegramUser::where('user_id', $telegramNumericId)->first();
            if (!$telegramUser) {
                $telegramUser = TelegramUser::create([
                    'user_id' => $telegramNumericId,
                    'username' => $normalizedUsername,
                    'first_name' => null,
                    'last_name' => null,
                    'last_interaction_at' => now(),
                    'level' => 2,
                ]);
            } else {
                $telegramUser->username = $normalizedUsername;
                if (is_null($telegramUser->level)) {
                    $telegramUser->level = 2;
                }
                if (is_null($telegramUser->last_interaction_at)) {
                    $telegramUser->last_interaction_at = now();
                }
                $telegramUser->save();
            }

            $currentTelegramUser = auth()->user()?->telegramUser;
            if ($currentTelegramUser && !$this->canManageTargetUser($currentTelegramUser, $telegramUser)) {
                return $this->errorResponse('Admin can only create web users for member Telegram accounts.', 403);
            }

            $isTelegramAlreadyUsed = User::where('telegram_user_id', $telegramUser->id)->exists();
            if ($isTelegramAlreadyUsed) {
                return $this->errorResponse('This Telegram account is already linked to another web account.', 409);
            }

            $newUser = User::create([
                'name' => trim($validated['name']),
                'email' => strtolower(trim($validated['email'])),
                'password' => Hash::make($validated['password']),
                'telegram_user_id' => $telegramUser->id,
            ]);

            activity()
                ->causedBy(auth()->user())
                ->performedOn($newUser)
                ->withProperties([
                    'target_email' => $newUser->email,
                    'telegram_numeric_id' => $telegramNumericId,
                    'telegram_user_id' => $telegramUser->id,
                    'telegram_username' => $normalizedUsername,
                ])
                ->log('create_user_backdoor');

            return $this->successResponse($newUser->load('telegramUser:id,user_id,username,first_name,last_name,level'), 'User created successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Error creating user: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while creating user.', 500);
        }
    }

    public function lastLogin()
    {
        try {
            $currentUser = auth()->user();
            if (!$currentUser) {
                return $this->errorResponse('Unauthorized.', 401);
            }

            $data = (new LoginModel())->lastLogin();
            if (!$data) {
                return $this->errorResponse('No login history found', 404);
            }

            return $this->successResponse($data, 'Login history retrieved successfully.');
        } catch (\Exception $e) {
            Log::error('Error retrieving login history: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving login history.', 500);
        }
    }

    public function upload(Request $request)
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls'
        ]);

        try {
            Excel::import(new StocksImport, $request->file('file'));
            
            return $this->successResponse(null, 'Stock data imported successfully.');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Import failed: ' . $e->getMessage(), 500);
        }
    }

    public function getUserDetail($userId)
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        try {
            $currentTelegramUser = auth()->user()?->telegramUser;
            $user = TelegramUser::with('webUser:id,telegram_user_id,name,avatar')
                ->where('user_id', $userId)
                ->firstOrFail();

            if ($currentTelegramUser && !$this->canManageTargetUser($currentTelegramUser, $user)) {
                return $this->errorResponse('Admin can only view member accounts.', 403);
            }
            
            $commands = TelegramUserCommand::where('user_id', $userId)
                ->latest()
                ->limit(50)
                ->get();

            return $this->successResponse([
                'user' => $user,
                'commands' => $commands
            ], 'User detail retrieved successfully.');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving user detail: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving user detail.', 500);
        }
    }

    public function getMyCommands()
    {
        try {
            $currentUser = auth()->user();
            if (!$currentUser) {
                return $this->errorResponse('Unauthorized.', 401);
            }

            $telegramUser = $currentUser->telegramUser;
            if (!$telegramUser) {
                return $this->errorResponse('User not linked to Telegram account.', 403);
            }

            $commands = TelegramUserCommand::where('user_id', $telegramUser->user_id)
                ->latest()
                ->limit(10)
                ->get();

            return $this->successResponse($commands, 'User commands retrieved successfully.');
        } catch (\Exception $e) {
            Log::error('Error retrieving user commands: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving commands.', 500);
        }
    }

    public function getRecentCommands()
    {
        try {
            $currentUser = auth()->user();
            if (!$currentUser) {
                return $this->errorResponse('Unauthorized.', 401);
            }

            $telegramUser = $currentUser->telegramUser;
            if (!$telegramUser) {
                return $this->errorResponse('User not linked to Telegram account.', 403);
            }

            $query = TelegramUserCommand::query();

            if ($telegramUser->isAdmin()) {
                $commands = $query
                    ->leftJoin('telegram_users', 'telegram_user_commands.user_id', '=', 'telegram_users.user_id')
                    ->select([
                        'telegram_user_commands.id',
                        'telegram_user_commands.command',
                        'telegram_user_commands.user_id',
                        'telegram_user_commands.created_at',
                        'telegram_users.username',
                        'telegram_users.first_name',
                        'telegram_users.last_name',
                    ])
                    ->latest('telegram_user_commands.created_at')
                    ->limit(10)
                    ->get();
            } else {
                $commands = $query
                    ->where('user_id', $telegramUser->user_id)
                    ->latest()
                    ->limit(10)
                    ->get();
            }

            return $this->successResponse($commands, 'Recent commands retrieved successfully.');
        } catch (\Exception $e) {
            Log::error('Error retrieving recent commands: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving commands.', 500);
        }
    }

    public function getUserCommands(Request $request)
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        try {
            $validated = $request->validate([
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'search' => ['nullable', 'string', 'max:255'],
                'start_date' => ['nullable', 'date_format:Y-m-d'],
                'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            ]);

            $query = TelegramUserCommand::query()
                ->leftJoin('telegram_users', 'telegram_user_commands.user_id', '=', 'telegram_users.user_id')
                ->select([
                    'telegram_user_commands.id',
                    'telegram_user_commands.command',
                    'telegram_user_commands.user_id',
                    'telegram_user_commands.created_at',
                    'telegram_users.username',
                    'telegram_users.first_name',
                    'telegram_users.last_name',
                ]);

            if (!empty($validated['search'])) {
                $keyword = '%' . trim($validated['search']) . '%';
                $query->where(function ($builder) use ($keyword) {
                    $builder
                        ->where('telegram_user_commands.command', 'like', $keyword)
                        ->orWhere('telegram_users.username', 'like', $keyword)
                        ->orWhere('telegram_users.first_name', 'like', $keyword)
                        ->orWhere('telegram_users.last_name', 'like', $keyword)
                        ->orWhere('telegram_user_commands.user_id', 'like', $keyword);
                });
            }

            if (!empty($validated['start_date'])) {
                $query->whereDate('telegram_user_commands.created_at', '>=', $validated['start_date']);
            }

            if (!empty($validated['end_date'])) {
                $query->whereDate('telegram_user_commands.created_at', '<=', $validated['end_date']);
            }

            $perPage = $validated['per_page'] ?? 15;
            $commands = $query
                ->latest('telegram_user_commands.created_at')
                ->paginate($perPage);

            return $this->successResponse($commands, 'User command list retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Error retrieving user command list: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving user commands.', 500);
        }
    }

    public function exportUserCommands(Request $request)
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        try {
            $validated = $request->validate([
                'search' => ['nullable', 'string', 'max:255'],
                'start_date' => ['nullable', 'date_format:Y-m-d'],
                'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            ]);

            $search = $validated['search'] ?? null;
            $startDate = $validated['start_date'] ?? null;
            $endDate = $validated['end_date'] ?? null;
            $fileName = 'user-commands-' . now()->format('Y-m-d-H-i-s') . '.xlsx';

            activity()
                ->causedBy(auth()->user())
                ->withProperties([
                    'search' => $search,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'file' => $fileName,
                ])
                ->log('export_user_commands');

            return Excel::download(new UserCommandsExport($search, $startDate, $endDate), $fileName);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Error exporting user commands: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while exporting user commands.', 500);
        }
    }

    public function getRecentLogins()
    {
        try {
            $currentUser = auth()->user();
            if (!$currentUser) {
                return $this->errorResponse('Unauthorized.', 401);
            }

            $telegramUser = $currentUser->telegramUser;
            if (!$telegramUser) {
                return $this->errorResponse('User not linked to Telegram account.', 403);
            }

            $isAdmin = $telegramUser->isAdmin();
            $userEmail = $currentUser->email;

            // Use cached Eloquent model (60 second cache)
            $logins = LoginModel::getRecentLogins(
                limit: 10,
                isAdmin: $isAdmin,
                userEmail: $userEmail,
                cacheDuration: 60
            );

            return $this->successResponse($logins, 'Recent logins retrieved successfully.');
        } catch (\Exception $e) {
            Log::error('Error retrieving recent logins: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving logins.', 500);
        }
    }

    public function updateUserRole(Request $request, $userId)
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        try {
            $validated = $request->validate([
                'level' => 'required|integer|in:0,1,2',
            ]);

            $user = TelegramUser::where('user_id', $userId)->firstOrFail();
            $currentUser = auth()->user();
            $currentTelegramUser = $currentUser?->telegramUser;
            $actorIsSuperAdmin = $currentTelegramUser?->isSuperAdmin() ?? false;
            $targetIsSuperAdmin = $user->isSuperAdmin();
            $newLevel = (int) $validated['level'];
            $allowBootstrapPromotion = $this->canBootstrapSuperAdmin($currentTelegramUser, $user, $newLevel);

            if ($currentTelegramUser && $currentTelegramUser->user_id === $user->user_id) {
                return $this->errorResponse('You cannot change your own role.', 403);
            }

            if ($currentTelegramUser && !$this->canManageTargetUser($currentTelegramUser, $user) && !$allowBootstrapPromotion) {
                return $this->errorResponse('Admin can only manage member accounts.', 403);
            }

            if (!$actorIsSuperAdmin && ($targetIsSuperAdmin || $newLevel === 0) && !$allowBootstrapPromotion) {
                return $this->errorResponse('Only superadmin can manage superadmin role.', 403);
            }

            if ($targetIsSuperAdmin && $newLevel !== 0) {
                $superAdminCount = TelegramUser::where('level', 0)->count();
                if ($superAdminCount <= 1) {
                    return $this->errorResponse('Cannot change role of the last superadmin.', 403);
                }
            }

            $user->level = $newLevel;
            $user->save();

            activity()
                ->causedBy($currentUser)
                ->performedOn($user)
                ->withProperties([
                    'target_user_id' => $user->user_id,
                    'level' => $newLevel,
                ])
                ->log('update_role');

            return $this->successResponse($user, 'User role updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Error updating user role: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while updating user role.', 500);
        }
    }

    public function deleteUser($userId)
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        try {
            $currentUser = auth()->user();
            $currentTelegramUser = $currentUser?->telegramUser;
            $targetUser = TelegramUser::where('user_id', $userId)->firstOrFail();
            $actorIsSuperAdmin = $currentTelegramUser?->isSuperAdmin() ?? false;
            $targetIsSuperAdmin = $targetUser->isSuperAdmin();

            if ($currentTelegramUser && $currentTelegramUser->user_id === $targetUser->user_id) {
                return $this->errorResponse('You cannot delete your own account.', 403);
            }

            if ($currentTelegramUser && !$this->canManageTargetUser($currentTelegramUser, $targetUser)) {
                return $this->errorResponse('Admin can only manage member accounts.', 403);
            }

            if (!$actorIsSuperAdmin && $targetIsSuperAdmin) {
                return $this->errorResponse('Only superadmin can delete superadmin.', 403);
            }

            if ($targetIsSuperAdmin) {
                $superAdminCount = TelegramUser::where('level', 0)->count();
                if ($superAdminCount <= 1) {
                    return $this->errorResponse('Cannot delete the last superadmin.', 403);
                }
            }

            $deletedSummary = DB::transaction(function () use ($targetUser) {
                $deletedCommands = TelegramUserCommand::where('user_id', $targetUser->user_id)->delete();
                $deletedPoopTrackers = DB::table('poop_tracker')->where('user_id', $targetUser->user_id)->delete();
                $targetUser->delete();

                return [
                    'commands' => $deletedCommands,
                    'poop_tracker' => $deletedPoopTrackers,
                ];
            });

            activity()
                ->causedBy($currentUser)
                ->withProperties([
                    'target_user_id' => $userId,
                    'deleted_related' => $deletedSummary,
                ])
                ->log('delete_user');

            return $this->successResponse([
                'user_id' => (int) $userId,
                'deleted_related' => $deletedSummary,
            ], 'User deleted successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('User not found.', 404);
        } catch (\Exception $e) {
            Log::error('Error deleting user: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while deleting user.', 500);
        }
    }

}
