# Feature: LoanPro e-commerce challenge

## Objective
Build an enterprise-grade e-commerce application: product CRUD, CSV import, product search, and purchase with a fake payment. Each feature has a UI, and the whole application runs with Docker.

## Problem
The challenge evaluates judgment more than completion. The sample CSV contains deliberate traps: invalid prices, negative stock, blank names, XSS and SQL injection payloads, quoting and unicode edge cases, blank rows, and duplicate SKUs. Concurrency, security, and data integrity must be handled explicitly.

## Why
Show deliberate decisions with documented tradeoffs: what was asked, what was assumed, and what was left out on purpose.

## Scope
- Laravel API backend (Screaming + Hexagonal modules: Catalog, Import, Ordering, Identity).
- React + Vite SPA frontend (features + atomic design, container/presentational).
- PostgreSQL. Docker Compose with Nginx as the single entry point.
- README with decisions, approach, alternatives, run instructions, and the CSV download date (2026-10-15).

## Constraints
- Strict TDD (Red → Green → Refactor). Any untestable behavior is declared explicitly.
- Remove AI-generated comments from the code (challenge rule).
- Artifacts are written in English.
- Commits use Conventional Commits with no AI attribution (user rule).
- Push, PR creation, and merge are the user's decisions.

## Agreed decisions (2026-10-15)
- **SKU**: unique, one per sellable variant. A soft-deleted product's SKU stays reserved forever.
- **Import**: partial, with a per-row report (created / updated / unchanged / failed, plus new categories). An existing SKU whose normalized name matches is updated; if the name differs, the row fails; identical data is reported as unchanged. Stock values are absolute. A SKU repeated in the same file with differing data has all of its rows rejected. The import cannot rename products.
- **Validation**: name, sku, category, price, and stock are required. Price is a decimal ≥ 0 with at most 2 decimals (`$` and `free` are rejected; 0 is valid). Stock is an integer ≥ 0. Weight is optional and ≥ 0. Description is optional. The system does not guess; it reports.
- **Categories**: stored in a table. The import finds or creates them using a normalized match and reports the new ones.
- **Auth**: Sanctum SPA mode (cookie + CSRF). The admin role (created by seeder only) manages the CRUD and the import. The buyer role self-registers and must log in to purchase ("My orders"). Demo users are seeded.
- **Purchase**: the cart lives in the frontend (localStorage) and does not reserve stock. Checkout is atomic: `SELECT ... FOR UPDATE`, current DB prices, fake payment, stock decrement, and order prices frozen at purchase time. A shortage on any item fails the whole order.
- **Payment**: a PaymentGateway port with a FakePaymentGateway adapter. Card 4242… approves and 4000…0002 declines. Only the brand and last 4 digits are stored. Each checkout sends an Idempotency-Key.
- **Search**: ILIKE + pg_trgm over name, sku, and description. Filters: category, price range, in stock. Sorting by name or price. Server-side pagination.
- **Deletion**: logical deletion everywhere. Orders are never deleted.
- **Docker**: db (Postgres), api (PHP-FPM; runs migrations and seeders on boot), and web (Nginx serving the SPA and proxying /api and /sanctum). Single origin at localhost:8080.
- **Frontend libraries**: TanStack Query, React Router, React Hook Form + Zod, Zustand persist, Tailwind 4 + shadcn/ui (in shared/ui).
- **Tests**: Pest unit + feature (against Postgres), Vitest + RTL + MSW, Larastan + Pint, ESLint + TypeScript strict, GitHub Actions. Concurrency gets a two-process integration test or a declared gap. Playwright is optional.

## Environment (observed)
PHP 8.4.25, Composer 2.10.2, Node 20.20.2, npm 11.14.1, Docker 29.4.1, Compose v5.1.3, git 2.54.0. The project directory is empty and is not a git repository yet.

## Delivery
- Forecast: about 5,700 authored changed lines, well above the 400-line slice budget.
- Strategy: ask-on-risk. The chain strategy is **stacked-to-main** (chosen by the user on 2026-10-15). Each slice branch starts from the previous one, and the PRs merge into `main` in order.
- Base: `main` holds an empty root commit (`chore: initial commit`) so that the first PR has a base.
- Planned slices (branch → tasks):
  1. `feat/01-bootstrap` → T01, T02, T03
  2. `feat/02-catalog-domain` → T04, T05
  3. `feat/03-identity` → T06
  4. `feat/04-catalog-api` → T07, T08
  5. `feat/05-import` → T09
  6. `feat/06-ordering` → T10, T11
  7. `feat/07-frontend-foundation` → T12, T13
  8. `feat/08-store-checkout` → T14, T15
  9. `feat/09-admin-ui` → T16, T17
  10. `feat/10-ci-docs` → T18, T19 (T20 optional)
- Framework-generated scaffolding (Laravel and Vite skeletons, lock files) does not count toward the line budget.

## Tasks
Route: D = delegated writer (2+ non-trivial files); I = inline.

- [ ] T01 Repository bootstrap: git init, .gitignore, sample CSV in `docs/sample-data/`, README skeleton with the CSV download date. Route: I. Check: files present.
- [ ] T02 Backend scaffold: Laravel API-only, Pest, Larastan, Pint, Postgres config, PSR-4 `src/`, health endpoint test. Route: D. Check: Pest green, Larastan clean.
- [ ] T03 Docker Compose: db + test database, api, web (Nginx proxy); migrations and seeders on boot. Route: D. Check: `docker compose up` serves the health endpoint through localhost:8080.
- [ ] T04 Catalog domain: Sku, Money, Stock value objects, Product entity, validation rules. Route: D. Check: Pest unit tests green.
- [ ] T05 Catalog persistence: products/categories migrations (unique sku that includes deleted rows, soft deletes, pg_trgm indexes), Eloquent repository and mapping. Route: D. Check: Pest feature tests against Postgres.
- [ ] T06 Identity: Sanctum SPA, register/login/logout/me, admin and buyer roles, policies, demo seeders. Route: D. Check: feature tests for auth and role access.
- [ ] T07 Catalog API: admin-only CRUD, category find-or-create, soft delete, reserved SKU error. Route: D. Check: feature tests.
- [ ] T08 Search API: free text, filters, sorting, pagination. Route: D. Check: feature tests.
- [ ] T09 Import: CSV reader, row validation, in-file duplicate detection, name-guarded upsert, per-row report endpoint. Route: D. Check: unit + feature tests, including the sample CSV traps.
- [ ] T10 Ordering: orders/order_items with snapshots, atomic Checkout use case, PaymentGateway port + fake adapter, idempotency, My orders. Route: D. Check: unit + feature tests.
- [ ] T11 Concurrency integration test (two processes, last unit) or a declared gap. Route: D. Check: the test is stable, or the gap is documented.
- [ ] T12 Frontend scaffold: Vite + React + TypeScript strict, Tailwind 4, shadcn in shared/ui, Router, Query, Vitest + RTL + MSW, Vite proxy, web Docker build. Route: D. Check: Vitest green, ESLint/tsc clean.
- [ ] T13 Frontend auth: login, register, role-protected routes, Store and Admin layouts. Route: D. Check: Vitest + MSW.
- [ ] T14 Frontend store: search with filters and pagination, product detail, "Out of stock" state, cart (Zustand persist). Route: D. Check: Vitest + MSW.
- [ ] T15 Frontend checkout and My orders: card form, Idempotency-Key, success and error toasts. Route: D. Check: Vitest + MSW.
- [ ] T16 Frontend admin: product table, create/edit form with category combobox, delete confirmation modal. Route: D. Check: Vitest + MSW.
- [ ] T17 Frontend import: CSV upload and per-row report. Route: D. Check: Vitest + MSW.
- [ ] T18 CI: GitHub Actions for backend and frontend checks. Route: I. Check: workflow file validated.
- [ ] T19 README: decisions, approach, alternatives, run instructions, known gaps, extensions, AI usage note. Route: D. Check: structural readback.
- [ ] T20 (Optional) Playwright end-to-end happy path. Route: D. Check: E2E green.

## Acceptance criteria
- `docker compose up` starts the whole application at localhost:8080 with the demo users seeded.
- Importing the sample CSV produces the expected per-row report for every trap.
- Two concurrent checkouts for the last unit never oversell.
- All test suites and static checks pass locally and in CI.

## Progress
- 2026-10-15: Decision round closed. Feature document created. Next: choose the chain strategy, then T01.

## Next step
The user chooses the chain strategy (stacked-to-main or feature-branch-chain). Then start T01.
