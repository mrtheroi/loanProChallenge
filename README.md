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

Requirements: Docker with Compose v2 or later.

```bash
docker compose up -d --build --wait
curl http://localhost:8080/api/health
```

The application is served at `http://localhost:8080`, bound to localhost only.

Notes:

- If the build stalls with no output (an issue observed with the Compose Bake builder), run it with `COMPOSE_BAKE=false docker compose up -d --build --wait`.
- Backend tests run inside the container against a dedicated PostgreSQL database: `docker compose exec api ./vendor/bin/pest`.
- The `ecommerce_test` database is created only the first time the database volume is initialized. If the volume predates it, recreate the volume with `docker compose down -v`.
- The `APP_KEY` and database credentials in `docker-compose.yml` are for local development only.

## Decisions, approach, and alternatives considered

_To be documented._
