# Assistant Tele Bot

Assistant Tele Bot is a Laravel + Vue application for managing financial transactions and user activity across two main channels:

- Web Dashboard (login, profile, audit logs, transaction analytics)
- Telegram Bot (money tracker, command center, and daily utilities)

The project is currently running with Sanctum-based APIs, activity logging, and automated tests (Feature + Unit).

## Key Features

### Web Dashboard

- Login/logout authentication using `auth_token` cookie
- Two-step registration flow (`/api/register/initiate` + `/api/register/verify`)
- Profile management (update profile and change password)
- Transaction monitoring:
  - list/filter/sort transactions
  - income vs expense summary
  - daily chart
  - edit, delete, and bulk delete
  - Excel export (`.xlsx`)
- User management (admin only):
  - list Telegram users
  - user detail + command history
  - update user role
- Audit logs (admin only) with pagination

### Telegram Bot

- Webhook endpoint for incoming Telegram updates
- Telegram user command logging to database
- Interactive menu and stateful flow
- Finance features: quick transaction input (`+/-`), summaries, delete/edit
- Additional features: stock analysis, poop tracker, AI chat mode, and other utilities

## Technology Stack

### Backend

- PHP 8.2
- Laravel 10
- Laravel Sanctum (API auth)
- Spatie Activitylog (audit trail)
- Maatwebsite Excel (export/import)
- irazasyed/telegram-bot-sdk (Telegram integration)

### Frontend

- Vue 3
- Pinia
- Vue Router
- Axios
- Tailwind CSS
- Vite
- Chart.js

### Database & Infrastructure

- PostgreSQL
- Standard Laravel queue/cache/session components

## Architecture Summary

- `routes/web.php` for web pages (Blade mount points + signed avatar)
- `routes/api.php` for app APIs and Telegram webhook
- Main controllers:
  - `DashboardController` for analytics, user management, and transactions
  - `LoginController`, `RegisterController`, `ProfileController`
  - `AuditLogController`
  - `TelegramController` (bot flow orchestrator)

## API Highlights

This README intentionally avoids listing every endpoint to keep it concise.

Main API groups in this project:

- Auth & Registration
- Profile Management
- Dashboard Analytics
- User & Role Management
- Transaction CRUD + Export
- Audit Logs
- Telegram Webhook

For the complete endpoint list, see `routes/api.php`.

## Local Setup

1. Clone the project
2. Install backend and frontend dependencies

```bash
composer install
npm install
```

3. Prepare environment

```bash
cp .env.example .env
php artisan key:generate
```

4. Minimum `.env` configuration:

- `APP_URL`
- `DB_CONNECTION=pgsql`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_WEBHOOK_URL` (optional for webhook deployment)

5. Run migrations and seeders

```bash
php artisan migrate --seed
```

6. Start the application

```bash
npm run dev
php artisan serve
```

## Testing & Quality

Run all tests:

```bash
php artisan test
```

Run test coverage:

```bash
php artisan test --coverage --min=20
```

### Quality Snapshot (local, Feb 25, 2026)

- Total tests: **55 passed**
- Assertions: **168**
- Coverage suite duration: **20.96s**
- Total line coverage: **24.5%**

High-coverage areas:

- `Http/Controllers/UserController`: 100%
- `Http/Controllers/AuditLogController`: 95%
- `Http/Controllers/DashboardController`: 68%
- `Exports/TransactionsExport`: 96.7%

## Performance Notes

- Frequently used dashboard endpoints already use pagination (e.g. audit logs, users, transactions).
- Recent login data uses short-window caching to reduce repeated queries.
- Transaction export uses query-based retrieval + streamed response via Laravel Excel.

## Short Roadmap

- Increase backend coverage progressively for Telegram and scheduler-related areas.
- Improve observability for Telegram webhook flow (alerting + structured logs).
- Harden validation and authorization in areas that are not fully covered yet.

## License

This project follows the license model used by the Laravel/composer dependencies (MIT).
