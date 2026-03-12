# Transactions Architectures

This document describes the current backend architecture for transaction features after refactor phase 2.1–2.7.

## Goals

- Keep controllers thin and focused on HTTP orchestration.
- Centralize business logic into dedicated services and actions.
- Preserve existing API contracts while improving maintainability.

## API Surface

Transaction endpoints are defined in `routes/api.php` and handled by `TransactionController`:

- `GET /api/transactions`
- `GET /api/transactions/summary`
- `GET /api/transactions/daily-chart`
- `POST /api/transactions`
- `PUT /api/transactions/{id}`
- `DELETE /api/transactions/{id}`
- `POST /api/transactions/bulk-delete`
- `GET /api/transactions/export`

## Layering Overview

### 1) HTTP Layer

- `app/Http/Controllers/TransactionController.php`
  - Receives request
  - Delegates validation to FormRequest classes
  - Calls access guards, query services, or action classes
  - Returns API responses

- `app/Http/Requests/ApiFormRequest.php`
  - Standardizes validation failure response format:
    - `success: false`
    - `message: Validation failed.`
    - `errors: { ... }`

- `app/Http/Requests/Transactions/*.php`
  - Endpoint-specific validation rules

### 2) Query/Read Services

- `app/Services/TransactionQueryService.php`
  - Pagination/filter/sort query builder
  - Date range resolver
  - Summary aggregation
  - Daily chart dataset builder

- `app/Services/TransactionAccessGuardService.php`
  - Access checks and scoped chat-id resolution for:
    - list
    - summary
    - daily chart
    - export

### 3) Write/Workflow Services

- `app/Services/TransactionCategoryService.php`
  - Manual category priority and auto-category fallback

- `app/Services/TransactionPersistenceService.php`
  - Handles save with duplicate PK sequence recovery

- `app/Services/TransactionActivityService.php`
  - Centralized activity logging payloads and events

- `app/Services/TransactionBulkDeleteService.php`
  - Resolves authorized records and executes bulk deletion

- `app/Services/TransactionExportService.php`
  - Builds export context and streams Excel output

### 4) Action Layer (Use-Case Orchestration)

Located at `app/Actions/Transactions/`:

- `CreateTransactionAction`
- `UpdateTransactionAction`
- `DeleteTransactionAction`
- `BulkDeleteTransactionsAction`
- `ExportTransactionsAction`

Each action orchestrates one use-case by composing services. This keeps controller methods concise and isolates behavior per command.

## Request Flow (Write Example)

`POST /api/transactions` flow:

1. `StoreTransactionRequest` validates payload.
2. `TransactionController@storeTransaction` calls `CreateTransactionAction`.
3. `CreateTransactionAction`:
   - checks telegram linkage
   - resolves category (`TransactionCategoryService`)
   - persists safely (`TransactionPersistenceService`)
   - logs activity (`TransactionActivityService`)
4. Controller returns unified success response.

## Request Flow (Read Example)

`GET /api/transactions/daily-chart` flow:

1. `DailyChartRequest` validates query params.
2. Controller resolves range (`TransactionQueryService`).
3. Controller resolves scope/access (`TransactionAccessGuardService`).
4. Query service returns chart data.
5. Controller returns unified success response.

## Testing Strategy

### Unit Tests (service/action focused)

- `tests/Unit/TransactionCategoryServiceTest.php`
- `tests/Unit/TransactionAuthorizationServiceTest.php`
- `tests/Unit/TransactionQueryServiceTest.php`
- `tests/Unit/TransactionExportServiceTest.php`
- `tests/Unit/TransactionPersistenceServiceTest.php`
- `tests/Unit/TransactionBulkDeleteServiceTest.php`
- `tests/Unit/TransactionAccessGuardServiceTest.php`
- `tests/Unit/TransactionActionsTest.php`

### Feature Regression

- `tests/Feature/WebFocusedApiEndpointsTest.php`
- `tests/Feature/DashboardAnalyticsTest.php`

These suites ensure API behavior remains stable while internals are refactored.

## Extension Guidelines

- New transaction use-case should prefer:
  1. dedicated FormRequest for validation,
  2. dedicated Action class for orchestration,
  3. reusable Service for shared business rules.
- Keep controller changes minimal and response contract-compatible.
