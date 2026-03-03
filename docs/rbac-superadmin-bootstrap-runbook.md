# RBAC Superadmin Bootstrap Runbook

This runbook describes the safe procedure to bootstrap the **first** superadmin account.

## Purpose

Use this only when no superadmin exists yet (fresh environment or accidental lockout scenario).

The command uses built-in safety guardrails:

- fails if `TELEGRAM_ADMIN_ID` is empty,
- fails if a superadmin already exists,
- only allows IDs listed in `TELEGRAM_ADMIN_ID`,
- requires the target Telegram user to already exist in `telegram_users`.

## Prerequisites

- Backend deployed and reachable.
- Environment variable `TELEGRAM_ADMIN_ID` set (single ID or comma-separated IDs).
- Target Telegram account has opened the bot and sent `/start` at least once.

## Bootstrap Steps

1. Ensure there is no superadmin yet.

```bash
php artisan tinker
>>> \App\Models\TelegramUser::where('level', 0)->count();
```

2. Verify `TELEGRAM_ADMIN_ID` includes the intended chat ID.

3. Run bootstrap command:

```bash
php artisan app:bootstrap-superadmin <chat_id>
```

Example:

```bash
php artisan app:bootstrap-superadmin 123456789
```

4. Validate result:

```bash
php artisan tinker
>>> \App\Models\TelegramUser::where('user_id', 123456789)->value('level');
```

Expected value: `0`.

## No-Argument Mode

If you run without argument:

```bash
php artisan app:bootstrap-superadmin
```

The command picks the first matching Telegram user from IDs in `TELEGRAM_ADMIN_ID`, prioritizing member-level candidate when available.

## Failure Cases and Meaning

- `TELEGRAM_ADMIN_ID is empty...`
  - Environment variable is missing; set it first.
- `A superadmin already exists...`
  - Command intentionally blocked to prevent accidental escalation.
- `Chat ID <id> is not listed in TELEGRAM_ADMIN_ID.`
  - Requested ID is outside the configured allowlist.
- `No Telegram user found...`
  - Target has not interacted with bot yet; ask user to send `/start`.

## Rollback / Recovery

If wrong account is promoted:

1. Login as current valid superadmin.
2. Promote the correct account to superadmin (level 0).
3. Downgrade incorrect account to intended level (`1` or `2`) via user role management endpoint/UI.
4. Keep at least one valid superadmin at all times.

## Operational Notes

- Never remove all superadmins.
- Keep `TELEGRAM_ADMIN_ID` restricted to trusted owner IDs only.
- Prefer explicit `<chat_id>` in production for deterministic behavior.
