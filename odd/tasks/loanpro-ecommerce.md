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
- **Docker**: db (Postgres), api (PHP-FPM; runs migrations and seeders on boot), and web (Nginx serving the SPA and proxying /api and /sanctum). Single origin at localhost:8000 (revised from 8080).
- **Frontend libraries**: TanStack Query, React Router, React Hook Form + Zod, Zustand persist, Tailwind 4 + shadcn/ui (in shared/ui).
- **Tests**: Pest unit + feature (against Postgres), Vitest + RTL + MSW, Larastan + Pint, ESLint + TypeScript strict, GitHub Actions. Concurrency gets a two-process integration test or a declared gap. Playwright is optional.

## Environment (observed)
PHP 8.4.25, Composer 2.10.2, Node 20.20.2, npm 11.14.1, Docker 29.4.1, Compose v5.1.3, git 2.54.0. The project directory is empty and is not a git repository yet.

## Delivery
- Forecast: about 5,700 authored changed lines, well above the 400-line slice budget.
- Strategy: ask-on-risk. The chain strategy is **stacked-to-main** (chosen by the user on 2026-10-15). Each slice branch starts from the previous one, and the PRs merge into `main` in order.
- Base: `main` holds an empty root commit (`chore: initial commit`) so that the first PR has a base.
- Planned slices (branch → tasks). Revised on 2026-10-16: slice 2 was split because T04 + T05 came to about 1,050 lines.
  1. `feat/01-bootstrap` → T01, T02, T03, T03b (PR #1)
  2. `feat/02-catalog-domain` → T04
  3. `feat/03-catalog-persistence` → T05
  4. `feat/04-identity` → T06
  5. `feat/05-catalog-api` → T07, T08
  6. `feat/06-import` → T09
  7. `feat/07-ordering` → T10, T11
  8. `feat/08-frontend-foundation` → T12, T13
  9. `feat/09-store-checkout` → T14, T15
  10. `feat/10-admin-ui` → T16, T17
  11. `feat/11-ci-docs` → T18, T19 (T20 optional)
- Each stacked PR targets its parent branch, so its diff shows only its own work unit; it is retargeted to `main` once the parent merges.
- Every PR gets an `@codex review` comment after it is opened (user rule).
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
    - Hardening commit `c8ea595`, reviewed with base `16f5593`: high, consent granted, four lenses **approved** and acknowledged (lineage `review-ad4cb11261d393cf`). The reviewed boundary is now `c8ea595`. Advisory notes: the committed dev `APP_KEY` must never be reused outside local development (README states this); explain the Docker DNS resolver in the README; state the minimum Compose version that supports `--wait`; nginx re-resolution is still unexercised (same gap as above).
    - Notes accepted as-is: the plaintext local DB password (local only, no host port), the `<server>` vs `<env>` choice in `phpunit.xml`, and the empty seeder. The no-comments rule is a challenge constraint, so the README will explain these.
- [x] T03b Repackage as two containers (user decision, 2026-10-16): `app` (Nginx + PHP-FPM + supervisord in one image, built from the repository root so it can serve the SPA later) and `db`, plus a dev compose override that bind-mounts the backend code with vendor in a named volume. Remove the cross-container FastCGI and the nginx resolver. Route: D. Check: `up --wait` shows 2 healthy services; health returns 200; tests pass in the container; dev mode runs the host code without a rebuild.
  - Files: `docker/app/{Dockerfile,nginx.conf,supervisord.conf,php.ini,entrypoint.sh,html/index.html}`, `docker-compose.dev.yml`, and a root `.dockerignore`. The old `backend/Dockerfile`, `backend/.dockerignore`, `backend/docker/` and `docker/nginx/` were removed.
  - Checks (from a fresh volume): `app` and `db` healthy; `/api/health` returned 200; `/` returned 200; `/.git/config` returned 403; the SPA fallback `/products/1` returned 200; pest 2/2, phpstan level 9 and pint passed; health returned 200 after an app restart; in dev mode the bind mount and vendor volume were confirmed through `/proc/mounts` and pest passed. The parent spot-checked `up --wait`, `ps` and the health curl.
  - Resolves the nginx stale-upstream gap from T03 by design: nginx and FPM talk over `127.0.0.1`.
  - Notes: `up --wait` requires Compose v2.1.1 or later (verified in the release notes by the writer). The dev-mode vendor volume goes stale after Composer changes (documented in the README). F7 (root-owned storage after `exec`) is still open. TDD exception: packaging only; the existing suite is the regression proof.
  - Evidence: commit `4f4eff6`. Review with base `c8ea595`: high (269 lines), consent granted, four lenses **approved** and acknowledged (lineage `review-d52366b100517be1`). The reviewed boundary is now `4f4eff6`.
  - Advisory follow-ups:
    - F8: supervisord as PID 1 keeps the container alive if php-fpm or nginx goes FATAL, so `restart: unless-stopped` never fires; add an event listener that stops supervisord on FATAL.
    - F9: in dev mode the bind mount hides the image's storage dirs, and the entrypoint chowns host files.
    - F10: the import accepts files up to 10 MB (Laravel `max:10240`); the infrastructure limits sit above it to leave room for multipart overhead (nginx `client_max_body_size 12M`, php `post_max_size 12M`, `upload_max_filesize 11M`). Keep the three in step.
    - F13 (user asked to revisit after the core): measure a large synchronous import, then evaluate async import (queue job + `imports` table + status endpoint) with push notification via Laravel Reverb (1.12 supports Laravel 13) versus polling. A browser cannot receive webhooks; SSE is a poor fit for PHP-FPM.
    - F14 (done on `feat/10-checkout`, first commit): skip blank records before the CSV header so a leading empty line is not reported as "missing all columns"; pin it with a test.
    - F11: add a repeatable smoke script (or CI step) for the packaging checks.
  - Codex review on PR #1 (2026-10-16) raised F8 and F9 again as P2. Both were fixed on `feat/01-bootstrap` with the user's approval:
    - F8: a supervisord `eventlistener` on `PROCESS_STATE_FATAL` sends SIGTERM to supervisord. Proof: php-fpm was removed and killed inside the container; supervisord logged `php-fpm entered FATAL state`, then shut down, and the Docker RestartCount went from 0 to 1. After a force-recreate, health returned 200.
    - F9: in dev mode (`APP_SOURCE_MOUNTED=true` from `docker-compose.dev.yml`) the entrypoint runs `chmod -R a+rwX` instead of `chown`. Proof: the host owner stayed 501:20, www-data could write to `storage/logs`, pest passed 2/2 in dev mode, and `git status` showed no mode changes.
    - Commit `494b38d`, reviewed with base `4f4eff6`: high (28 lines), consent granted, four lenses **approved** and acknowledged (lineage `review-be04f9118891362d`). The reviewed boundary is now `494b38d`. Advisory notes:
      - The dev chmod is world-writable on the host (documented as local-only).
      - The `chmod -R` under `set -e` could abort boot on rootless Docker or user-namespace remapping (F12, open).
      - The listener reads the PID file: `pidfile=/run/supervisord.pid` is set in the same file, so the PID-file finding does not apply.
      - The listener does not complete the supervisor event protocol handshake, which is acceptable because it terminates on the first event.
      - Repeatable smoke checks are still F11.
  - Codex second round on `46894bc`:
    - The FATAL finding was repeated on the same line. It is not applicable, because the event listener is already in place and was proven; the reply goes in the PR thread.
    - APP_URL (valid): the user chose port 8000 (the Laravel convention) instead of 8080. `docker-compose.yml` now publishes `127.0.0.1:8000` and sets `APP_URL=http://localhost:8000` explicitly. Proof: health returned 200 on :8000 and `config('app.url')` returned `http://localhost:8000`.
    - Root-owned `.env` in dev mode (valid): after the copy, in mounted mode, the file is chowned to the owner of the mount dir. Proof: the entrypoint syntax is OK and dev mode stays healthy. Gap: macOS reports the mount as 0:0, so the Linux UID path was not exercised.
  - Port 8080 is replaced by 8000 everywhere, including the PR body.
  - Commit `42766c8`, reviewed with base `494b38d`: high (21 lines), consent granted, four lenses **approved** and acknowledged (lineage `review-d44def4504f3f30d`). The reviewed boundary is now `42766c8`. Advisory notes:
    - F12 is widened: the dev-mode `chown` of `.env` and the `chmod` both run under `set -e` and can abort boot on rootless Docker or user-namespace remapping. Make them best-effort with a warning.
    - The Linux UID path is unproven on macOS.
    - A root-owned `.env` left by an older boot stays root-owned.
    - The port and `APP_URL` must be kept in sync.
    - The committed dev `APP_KEY` (already documented).
- [x] T04 Catalog domain: Sku, Money, Stock value objects, Product entity, validation rules. Route: D. Check: Pest unit tests green.
  - Route: delegated writer (10 domain classes and 7 test files). Strict TDD; the RED/GREEN log is in the writer report (each behavior failed first for a missing behavior).
  - Domain: `Sku`, `ProductName`, `CategoryName` (with `normalized()`), `Money` (integer cents, no floats), `Stock`, `Weight` (integer grams, optional), `Product`, and `InvalidProductAttribute(field, reason)`. Shared helpers: `RequiredText` and `ScaledDecimal`.
  - Assumptions: SKU equality was case-sensitive (superseded on 2026-10-16: SKUs are always stored uppercase, by user decision); max lengths are 64, 255 and 100 characters (`mb_strlen`); weight has up to 3 decimals; overflow is rejected as "is too large"; `+5`, `1e3`, `.99`, `29.` and decimal commas are rejected; leading zeros are accepted.
  - Checks: pest unit 97 tests / 174 assertions (the parent re-ran it as a spot check); phpstan level 9 reported 0 errors; pint passed; there are no `Illuminate` references in the domain.
- [x] T05 Catalog persistence: products/categories migrations (unique sku that includes deleted rows, soft deletes, pg_trgm indexes), Eloquent repository and mapping. Route: D. Check: Pest feature tests against Postgres.
  - Route: delegated writer (3 migrations, port, 2 models, repository, provider, tests).
  - Built: `ProductRepository` port (`save` = upsert by SKU inside a transaction; `findBySku` excludes soft-deleted rows), `EloquentProductRepository`, `CatalogServiceProvider`, and `Money::fromCents` / `Weight::fromGrams` (both reject negatives).
  - Schema: `pg_trgm` extension; `categories` with a unique `normalized_name`; `products` with a plain unique `sku` that also covers deleted rows (the reservation is proven by a raw insert), named CHECK constraints on price, stock and weight (proven by raw inserts), and 3 trigram GIN indexes.
  - Checks: the full suite passed in the container, 115 tests / 204 assertions (the parent re-ran it); `migrate:fresh`, rollback and migrate ran clean; phpstan level 9 reported 0 errors; pint passed; the domain is framework-free.
  - Notes for T07:
    - Saving a SKU of a soft-deleted product currently surfaces as `UniqueConstraintViolationException`; T07 must map it to the domain error "SKU belongs to a deleted product".
    - Soft-deleted categories are not handled.
    - The transaction rollback is untested, and concurrent saves are untested.
- [x] T06 Identity: Sanctum SPA, register/login/logout/me, admin and buyer roles, policies, demo seeders. Route: D. Check: feature tests for auth and role access.
  - Route: delegated writer (about 330 production lines and 416 test lines).
  - Built: `Role` enum (admin, buyer) in a `users.role` column (NOT NULL, default buyer, not mass-assignable); `statefulApi()` (Sanctum SPA cookie mode); `POST /api/auth/register` (always creates a buyer; a `role: admin` field in the payload is ignored), `login` (throttled 5 per minute per email+IP; the generic 422 does not reveal whether the email exists), `logout` (invalidates the session and rotates the CSRF token), and `GET me`; a `role` middleware alias (401 for guests, 403 for the wrong role); gates `manage-catalog` and `purchase`; idempotent seeders admin@example.com and buyer@example.com, both with password `password` (F5 resolved); README demo accounts.
  - Checks: full suite 146 tests / 294 assertions (the parent re-ran it); `migrate:fresh --seed` plus a second `db:seed` still leaves exactly 2 users; curl through nginx returned csrf-cookie 204, login 200 (admin), me 200, and 419 without XSRF; phpstan level 9 reported 0 errors; pint passed.
  - Notes:
    - The session-flow tests use a `BrowserSession` helper that forces database sessions; without it, the same-session test passed even with the cookie removed.
    - Laravel 13 already regenerates the session on login.
    - The stock `GET /api/user` route from `install:api` was removed in the root-cause pass (`StockUserRouteTest` asserts 404).
  - Consolidated native review (base `666e328`; covers T04, T05, the pg_trgm fix and T06): medium, 1,844 lines, consent granted, one lens (reliability), **approved** and acknowledged (lineage `review-7d1107ccb9268002`). The reviewed boundary is now `f47b518`. Advisory notes:
    - All three notes are RESOLVED by the root-cause pass below: stock and weight are now bounded to int32; categories no longer use soft deletes; a mistyped `role:` parameter now returns 403.
  - Root-cause pass (2026-10-16), triggered by repeated Codex findings on PRs #3 and #4 (decision record: Obsidian `Decisiones/2026-10-16-correccion-por-causa-raiz.md`):
    - Cause 1, states the product never creates: category soft deletes removed.
    - Cause 2, infrastructure errors leaking: `SkuIsReserved` (including a race), registration unique race returns 422, an array email on login returns 422, invalid UTF-8 or NUL in text returns a domain error or 422.
    - Cause 3, domain/DB parity: column-by-column audit plus a max-value round-trip test, int casts, CHECKs for blank or padded name, `users_role_valid`, and `products_sku_format`.
    - Cause 4, normalization: NFC, Unicode trim and whitespace collapse for categories; emails lowercased at the boundary plus a unique index on `lower(email)`; SKUs restricted to `[A-Z0-9._-]` and stored uppercase (user decision).
    - Cause 5, fail-open: role middleware fails closed; demo seeders allowlisted to local/testing inside each seeder; per-IP throttles on register and login; a real 419 CSRF test; stock `GET /api/user` removed.
    - Commits on feat/03: `9124f67`, `357987d`, `683d142`. Commits on feat/04: `981445c`, `9c4d429`, `4e0b81d`. Full suite: 230 tests / 519 assertions.
    - Consolidated native review over PRs #3 and #4 (67 files, 2,535 lines): medium, consent granted, **approved** and acknowledged (lineage `review-5e56a3d82f7e71d7`). Remaining suggestion: demo seeders match the email exactly; a hand-inserted mixed-case demo email would collide with the `lower(email)` index (accepted, because the boundary always lowercases).
    - Follow-up review on PR #4 after the rebase onto main (lineage `review-df412755db034785`, approved): `\p{Cc}` now defines control characters in the 3 text checks (DEL and C1 included); login and register without a stateful SPA session return 403 instead of a silent 200 (`4ad5f81`). Next review (lineage `review-3d23ef0b2ae57ac2`, approved): the login throttle key now trims as well as lowercases, so it does not depend on the global TrimStrings middleware (proven with a test that disables it).
    - Accepted: the `lower(email)` unique index migration assumes no existing case-variant duplicates; this is a greenfield database, and the boundary normalizes every new email.
    - Accepted trade-offs: register reveals existing emails (mitigated by the per-IP throttle); Laravel 13 accepts `Sec-Fetch-Site: same-origin` without a token; repository `save()` is last-write-wins (T07 create must reject existing SKUs); a real two-connection race test is deferred to T11.
- [x] T07 Catalog API: admin-only CRUD, category find-or-create, soft delete, reserved SKU error. Route: D. Check: feature tests.
  - Route: delegated writer (about 389 production lines and 453 test lines). The root-cause quality bar was given up front.
  - API, under `auth:sanctum` + `can:manage-catalog` (guest 401, buyer 403, tested per verb, with a mutation check):
    - `POST /api/admin/products` returns 201, or 422 on `sku` when the SKU already exists or is reserved.
    - `GET /api/admin/products/{sku}` returns 200, or 404.
    - `PUT /api/admin/products/{sku}` is a full update; the SKU is immutable and renaming is allowed.
    - `DELETE /api/admin/products/{sku}` returns 204 (soft delete; the SKU stays reserved).
  - `price` and `weight_kg` must be JSON strings. Wrong JSON types return 422, never 500.
  - Domain errors are mapped centrally in `Catalog/Infrastructure/Http/CatalogExceptions` (wired from `bootstrap/app.php`).
  - Create is insert-only through the new port method `ProductRepository::add` (no check-then-upsert); `delete(Sku)` is an atomic soft delete.
  - Checks: full suite 316 tests / 864 assertions (the parent re-ran it); curl CRUD through nginx passed; phpstan level 9 reported 0 errors; pint passed; Domain and Application are framework-free.
  - Review follow-up (native review, lineage `review-e3608f86664903c3`): updates are now atomic and update-only through `ProductRepository::update` (a single conditional UPDATE on the live row; 0 affected rows throws `ProductNotFound`; it never inserts). An update racing a delete now returns 404, proven by an in-flight race test (`335e8c1`). Native review approved (lineage `review-948fdd0a9067d36b`).
  - Notes:
    - Write primitives: create = `add` (insert-only), update = `update` (update-only), delete = `delete` (atomic soft delete); the upsert `save` is reserved for the T09 import.
    - The affected-row check relies on PostgreSQL counting matched rows; MySQL counts only changed rows, which is noted but out of scope (the stack is Postgres).
    - Behavior change: `PUT` to an unknown SKU with a mismatched body SKU returns 422 on `sku` (body validation runs first).
    - A real two-connection race for `add` is deferred to T11.
- [x] T08 Search API: free text, filters, sorting, pagination. Route: inline (no writer trigger). Check: feature tests. Commit 240c486.
  - 2026-10-16 user decision: search is public ("Pública (Recomendado)"): guests browse and search, buying requires a buyer session; admin uses the same endpoint.
  - Contract (assumptions, not user strings): `GET /api/products` with `q` (≤100 chars, no control characters; `%`, `_` and `\` are literal), `category` (normalized match), `min_price`/`max_price` (price format; min ≤ max), `in_stock` (`1|0|true|false`), `sort` (`name|-name|price|-price`, default `name`, tie-break by sku), `page` (≥1), `per_page` (1–50, default 12). Response: `data` (product resource) and `meta` (`current_page`, `per_page`, `total`, `last_page`). Soft-deleted products are excluded.
  - Evidence: `SearchProductsApiTest` (59 cases), RED observed per behavior (NUL and invalid UTF-8 in `q` were 500 before the rules); wildcard escaping mutation-checked (removing it fails 3 cases); suite 383 passed, Larastan clean, Pint clean. The row→domain mapping moved to `ProductModel::toProduct()`, shared by the repository and the search. Native assess: medium, `under_budget`, no review due.
  - Page is capped at 1,000,000 so the offset can never overflow.
  - Native review (lineage review-791a63959800dd7d, 1 lens reliability): approved and acknowledged. Suggestion applied: a combined-filters test (mutation-checked by flattening the OR group). Suggestion not applied: duplicating the category length/control rules in the request, because `CategoryName` stays the single source of truth for those limits (parity, cause 3) and its 422 mapping is already tested.
- [x] T09 Import: CSV reader, row validation, in-file duplicate detection, name-guarded upsert, per-row report endpoint. Route: inline (no writer trigger). Check: unit + feature tests, including the sample CSV traps. Commits 97b1fd3, 4fb5aca, c9cfc3c, plus the review fix.
  - 2026-10-16 user decision: "league/csv (Recomendado)" behind a `CsvRowReader` port (league/csv 9.28.0 requires PHP ^8.2, compatible with 8.4). PR #6 merged; Codex found no issues.
  - Contract (assumptions, not user strings): `POST /api/admin/products/import` (admin only), multipart `file` (.csv/.txt, ≤10 MB, F10). The header must hold exactly the columns name, sku, description, category, price, stock, weight_kg in any order (trimmed, case-insensitive); a missing, duplicate or unknown column rejects the whole file with 422. Fully blank rows are skipped and counted. A row with the wrong number of fields fails. Every invalid field of a row is reported, not only the first. Rows are numbered by CSV record with the header as row 1 (equal to the file line unless a quoted field spans lines).
  - In-file duplicates by canonical SKU: differing data rejects every row of that SKU; identical rows are applied once and the repeats are reported as `unchanged` ("identical to row N").
  - Existing SKU: same normalized name and same data → `unchanged`; same normalized name, different data → `updated` (stock absolute); different name → `failed`; soft-deleted SKU → `failed` (reserved). Response: `summary` (created, updated, unchanged, failed, blank_rows), `new_categories`, `rows` (row, sku, status, errors).
  - 2026-10-16 user decision (verbatim): "sigamos con la solución sincrona pero posterior me gustaría que este pendiente para que veamos como se comporta por que es bueno que podamos tener en cuenta esa parte". The import stays synchronous with an explicit cap of 5,000 data rows: a larger file is rejected with 422 before any row is written. Follow-up F13 below.
  - Evidence: unit tests for the reader, header and parser; `ImportProductsApiTest` (25) and `SampleCsvImportTest` (2): the sample CSV gives created 84, failed 11 (RS-001 x2, BS-021 x3, `$29.99`, `free`, stock -5, blank name, whitespace name, missing category), blank_rows 2; XSS and SQL names are stored verbatim; re-import reports 84 unchanged. RED observed per behavior; a hollow upload dataset (closures wrapped in an extra array) was caught and fixed. Races between read and write become failed rows instead of a 500 mid-import. Invalid UTF-8 in the header or a SKU is scrubbed before it reaches JSON. Suite 426 passed, Larastan and Pint clean. F13 data point: 5,001 rows took about 7 s in the test container.
  - Native review (lineage review-ee0d563e9a3a9b3a, lenses risk + reliability): approved and acknowledged. WARNING applied: an identical repeat now mirrors the first row's outcome (failed stays failed) instead of always reporting unchanged.
  - Second native review over the fix (lineage review-a2b6d63b56095635, reliability): approved and acknowledged. Two suggestions recorded, not applied: `getRealPath()` returning false only if the temp upload vanishes (not realistic input); rows with the wrong field count stay out of the duplicate grouping on purpose, because their SKU is not reliable.
  - Codex on PR #7 (3 x P2), one root cause, silent tolerance: (1) unnamed header columns (trailing comma) were ignored, now 422 with their positions; (2) physically empty lines were skipped by league/csv, now `includeEmptyRecords()` and counted as blank before the field-count check; (3) identical invalid rows got per-row signatures and a false "different data" error, now invalid rows are compared by their trimmed raw fields and repeats mirror the first row. RED observed for each.
  - User concern (2026-10-16, verbatim): "hay algunas filas que traen comandos que pueden borrar la base de dtaos o eliminar registros con drop y eso es peligroso hay que evita el scripting que puedan incluir los registros". Evidence: every write uses bound parameters (no raw SQL with input in `src`; `orderByRaw` uses fixed enum strings); a test imports `DROP TABLE` / `DELETE FROM` / `<script>` payloads in name, description and category and asserts both tables and the existing product survive and the text is stored verbatim. Output escaping belongs to the UI (React escapes text by default; no `dangerouslySetInnerHTML`, to be enforced in T12–T17).
  - Third native review (lineage review-ff31cf2785e67894, reliability): approved and acknowledged. WARNING applied: league/csv's default backslash escape swallowed a closing quote after a backslash (e.g. a Windows path); the reader now uses `setEscape('')` (RFC 4180), with a unit test that fails without it.
  - Codex second pass on PR #7 (2 x P2) plus native review 4 (lineage review-aa7c4d476033f6b3, approved, 3 suggestions): (1) check-then-write on the name guard: `ProductRepository::update()` takes an optional expected name and updates `WHERE sku AND name` atomically; a concurrent rename fails the row (`ProductChangedConcurrently`); (2) nginx/php body limits raised above the 10 MB file limit (F10), not covered by an automated test, checked on the next image rebuild; (3) blank rows no longer count toward the 5,000 cap, with a test that exactly 5,000 rows plus blanks are accepted; (4) invalid-row signatures normalize name and category casing and spacing. Accepted trade-off: an unexpected database error mid-import returns 500 after earlier rows were committed; the import is partial by design and re-importing is idempotent. Suite 436 passed.
  - Native review 5 (lineage review-6a1da134e14e4b18, resilience + reliability): approved and acknowledged. WARNING = the accepted partial-write trade-off above (no change). SUGGESTION deferred to F14 by the user (2026-10-16, verbatim: "ya no encontro issues codex creo que eso lo podemos corregir en el siguiente pr para cerrar esta parte"): a blank line before the header is rejected as "missing all columns" because empty records are now kept.
- [x] T10 Ordering: orders/order_items with snapshots, atomic Checkout use case, PaymentGateway port + fake adapter, idempotency, My orders. Route: inline (no writer trigger). Check: unit + feature tests. Commits 123a238, ee756f2, 3c109dd.
  - Contract (assumptions, not user strings; built on the agreed decisions above):
    - `POST /api/orders` (buyer only, `can:purchase`), header `Idempotency-Key` (UUID, required), body `items` (1–50 lines of `sku` + `quantity` 1–999, SKUs unique per request) and `card` (`number` 13–19 digits with spaces allowed, `exp_month`, `exp_year` not expired, `cvc` 3–4 digits).
    - One transaction: lock the product rows `FOR UPDATE` in SKU order (no deadlocks), reject the whole order with 409 and per-SKU details when any item is missing, deleted or short, price from the database, charge the fake gateway, decrement stock, store the order with frozen name/SKU/unit price per line.
    - Payment: `4242424242424242` approves; `4000000000000002` declines with 402; any other number is declined as an unsupported test card. Only brand and last 4 are stored; the number and CVC are never persisted or logged. A declined payment stores nothing.
    - Idempotency: unique (user, key). Same key + same request → the stored order with 200; same key + different request → 422. Concurrent duplicates are resolved by the unique index.
    - `GET /api/orders` (own orders, newest first, paginated) and `GET /api/orders/{id}` (404 for another buyer's order).
    - The fake gateway runs inside the transaction while the locks are held; a real gateway would use authorize/capture or an outbox (README trade-off).
  - Evidence T10: PaymentCard and FakePaymentGateway unit tests; `PlaceOrderApiTest`, `MyOrdersApiTest`, `OrderingSchemaTest`. Mutation checks, each caught by a test: no shortage check, wrong shortage comparison, no request-hash check, no stock decrement, no payment, no duplicate-claim handling. The key is claimed first (pending row under the unique index), so a concurrent duplicate waits on the index and replays instead of charging twice. Suite 489 passed, Larastan and Pint clean.
  - Evidence T11: `ConcurrentCheckoutTest` launches two PHP processes (`tests/Concurrency/place-order.php`, a seam holds the lock 300 ms) against `ecommerce_test` with `DatabaseTruncation`. Last unit: one created, one insufficient, stock 0, one order. Same key: one created, one replayed, one order. Removing `lockForUpdate()` makes the first test fail: both read stock 1 and the second hits `products_stock_non_negative` (a 500 instead of a 409). Two test defects found in the full suite and fixed: a fixed 1.5 s start deadline crashed when processes booted slowly (now a ready/go barrier over stdin/stderr), and committed rows leaked into later RefreshDatabase tests (now truncated after each concurrency test). Full suite 491 passed in 5 of 5 consecutive runs; the lock mutation still fails the test.
  - Native review (lineage review-749d0b7bdede8e74, risk + resilience + reliability): approved and acknowledged. Applied: (1) empty `page`/`per_page` became 0 and divided by zero (500) in My orders and in the T08 product search, same root cause, now default; (2) order ids above PHP_INT_MAX hit a TypeError, the route now bounds the id; (3) checkout throttled to 10 per minute per buyer (card-testing guard before a real gateway); (4) the concurrency harness times out stalled children (corrected later: the first version used `stream_set_timeout`, which is a no-op on process pipes; see review 4). Accepted trade-off, unchanged: the charge happens inside the transaction before commit, so with a real gateway a failure after approval would leave an uncompensated charge; the fix is authorize/capture or an outbox (README, T19). Suite 495 passed.
  - Native review over the fixes (lineage review-cf0af38cc75c5b20, risk + reliability): approved and acknowledged. Applied: two lines whose SKUs only match after canonicalization (e.g. ` mug-1 ` without the trim middleware) silently merged quantities; they are now rejected per line. Accepted: the request hash uses brand, last 4 and expiry (never the full number, by the no-PAN rule), so a different card with the same last 4 and expiry replays the buyer's own order instead of returning 422; the charge-before-commit trade-off stays documented. Suite 496 passed.
  - Native review 3 on T10 (lineage review-1d6537343299cdb6, reliability): approved and acknowledged. Applied: all-digit SKUs (valid, e.g. `12345`) became integer array keys and a shortage raised a TypeError (500); keys are now cast back to strings. Pinned: an all-blank CSV is rejected as missing all columns. Suite 498 passed.
  - Native review 4 on T10 (lineage review-93c35b11438ea9eb, reliability): approved and acknowledged. Verified in the container that `stream_set_timeout()` returns false on proc_open pipes, so the earlier timeout fix did nothing; the harness now reads with `stream_select` against a 30 s deadline and terminates the children in `finally` (a probe with a 5 s sleep and a 1 s deadline returned in about 1 s). The shared test card now expires four years from today instead of 12/2030. Accepted: the checkout throttle also counts idempotent retries. Suite 498 passed in 3 of 3 runs.
- [x] T11 Concurrency integration test (two processes, last unit) or a declared gap. Route: inline. Check: the test is stable (3/3 runs). Commit in this PR.
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
- 2026-10-16: Slice 1 complete (T01, T02, T03, hardening, T03b). Pushed, and PR #1 opened: https://github.com/mrtheroi/loanProChallenge/pull/1 (`size:exception` recommended: about 12,300 generated or data lines; about 430 authored).

## Next step
T10 on `feat/10-checkout` (PR #7 merged 2026-10-16).
