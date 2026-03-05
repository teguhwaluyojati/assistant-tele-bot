# Transaction Power Features — 1 Week Sprint Plan

## Goal

Deliver high-impact transaction capabilities with controlled scope and strong safety:

1. Recurring transactions (MVP)
2. Category and tags (MVP)
3. Budget threshold alert (MVP)

## Constraints

- Keep UX minimal and aligned with existing dashboard style.
- Preserve current RBAC behavior (Superadmin/Admin/Member).
- Add tests incrementally per feature.
- Avoid broad refactors unrelated to transaction module.

---

## Scope (MVP)

### 1) Recurring Transactions

#### Functional
- Users can create recurring rules:
  - type: income/expense
  - amount
  - description
  - category (required)
  - frequency: daily/weekly/monthly
  - start_date
  - optional end_date
  - active flag
- Scheduler generates due transactions from active rules.
- Prevent duplicate generation for the same rule/date.

#### Data Model
- New table: `recurring_transactions`
  - `id`, `telegram_user_id` (FK to `telegram_users.id`)
  - `type`, `amount`, `description`
  - `category_id` (nullable at migration phase, required by validation)
  - `frequency`, `start_date`, `end_date`
  - `last_generated_at` (nullable)
  - `is_active` (bool)
  - timestamps

#### API
- `GET /api/transactions/recurring`
- `POST /api/transactions/recurring`
- `PUT /api/transactions/recurring/{id}`
- `DELETE /api/transactions/recurring/{id}`
- Owner/Admin/Superadmin access follows existing transaction ownership policy.

---

### 2) Category and Tags

#### Functional
- Add transaction categories (MVP fixed seed + optional custom later).
- Optional free-text tags (comma-separated) on transaction create/update.
- Filter transactions by category and tag.
- Show category on table/export.

#### Data Model
- New table: `transaction_categories`
  - `id`, `name`, `is_default`, timestamps
- Alter `transactions` table:
  - `category_id` nullable FK
  - `tags` nullable text/json

#### API Changes
- Extend existing transaction create/update payload:
  - `category_id`
  - `tags`
- Extend list filters:
  - `category_id`
  - `tag`

---

### 3) Budget Threshold Alerts

#### Functional
- User sets monthly budget by category.
- Trigger alert when usage reaches threshold (default 80% and 100%).
- Alert channel MVP:
  - web notification (existing toast flow)
  - optional Telegram message if channel available

#### Data Model
- New table: `category_budgets`
  - `id`, `telegram_user_id`, `category_id`
  - `month` (YYYY-MM)
  - `budget_amount`
  - `alerted_80_at` nullable
  - `alerted_100_at` nullable
  - timestamps

#### API
- `GET /api/transactions/budgets`
- `POST /api/transactions/budgets`
- `PUT /api/transactions/budgets/{id}`
- `DELETE /api/transactions/budgets/{id}`

---

## Delivery Plan (5 Working Days)

### Day 1 — Data Foundation
- Create migrations for:
  - `transaction_categories`
  - `recurring_transactions`
  - `category_budgets`
  - `transactions` alteration (`category_id`, `tags`)
- Add models and relationships.
- Seed default categories.
- Add migration tests/smoke.

### Day 2 — Recurring Transactions API + Scheduler
- Implement recurring CRUD endpoints.
- Add scheduler command to materialize due transactions.
- Add duplicate-guard logic.
- Add feature tests:
  - create/list/update/delete recurring
  - owner access constraints
  - generation happy path + duplicate prevention

### Day 3 — Category/Tag Integration
- Extend transaction create/update/list filters.
- Update export pipeline with category/tags columns.
- Add feature tests for category/tag filter and persistence.

### Day 4 — Budget Alerts
- Implement budget CRUD endpoints.
- Implement threshold computation and alert flags.
- Add server-side event/response flags for alert state.
- Add feature tests for 80% and 100% threshold behavior.

### Day 5 — UI + Hardening + QA
- Add minimal UI:
  - recurring management section
  - category selection + tag input
  - budget setup panel and threshold indicator
- Run targeted + full test suite.
- Write rollout and rollback notes.

---

## Testing Strategy (Minimum per feature)

For each endpoint/feature block:
- 1 happy path
- 1 permission failure
- 1 validation/error path

Additional required tests:
- recurring duplicate generation guard
- export includes category/tags
- budget threshold idempotency (alert once per threshold)

---

## Definition of Done

- Feature implemented per MVP scope.
- API endpoints documented and tested.
- No RBAC regression on existing modules.
- Targeted suites pass, then full `php artisan test` pass.
- Release notes updated with migration and rollback info.

---

## Out of Scope (Next Sprint)

- Advanced recurrence rules (e.g., custom cron, nth weekday)
- Multi-budget periods beyond monthly
- Rich tag management UI (autocomplete, colors)
- Predictive insights / anomaly detection
