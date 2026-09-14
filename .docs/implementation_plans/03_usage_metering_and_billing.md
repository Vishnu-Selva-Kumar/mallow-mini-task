# Implementation Plan: Usage Metering, Aggregation & Invoicing System

Build the high-throughput, idempotent usage-metering ingestion endpoint, queued & chunked daily aggregation and cycle-end invoicing jobs, Redis plan caching with invalidation, rate limiting, and comprehensive unit & feature testing. All code and artisan generators will strictly adhere to `.agents/rules/` (`commit_type.yml`, `laravel-docker.md`, `laravel-artisan-generation.md`).

---

## User Review Required

> [!IMPORTANT]
> **Suggested Branch Name**:
> `feat/usage-metering-and-billing`  
> *(Please confirm if you approve creating and checking out this branch from `feat/database-schema-and-models` or `main`.)*

> [!IMPORTANT]
> **High-Throughput & Idempotency Strategy**:
> - The `POST /usage` endpoint enforces idempotency at the database level via the unique index on `usage_events.idempotency_key` and at the application level via `UsageService`.
> - If a duplicated `idempotency_key` is received, the service will return an idempotent HTTP 200 response with the existing event record, preventing double-counting while preserving client resilience on network retries.

> [!NOTE]
> **50L+ Chunking Strategy**:
> - `AggregateDailyUsageJob` processes raw events in memory-safe chunks (`chunkById(5000)`) and writes rollups into `daily_usage_aggregates` using MySQL atomic `upsert()` on the composite key `(subscription_id, usage_date)`.
> - `GenerateInvoiceJob` chunks active subscriptions (`chunkById(500)`) to compute base and overage charges without exceeding PHP memory limits.

---

## Architecture Flow

```
POST /usage
    │
    ▼
RateLimiter ('usage-metering', 120 req/min)
    │
    ▼
UsageController::store()
    │
    ▼
StoreUsageRequest (Validation: customer_id, usage_date, units, idempotency_key)
    │
    ▼
RecordUsageDTO::fromRequest()
    │
    ▼
UsageService::record()
    ├── 1. Check customer exists (User)
    ├── 2. Check active subscription for customer
    ├── 3. Atomically insert or retrieve usage event (idempotency check)
    └── 4. Return UsageEvent entity / Result DTO
```

---

## Proposed Components & Implementation Order

### 1. API Route & Rate Limiting
- Install Laravel API routes standardly:
  ```bash
  docker exec mallow-laravel.test-1 php artisan install:api
  ```
- Configure Rate Limiter in `bootstrap/app.php` or `AppServiceProvider`:
  - 120 requests/minute per `customer_id` (fallback to IP).
- Register route:
  - `POST /api/usage` (and alias `/usage`): `UsageController::class, 'store'`.

### 2. DTO: Data Transfer Object
- **`app/DTOs/RecordUsageDTO.php`**:
  - Properties: `int $customerId`, `string $usageDate`, `int $units`, `string $idempotencyKey`.
  - Factory: `RecordUsageDTO::fromRequest(StoreUsageRequest $request)`.

### 3. Form Request & Controller
- Generated via Artisan:
  ```bash
  docker exec mallow-laravel.test-1 php artisan make:request StoreUsageRequest
  docker exec mallow-laravel.test-1 php artisan make:controller UsageController --api
  ```
- **`app/Http/Requests/StoreUsageRequest.php`**:
  - Validates `customer_id` (`exists:users,id`), `usage_date` (`date_format:Y-m-d`), `units` (`integer|min:1`), `idempotency_key` (`string|max:255`).
- **`app/Http/Controllers/UsageController.php`**:
  - Receives request, transforms to DTO, calls `UsageService`, returns structured JSON response (HTTP 201 for newly created, HTTP 200 for idempotent replay).

### 4. Services Layer
- **`app/Services/PlanCacheService.php`**:
  - Redis cache store with TTL 600s (10 minutes) matching wireframe specifications.
  - Methods: `getPlan(int $id)`, `getMerchantPlans(int $merchantId)`, `invalidate(Plan $plan)`.
- **`app/Services/UsageService.php`**:
  - Checks customer existence and active subscription.
  - Uses `firstOrCreate` / `UniqueConstraintViolationException` handling on `idempotency_key` to guarantee idempotency under high concurrency.
- **`app/Services/BillingService.php`**:
  - Computes cycle invoices:
    - **Proration**: Prorates base fee for days active in cycle.
    - **Mid-Cycle Plan Changes (Req 8)**: Breaks billing into segments using `subscription_periods`:
      - Segment 1: Usage before plan switch date vs. old plan allowance and overage rate.
      - Segment 2: Usage after plan switch date vs. new plan allowance and overage rate.
    - **Overage**: $\max(0, \text{Usage} - \text{Allowance}) \times \text{Overage Rate}$.
  - Creates and stores `Invoice` record with breakdown.

### 5. Jobs (Queued & Chunked)
- Generated via Artisan:
  ```bash
  docker exec mallow-laravel.test-1 php artisan make:job AggregateDailyUsageJob
  docker exec mallow-laravel.test-1 php artisan make:job GenerateInvoiceJob
  docker exec mallow-laravel.test-1 php artisan make:command AggregateDailyUsageCommand
  docker exec mallow-laravel.test-1 php artisan make:command GenerateInvoicesCommand
  ```
- **`app/Jobs/AggregateDailyUsageJob.php`**:
  - Aggregates daily usage in `chunkById(5000)`.
  - Performs bulk `upsert()` into `daily_usage_aggregates` on `(subscription_id, usage_date)`.
- **`app/Jobs/GenerateInvoiceJob.php`**:
  - Chunks active subscriptions (`chunkById(500)`) and invokes `BillingService`.

---

## Verification & Testing Plan

### 1. Automated Unit Tests
Generated via Artisan:
```bash
docker exec mallow-laravel.test-1 php artisan make:test BillingCalculationTest --unit
```
- **`BillingCalculationTest`**:
  - Standard monthly cycle with 0 overage.
  - Standard monthly cycle with overage.
  - Mid-cycle subscription start (base price proration).
  - Mid-cycle plan upgrade/downgrade (dual period segmentation & separate overage calculations).

### 2. Automated Feature Tests
Generated via Artisan:
```bash
docker exec mallow-laravel.test-1 php artisan make:test UsageIngestionTest
docker exec mallow-laravel.test-1 php artisan make:test DailyAggregationJobTest
docker exec mallow-laravel.test-1 php artisan make:test PlanCacheTest
```
- **`UsageIngestionTest`**:
  - Valid `POST /usage` returns HTTP 201 and stores record in `usage_events`.
  - Submitting same `idempotency_key` returns HTTP 200 and does **not** double count.
  - Invalid customer or invalid date returns HTTP 422.
  - Exceeding rate limit returns HTTP 429.
- **`DailyAggregationJobTest`**:
  - Simulates 5000+ usage events across multiple days.
  - Dispatches `AggregateDailyUsageJob` and verifies `daily_usage_aggregates` rollups match exact sum.
- **`PlanCacheTest`**:
  - Verifies Redis cache stores plan lookups.
  - Verifies cache invalidation when plan is updated.

### 3. Execution Commands
```bash
docker exec mallow-laravel.test-1 php artisan test
```
