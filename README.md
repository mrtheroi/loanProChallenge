# LoanPro Code Challenge — E-commerce

Enterprise-grade e-commerce application: product CRUD, CSV import, product search, and purchase with a fake payment.

> Work in progress. Sections below are filled in as the project is built.

## Sample data

The example CSV provided by the challenge is stored at [`docs/sample-data/products.csv`](docs/sample-data/products.csv).

- **Downloaded on:** 2026-10-15
- The original file was shared as a spreadsheet; this CSV is its export, with the same 97 data rows.
- The file contains intentionally invalid rows (invalid prices, negative stock, blank names, injection payloads, duplicate SKUs). How each case is handled is documented in the decisions section.

## Running locally

_Full instructions will be documented as the project is built._

Requirements: Docker with Docker Compose v2.1.1 or later (the first release with `up --wait`).

The stack has two containers: `app` (Nginx, PHP-FPM, and the Laravel code in one image, run by supervisord) and `db` (PostgreSQL).

```bash
docker compose up -d --build --wait
curl http://localhost:8000/api/health
```

The application is served at `http://localhost:8000`, bound to localhost only. If port 8000 is already in use (for example by `php artisan serve`), change the published port in `docker-compose.yml` together with `APP_URL`.

Development mode mounts `./backend` into the container, so code changes apply without a rebuild:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build --wait
```

Backend tests run inside the container against a dedicated PostgreSQL database:

```bash
docker compose exec app ./vendor/bin/pest
```

The image builds the React frontend with Node 26 and Nginx serves it at `http://localhost:8000`. To work on the frontend with hot reload, start the backend in development mode (above) and run Vite on the host with Node 26 (`frontend/.nvmrc`); it proxies `/api` and `/sanctum` to port 8000:

```bash
cd frontend
npm ci
npm run dev        # http://localhost:5173
npm test           # Vitest + Testing Library + MSW
npm run typecheck  # tsc
npm run lint       # oxlint
```

Notes:

- If the build stalls with no output (an issue observed with the Compose Bake builder), run it with `COMPOSE_BAKE=false docker compose up -d --build --wait`.
- In development mode, `vendor` lives in the `backend-vendor` named volume, seeded from the image the first time it is created. After changing Composer dependencies, remove that volume (`docker compose -f docker-compose.yml -f docker-compose.dev.yml down -v`) and rebuild.
- In development mode, the entrypoint does not change file ownership on the mounted source. It makes `storage` and `bootstrap/cache` group- and world-writable instead, so PHP-FPM can write to them while the files stay owned by the host user. This is acceptable for local development only.
- If PHP-FPM or Nginx cannot be restarted, supervisord shuts down, the container exits, and Docker restarts it according to the `unless-stopped` policy.
- The `ecommerce_test` database is created only the first time the database volume is initialized. If the volume predates it, recreate the volume with `docker compose down -v`.
- The `APP_KEY` and database credentials in `docker-compose.yml` are for local development only.

### Demo accounts

The seeders run on every boot and create these two accounts only when they are missing. They never modify an existing account (a changed password or role is kept), and they create accounts only when `APP_ENV` is `local` or `testing` (any other environment, including running a single seeder with `--class`, creates nothing):

| Role | Email | Password |
|------|-------|----------|
| admin | `admin@example.com` | `password` |
| buyer | `buyer@example.com` | `password` |

These accounts are for local evaluation only. New sign-ups always get the buyer role; the admin account exists only through the seeder.

## Decisions, approach, and alternatives considered

_To be documented._
