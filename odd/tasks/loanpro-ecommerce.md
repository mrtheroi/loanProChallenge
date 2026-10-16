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

- [x] T01 Repository bootstrap: git init, .gitignore, sample CSV in `docs/sample-data/`, README skeleton with the CSV download date. Route: I. Check: files present.
  - Evidence: commit `3d033bd`. `cmp` confirmed the CSV is byte-identical to the downloaded file. TDD exception: passive files only, no runnable behavior.
  - Review: assessed `medium` (`.gitignore` counted as executable), `review_due=false` (`under_budget`, 219 lines). Stays pending in slice 1; the reviewed boundary is still `4b885bb`.
- [x] T02 Backend scaffold: Laravel API-only, Pest, Larastan, Pint, Postgres config, PSR-4 `src/`, health endpoint test. Route: D. Check: Pest green, Larastan clean.
  - Route: delegated writer (scaffold plus several config files and a test).
  - Stack: Laravel 13.36.0, Pest 4.7, Larastan 3.13 (PHPStan level 9, no baseline), Pint 1.27, namespace `Ecommerce\` → `src/`.
  - RED: `GET /api/health` returned 404. GREEN: the route was added; 1 test, 2 assertions.
  - Checks: `pest` passed (the parent re-ran it as a spot check); `phpstan analyse` reported 0 errors at level 9; `pint --test` passed.
  - The user set the PostgreSQL variables in `backend/.env.example` manually, because permission rules block agents from reading or editing `.env*` files. The user also deleted the Laravel Boost `AGENTS.md` and `CLAUDE.md` from the scaffold. `.atl/` (local skill registry) was added to `.gitignore`.
  - Evidence: commit `e74dcac`.
  - Review: assessed `high` (`review_due=true`, 12,604 lines). The first preflight stopped with `managed_assets_outdated`; review START ran after `gentle-ai sync --agent claude-code` (gentle-ai 4.2.0). The user granted consent, but START refused with `lens_context_budget_exceeded`: the Laravel scaffold and `composer.lock` exceed the reviewer budget, and no authority was created. The user then declined review for this candidate (`declined_this_candidate`, lineage `review-92ffb2e9bb5e211e`) because it is framework scaffolding. Delivery follows ordinary repository policy.
- [x] T03 Docker Compose: db + test database, api, web (Nginx proxy); migrations and seeders on boot. Route: D. Check: `docker compose up` serves the health endpoint through localhost:8080.
  - Route: delegated writer (compose, Dockerfile, entrypoint, nginx, postgres init, phpunit.xml, seeder, test).
  - RED: `DatabaseConnectionTest` failed with `-'ecommerce_test' +'ecommerce'`. GREEN: `<server>` entries in `phpunit.xml`; 2 tests, 5 assertions. PHPUnit `<env force>` does not override Docker variables, because Laravel reads `$_SERVER` first.
  - Checks: `up --wait` brought all 3 services up healthy; `/api/health` returned `{"status":"ok"} 200`; `/` returned 200; pest, phpstan (level 9) and pint passed in the container; after an api restart the stack stayed healthy, which shows migrations and seeding are idempotent. The parent spot-checked `up --wait` and the health curl.
  - Deviations: the api healthcheck (`nc` on port 9000) gates `web`; one nginx regex location handles `/api` and `/sanctum`; the code is baked into the image (rebuild after changes); `DatabaseSeeder` is empty until T06.
  - Environment issue: Compose v5 bake builds hang on this machine; `COMPOSE_BAKE=false` builds in about 1 minute. The README run instructions must mention this.
  - Backend tests now run inside the container: `docker compose exec api ./vendor/bin/pest`.
  - Evidence: commit `16f5593`.
  - Review: assessed `high` (170 lines, base `e74dcac`). The user granted consent; the four-lens review was **approved** and acknowledged (lineage `review-2b35a977877aa1ec`, authority burned). The reviewed boundary is now `16f5593`. It raised 15 non-blocking advisory findings, recorded below as follow-ups.
  - Advisory follow-ups (not yet authorized as scope):
    - F1: publish `web` on `127.0.0.1:8080` only, and make sure `APP_DEBUG` is false outside local development (R1-001).
    - F2: `APP_KEY` rotates whenever the api container is recreated; pass a stable key through compose (R3-001, R4).
    - F3: add a `restart` policy for `api`, and make nginx re-resolve the `api` upstream (Docker DNS resolver) after a recreate (R4).
    - F4: run the db healthcheck over TCP (`pg_isready -h localhost`) so it does not race the init script (R4).
    - F5: seeders must be idempotent (`firstOrCreate`). Applies to T06 (R2, R3-004).
    - F6: document that `ecommerce_test` is created only on the first volume init; recover with `docker compose down -v` (R3-002).
    - F7: the `api` container runs as root during `exec`, so storage files can become root-owned (R4).
    - Resolved in the hardening commit (user approved on 2026-10-16): F1 (loopback bind; `APP_DEBUG` is left at the local default because the port is now loopback-only), F2 (stable dev `APP_KEY` in compose; the entrypoint skips generation when it is set), F3 (`restart: unless-stopped` on api and web; nginx `resolver 127.0.0.11` with a variable upstream), F4 (`pg_isready -h localhost`), F6 (documented in the README together with `COMPOSE_BAKE=false`). Checks: all 3 services healthy; port published on `127.0.0.1:8080`; health returned 200 before and after `--force-recreate api`; the app key was identical after the recreate; pest 2/2 passed. Gap: the recreated api kept the same IP (172.18.0.3), so nginx re-resolution after an IP change was configured but not exercised. F5 moves to T06; F7 stays open.
    - Notes accepted as-is: the plaintext local DB password (local only, no host port), the `<server>` vs `<env>` choice in `phpunit.xml`, and the empty seeder. The no-comments rule is a challenge constraint, so the README will explain these.
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
- 2026-10-15: Decision round closed. Feature document created.
- 2026-10-15: Chain strategy set to stacked-to-main. T01 done on `feat/01-bootstrap`.

## Next step
T02, the backend scaffold, on `feat/01-bootstrap`.
