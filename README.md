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
curl http://localhost:8080/api/health
```

The application is served at `http://localhost:8080`, bound to localhost only.

Development mode mounts `./backend` into the container, so code changes apply without a rebuild:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build --wait
```

Backend tests run inside the container against a dedicated PostgreSQL database:

```bash
docker compose exec app ./vendor/bin/pest
```

Notes:

- If the build stalls with no output (an issue observed with the Compose Bake builder), run it with `COMPOSE_BAKE=false docker compose up -d --build --wait`.
- In development mode, `vendor` lives in the `backend-vendor` named volume, seeded from the image the first time it is created. After changing Composer dependencies, remove that volume (`docker compose -f docker-compose.yml -f docker-compose.dev.yml down -v`) and rebuild.
- The `ecommerce_test` database is created only the first time the database volume is initialized. If the volume predates it, recreate the volume with `docker compose down -v`.
- The `APP_KEY` and database credentials in `docker-compose.yml` are for local development only.

## Decisions, approach, and alternatives considered

_To be documented._
