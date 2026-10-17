# LoanPro Code Challenge — E-commerce

An e-commerce application with product CRUD, CSV import, product search, and purchase with a fake payment. Every feature has a UI, and the whole stack runs with Docker.

- **Backend:** Laravel 13 (PHP 8.4), organized as screaming/hexagonal modules (`Catalog`, `Import`, `Ordering`, `Identity`).
- **Frontend:** React 19 + Vite + TypeScript, TanStack Query, React Router, Zustand, Tailwind 4 and shadcn/ui.
- **Database:** PostgreSQL 17 (local, in Docker).
- **Quality:** Pest (unit and feature tests against PostgreSQL), Larastan level 9, Pint; Vitest + Testing Library + MSW, `tsc` strict, oxlint; GitHub Actions CI.

## Contents

- [Sample data](#sample-data)
- [Running locally](#running-locally)
- [Trying it out](#trying-it-out)
- [Architecture](#architecture)
- [Decisions, approach, and alternatives considered](#decisions-approach-and-alternatives-considered)
- [Security](#security)
- [Known limitations and follow-ups](#known-limitations-and-follow-ups)
- [How AI was used](#how-ai-was-used)

## Sample data

The example CSV provided by the challenge is stored at [`docs/sample-data/products.csv`](docs/sample-data/products.csv).

- **Downloaded on:** 2026-10-15
- The original file was shared as a spreadsheet; this CSV is its export, with the same 97 data rows.
- The file contains intentionally invalid rows (invalid prices, negative stock, blank names, script and SQL injection payloads, quoting and unicode edge cases, blank rows, duplicate SKUs). How each case is handled is described under [CSV import](#csv-import).

## Running locally

Requirements: Docker with Docker Compose v2.1.1 or later (the first release with `up --wait`).

The stack has two containers: `app` (Nginx, PHP-FPM and the Laravel code in one image, run by supervisord; the image also builds the React frontend and Nginx serves it) and `db` (PostgreSQL). Migrations and seeders run when `app` starts.

```bash
docker compose up -d --build --wait
curl http://localhost:8000/api/health
```

Open `http://localhost:8000`. The port is bound to localhost only. If port 8000 is already in use, change the published port in `docker-compose.yml` together with `APP_URL`.

### Development mode

Development mode mounts `./backend` into the container, so backend changes apply without a rebuild:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build --wait
```

To work on the frontend with hot reload, run Vite on the host with Node 26 (`frontend/.nvmrc`). It proxies `/api` and `/sanctum` to port 8000:

```bash
cd frontend
npm ci
npm run dev        # http://localhost:5173
```

### Tests and checks

```bash
# Backend (inside the container, against the dedicated ecommerce_test database)
docker compose exec app ./vendor/bin/pest
docker compose exec app ./vendor/bin/phpstan analyse
docker compose exec app ./vendor/bin/pint --test

# Frontend
cd frontend
npm test           # Vitest + Testing Library + MSW
npm run typecheck  # tsc
npm run lint       # oxlint
```

GitHub Actions (`.github/workflows/ci.yml`) runs the same backend and frontend checks on every pull request and on every push to `main`. The backend job uses a PostgreSQL service and needs no environment file: database credentials come from job variables and a throwaway `APP_KEY` is generated per run.

### Notes

- If the build stalls with no output (an issue observed with the Compose Bake builder), run it with `COMPOSE_BAKE=false docker compose up -d --build --wait`.
- In development mode, `vendor` lives in the `backend-vendor` named volume, seeded from the image the first time it is created. After changing Composer dependencies, remove that volume (`docker compose -f docker-compose.yml -f docker-compose.dev.yml down -v`) and rebuild.
- In development mode, the entrypoint does not change file ownership on the mounted source. It makes `storage` and `bootstrap/cache` group- and world-writable instead, so PHP-FPM can write to them while the files stay owned by the host user. This is acceptable for local development only.
- If PHP-FPM or Nginx cannot be restarted, supervisord shuts down, the container exits, and Docker restarts it according to the `unless-stopped` policy.
- The `ecommerce_test` database is created only the first time the database volume is initialized. If the volume predates it, recreate the volume with `docker compose down -v`.
- The `APP_KEY` and database credentials in `docker-compose.yml` are for local development only.
- Checkout needs the browser's Web Locks API, which browsers expose only in secure contexts (HTTPS or `localhost`). `http://localhost:8000` qualifies; a plain-HTTP host other than localhost would block checkout (see [Purchase](#purchase-and-fake-payment)).

## Trying it out

### Demo accounts

The seeders run on every boot and create these two accounts only when they are missing. They never modify an existing account, and they create accounts only when `APP_ENV` is `local` or `testing`:

| Role | Email | Password |
|------|-------|----------|
| admin | `admin@example.com` | `password` |
| buyer | `buyer@example.com` | `password` |

New sign-ups always get the buyer role; the admin account exists only through the seeder. These accounts are for local evaluation only.

### Walkthrough

1. **Admin** (`admin@example.com`): open **Admin**. Search, page through, create, edit and delete products. Use **Import CSV** to upload [`docs/sample-data/products.csv`](docs/sample-data/products.csv) and read the per-row report.
2. **Store** (any visitor): search by name, SKU or description; filter by category, price range and "In stock only"; sort by name or price; open a product.
3. **Purchase** (`buyer@example.com`, or a new account): add products to the cart (the cart shows the stock of each line and explains when a quantity is reduced), then check out with a fake card:
   - `4242 4242 4242 4242` is approved;
   - `4000 0000 0000 0002` is declined.

   Any future expiry and any 3- or 4-digit CVC work. The order then appears under **My orders**.

## Architecture

### Backend: modules by business capability

```
backend/src/
├── Catalog/    products, categories, search      Domain / Application / Infrastructure
├── Import/     CSV import and per-row report     Domain / Application / Infrastructure
├── Ordering/   checkout, payment, orders         Domain / Application / Infrastructure
└── Identity/   users, roles, Sanctum SPA auth
```

- **Domain** holds value objects and rules with no Laravel dependency (`Sku`, `Money`, `Stock`, `PaymentCard`, `Order`), so they are unit tested in isolation.
- **Application** holds use cases (`CreateProduct`, `ImportProducts`, `PlaceOrder`, ...) that depend only on ports (`ProductRepository`, `PaymentGateway`, `StockLedger`, `CsvRowReader`).
- **Infrastructure** holds the adapters: Eloquent repositories, HTTP controllers and requests, `FakePaymentGateway`, the `league/csv` reader. Dependencies point inward.

### Frontend: features with container/presentational components

```
frontend/src/
├── app/        router, layouts, query client
├── features/   catalog, cart, checkout, orders, auth, admin-catalog, import
│               └── api/ containers/ components/
└── shared/     api client, money helpers, ui (shadcn/ui primitives)
```

Containers own data fetching and state; components only render props. Server state lives in TanStack Query; the cart lives in a persisted Zustand store. Money is handled as integer cents (`bigint`) end to end to avoid floating-point errors.

## Decisions, approach, and alternatives considered

The work was planned before any code: questions about the CSV traps, concurrency, security and scope were answered first, then the features were built test-first in small pull requests. Each decision below names the alternative that was rejected and why.

### Database: PostgreSQL

- **Chosen:** PostgreSQL in Docker.
- **Why:** checkout needs row-level locks (`SELECT ... FOR UPDATE`) to prevent overselling, partial unique indexes, CHECK constraints, and trigram indexes for search.
- **Alternatives:** SQLite locks the whole database on writes and has no trigram search; MySQL would work, but PostgreSQL's CHECK constraints and `pg_trgm` fit the requirements better.
- Tests that depend on locking run against PostgreSQL, never against an in-memory database.

### Products and SKUs

- **SKU is the identity.** One SKU per sellable variant, unique, case-insensitive, stored uppercase, restricted to `A-Z`, `0-9`, `-`, `_` and `.` (for example `33PL167-B`). The format is checked before uppercasing, so `ß` cannot silently become `SS`.
- **Validation in the domain and in the database.** Name, SKU, category, price and stock are required. Price is a decimal ≥ 0 with at most 2 decimals (`$29.99` and `free` are rejected; `0` is valid). Stock is an integer ≥ 0. Weight is optional and ≥ 0. The database repeats these rules as CHECK constraints, so data stays valid even if code bypasses the domain.
- **Categories are a table**, found or created by a normalized name, not a fixed enum. The CSV contains categories that an enum would have to reject; a table lets an import introduce them and report them as new.
- **Soft delete.** Deleted products disappear from the store and search but stay in the database, because orders reference them. A deleted product's SKU stays reserved forever: creating or importing it again is rejected rather than silently restoring old data. Orders are never deleted.

### CSV import

- **Partial import with a per-row report.** Each row is reported as created, updated, unchanged or failed, with every invalid field listed, plus blank rows and new categories. One bad row does not block the good ones.
  - *Alternative:* all-or-nothing import. Rejected because the sample file is intentionally dirty, so it would never succeed, and the admin would get no useful feedback.
- **Upsert by SKU, guarded by name.** An existing SKU whose normalized name matches is updated; if the name differs, the row fails, because the import must not rename products by accident. Identical data is reported as unchanged. Stock values are absolute, not increments.
- **Duplicate SKUs inside one file:** if a SKU appears more than once with different data, all of its rows are rejected, because the system cannot know which one is right. The system reports; it does not guess.
- **Injection payloads are data, never code.** Every write uses bound parameters, so SQL in a cell is stored as text; the UI renders values as text, so script payloads are never executed (pinned by tests).
- **Synchronous, capped at 5,000 rows and 10 MB.** 5,001 rows took about 7 seconds in testing, which is acceptable for an admin tool.
  - *Alternative considered:* an asynchronous import (queue job, `imports` table, progress pushed with Laravel Reverb). It is the right design for large files and is recorded as a follow-up. Polling was weighed against push; browsers cannot receive webhooks, so push would need WebSockets (Reverb) or Server-Sent Events, which fit poorly with PHP-FPM.

### Search

- **Chosen:** PostgreSQL `ILIKE` over name, SKU and description, backed by `pg_trgm` GIN indexes, with filters (category, price range, in stock), sorting (name, price) and server-side pagination. Search is public; buying requires a buyer session.
- **Why:** partial, case-insensitive matches on SKUs and names are what a catalog user types, and trigram indexes keep them fast.
- **Alternatives:** PostgreSQL full-text search handles words and stemming but not partial SKUs; Elasticsearch or Meilisearch would add a service to run and keep in sync, which this scope does not need.
- Search text is limited to 100 characters, rejects control characters, and escapes `LIKE` wildcards.

### Authentication and roles

- **Laravel Sanctum in SPA mode** (session cookie + CSRF token), with the SPA and the API on a single origin behind Nginx.
  - *Alternative:* API tokens in browser storage. Rejected because Sanctum's documentation advises against tokens for first-party SPAs, and an HTTP-only session cookie cannot be read by injected scripts.
- **Two roles.** `admin` (created only by the seeder) manages the catalog and the import; `buyer` signs up and purchases. Purchasing is buyer-only: an admin's cart explains this instead of offering checkout, and the backend enforces it regardless of the UI.
- Login is throttled per email and per IP, registration per IP, and checkout per buyer.

### Cart

- **Chosen:** the cart lives in the browser (persisted Zustand store) and does not reserve stock. Checkout sends SKUs, quantities and the prices the buyer saw; the server re-checks stock and current prices.
- **Alternative:** a server-side cart. It would allow a cart shared across devices, but it adds tables and endpoints, and reserving stock for abandoned carts creates its own problems. A server cart is a reasonable extension.

### Purchase and fake payment

- **Atomic checkout.** In one transaction the server locks the product rows (`FOR UPDATE`, in SKU order so two checkouts cannot deadlock), checks every line, charges the fake gateway, decrements stock and stores the order with prices frozen at purchase time. A shortage on any line fails the whole order and charges nothing.
- **Price changes are confirmed, never applied silently.** Each line carries the price the buyer saw. If a price changed, checkout returns `409 price_changes`, charges nothing, and the UI updates the cart and asks the buyer to review it.
- **Fake payment behind a port.** `PaymentGateway` with a `FakePaymentGateway` adapter: `4242…` approves and `4000…0002` declines (Stripe's test convention). Only the card brand and last four digits are stored, never the number or the CVC.
- **Idempotency against double charges.** Every checkout sends an `Idempotency-Key`. The server claims the key before charging, so a retry with the same key returns the same order instead of charging again; the same key with a different request is rejected. The browser keeps one random key per buyer and payment until it sees an outcome, and creates it under a cross-tab lock (Web Locks) so two tabs paying for the same thing share one key.
- **Kept in sync on purpose.** The browser's payment fingerprint (`frontend/src/features/checkout/idempotency.ts`) mirrors the backend request hash (`PlaceOrderCommand::requestHash`) and its card-brand detection mirrors `PaymentCard::brand()`; changing one side requires changing the other. Storage errors in that file are ignored on purpose: an in-memory copy of the key still covers retries in the same page.
- **Concurrency is tested for real.** An integration test starts two PHP processes that compete for the last unit; exactly one order is created and stock ends at zero.

### Docker

Two containers: `app` (Nginx + PHP-FPM + supervisord, serving the built SPA and proxying the API on one origin) and `db` (PostgreSQL). A single origin avoids CORS and keeps Sanctum's cookie and CSRF flow simple. A separate frontend container was considered and rejected as unnecessary for this scope.

## Security

- **Input validation** in the domain, in form requests, and as database CHECK constraints.
- **SQL injection:** all queries use bound parameters; `LIKE` wildcards in search are escaped.
- **XSS:** React escapes everything it renders; CSV script payloads are pinned by tests to render as text.
- **CSRF:** Sanctum's CSRF token on every state-changing request; login and register also require the first-party origin.
- **Authorization:** admin endpoints require the admin role; orders are scoped to their buyer (another buyer's order returns 404); cached order data is keyed by buyer, so one buyer never sees another's cache.
- **Payment data:** only brand and last four digits are stored; the idempotency hash never includes the card number or CVC.
- **Rate limiting** on login, registration and checkout.

## Known limitations and follow-ups

- **Asynchronous import** for files beyond the 5,000-row cap (queue + import status + Reverb push).
- **Server-side cart** for carts shared across devices.
- **Web Locks requires a secure context.** Checkout refuses to run without it rather than risk a double charge; served over HTTPS or localhost it works.
- **Multi-tab edge cases.** If another tab changes the signed-in role, the cart can show Checkout until the session is revalidated; the backend still rejects an unauthorized order. A payment key that never saw an outcome has no expiry.
- **Cart stock** is the value seen when the line was added; the final check happens at checkout.
- **Test and tooling polish:** pin GitHub Actions to commit SHAs; use fake timers in the "Added to cart" test; build the Docker image in CI.

## How AI was used

AI (Claude) was used as a pair programmer under my direction. I made the product and architecture decisions above after working through the trade-offs; the AI proposed options, implemented them test-first (red, green, refactor), and every change went through my review plus an automated local review (Gentle AI) and Codex reviews on each pull request before merging.

As the challenge asks, comments were removed from the code. The only annotations kept are the PHPDoc type tags (`@param`, `@return`, `@var`, ...) that Larastan level 9 needs to check types PHP cannot express natively, and tool directives such as `/* @vite-ignore */`. They describe types for tools, not behavior for readers.
