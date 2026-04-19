# tububox - Delivery Fulfillment SaaS (MVP)

API-first, multi-tenant delivery fulfillment platform built with PHP 8 and PostgreSQL.

Role model (fixed):

- `user`
- `merchant`
- `rider`
- `operator`
- `admin`

Order sources (fixed):

- `marketplace`
- `merchant_dashboard`
- `merchant_api`

Current Phase 1 implementation includes:

- Project skeleton with layered architecture (`Controller / Service / Repository / Middleware`)
- PostgreSQL migration and seed scripts
- Session login, role middleware, API key authentication
- Core delivery APIs (create/list/detail/cancel)
- Dispatch APIs and rider fulfillment APIs (accept/pickup/sign/complete/COD/tracking)
- Web UI baseline (login, dashboard, merchant delivery pages, dispatch page, rider H5 page)

Phase 2 hardening completed:

- Tenant scope middleware for authenticated routes
- Typed API exceptions and unified error payload (`error_code`, `meta.request_id`)
- Session security baseline (session id rotation, fingerprint check, idle timeout)

Phase 3 features completed:

- Webhook endpoint management (Web UI + API): create/list/update/delete
- Outbound webhook signing support (`X-Tubu-Timestamp`, `X-Tubu-Signature`)
- Dispatch UI action closure: assign, reassign, mark-failed
- Integration test script for idempotency, state machine, tenant isolation, dispatch flow, webhook endpoint ACL

## 1. Requirements

- PHP `>= 8.1` (8.2+ recommended)
- PostgreSQL `>= 14`
- PHP extension: `pdo_pgsql`

## 2. Quick Start

1. Copy environment file:

```bash
cp .env.example .env
```

2. Update database connection in `.env`.

3. Run migrations:

```bash
php bin/migrate.php
```

4. Seed demo data:

```bash
php bin/seed.php
```

5. Start local server:

```bash
php -S 127.0.0.1:8080 -t public public/router.php
```

6. Open:

- [http://127.0.0.1:8080/login](http://127.0.0.1:8080/login)

## 3. Default Accounts

- Admin: `admin@tububox.local / admin123`
- Merchant: `merchant@tububox.local / admin123`
- Operator: `operator@tububox.local / admin123`
- Rider: `rider@tububox.local / admin123`
- Marketplace User: `user@tububox.local / admin123`

## 4. Demo API Key

Use request headers:

- `X-API-KEY: demo_key`
- `X-API-SECRET: demo_secret`

## 5. Key APIs (MVP)

- `POST /api/v1/deliveries`
- `GET /api/v1/deliveries`
- `GET /api/v1/deliveries/{id}`
- `POST /api/v1/deliveries/{id}/cancel`
- `POST /api/v1/marketplace/orders`
- `POST /api/v1/dispatch/assign`
- `POST /api/v1/dispatch/reassign`
- `POST /api/v1/dispatch/mark-failed`
- `POST /api/v1/rider/deliveries/{id}/accept`
- `POST /api/v1/rider/deliveries/{id}/arrive-pickup`
- `POST /api/v1/rider/deliveries/{id}/pickup`
- `POST /api/v1/rider/deliveries/{id}/arrive-dropoff`
- `POST /api/v1/rider/deliveries/{id}/sign`
- `POST /api/v1/rider/deliveries/{id}/complete`
- `POST /api/v1/rider/deliveries/{id}/cod-collect`
- `POST /api/v1/rider/location`
- `POST /api/v1/driver/deliveries/{id}/accept`
- `POST /api/v1/driver/deliveries/{id}/arrive-pickup`
- `POST /api/v1/driver/deliveries/{id}/pickup`
- `POST /api/v1/driver/deliveries/{id}/arrive-dropoff`
- `POST /api/v1/driver/deliveries/{id}/sign`
- `POST /api/v1/driver/deliveries/{id}/complete`
- `POST /api/v1/driver/deliveries/{id}/cod-collect`
- `POST /api/v1/driver/location`
- `GET /api/v1/webhook-endpoints`
- `POST /api/v1/webhook-endpoints`
- `POST /api/v1/webhook-endpoints/{id}/update`
- `POST /api/v1/webhook-endpoints/{id}/delete`

API response contract:

- Success: `success`, `message`, `data`, `meta`
- Error: `success`, `message`, `error_code`, `errors`, `meta`

Outbound webhook signature:

- `X-Tubu-Timestamp: <unix-seconds>`
- `X-Tubu-Signature: sha256=<hex-hmac>`
- Signature payload: `<timestamp>.<json-body>`

## 7. Integration Test (Phase 3)

Run:

```bash
php bin/test-phase3.php
```

Notes:

- Uses current PostgreSQL from `.env`
- Requires migrations to be applied first
- Runs in a DB transaction and rolls back automatically

## 6. Folder Layout

```text
app/
  Controllers/
  Services/
  Domain/
  Repositories/
  Middlewares/
  Requests/
  Policies/
  Views/
bootstrap/
config/
database/
  migrations/
  seeders/
public/
  assets/
routes/
storage/
tests/
doc/
  MASTER_SPEC.md
  API_SPEC.md
  DB_SCHEMA.sql
```
