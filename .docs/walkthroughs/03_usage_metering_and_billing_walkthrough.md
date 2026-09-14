# Walkthrough: Usage Metering, Daily Aggregation & Billing Engine

## Overview
This walkthrough summarizes the implementation of the high-throughput, idempotent usage metering ingestion pipeline, the Redis plan caching layer, the chunked daily rollup and invoice generation background jobs (using Eloquent models and the ERD approach), and the mid-cycle prorated billing engine with complete unit and feature test verification.

---

## Changes Implemented

### 1. High-Throughput & Idempotent Ingestion Endpoint
- **DTO**: [RecordUsageDTO](file:///d:/mallow/app/DTOs/RecordUsageDTO.php) — Strongly typed transfer object for ingestion payload (`userId`, `usageDate`, `units`, `idempotencyKey`).
- **Form Request**: [StoreUsageRequest](file:///d:/mallow/app/Http/Requests/StoreUsageRequest.php) — Validates user existence (`user_id`), `Y-m-d` date format, positive unit requirement, and idempotency key.
- **Service**: [UsageService](file:///d:/mallow/app/Services/UsageService.php) — Implements dual-layer idempotency protection:
  - First-check via query for `idempotency_key` (returns HTTP 200 without double counting).
  - Handles concurrent race conditions by catching `UniqueConstraintViolationException` on the DB unique index.
  - Verifies user existence and active subscription presence.
- **Controller**: [UsageController](file:///d:/mallow/app/Http/Controllers/UsageController.php) — Returns HTTP 201 Created for new events, HTTP 200 OK for idempotent replays, using `user_id` in response data.
- **Rate Limiter & Route**:
  - [AppServiceProvider](file:///d:/mallow/app/Providers/AppServiceProvider.php) — Configured `RateLimiter::for('usage-metering')` limiting to 120 requests/minute (keyed by API key, `user_id`, or IP).
  - [web.php](file:///d:/mallow/routes/web.php) — Consolidated to a single clean route: `POST /api/usage` wrapped in `throttle:usage-metering`.

### 2. Plan & Pricing Caching (Redis)
- [PlanCacheService](file:///d:/mallow/app/Services/PlanCacheService.php) — Caches plans by `plan:{id}` and merchant plans by `merchant:{id}:plans` in Redis with 10-minute (600s) TTL.
- Provides atomic cache invalidation via `invalidate(Plan $plan)` and `invalidateById(int $id)`.

### 3. Scalable Aggregation & Invoicing Background Jobs (Eloquent / ERD Approach)
- **Daily Rollup**: [AggregateDailyUsageJob](file:///d:/mallow/app/Jobs/AggregateDailyUsageJob.php) — Uses the [UsageEvent](file:///d:/mallow/app/Models/UsageEvent.php) Eloquent model query to group events by `(subscription_id, user_id, usage_date)` in memory-safe `chunk(5000)` batches and upserts directly into [DailyUsageAggregate](file:///d:/mallow/app/Models/DailyUsageAggregate.php) with composite unique index `(subscription_id, usage_date)`.
- **Artisan Command**: [AggregateDailyUsageCommand](file:///d:/mallow/app/Console/Commands/AggregateDailyUsageCommand.php) (`php artisan usage:aggregate-daily {--date=}`).
- **Invoice Generation**: [GenerateInvoiceJob](file:///d:/mallow/app/Jobs/GenerateInvoiceJob.php) — Generates cycle-end invoices processing active subscriptions via Eloquent in memory-safe `chunkById(500)` batches.
- **Artisan Command**: [GenerateInvoicesCommand](file:///d:/mallow/app/Console/Commands/GenerateInvoicesCommand.php) (`php artisan billing:generate-invoices {--subscription=} {--start=} {--end=}`).

### 4. Billing Engine with Proration & Mid-Cycle Upgrades
- [BillingService](file:///d:/mallow/app/Services/BillingService.php):
  - Normalized date calculations to prevent fractional day rounding issues.
  - Supports mid-cycle subscription starts (prorates base price and included usage allowance).
  - Supports mid-cycle plan upgrades and downgrades by segmenting the billing cycle across [SubscriptionPeriod](file:///d:/mallow/app/Models/SubscriptionPeriod.php) records.
  - Calculates tier-specific overage fees when usage exceeds prorated included units.

---

## Verification & Test Results

All tests were executed inside the Docker container runtime (`docker exec mallow-laravel.test-1 php artisan test`).

### Test Summary
- **Tests**: 20 passed (0 failed)
- **Assertions**: 179 assertions passed

```
   PASS  Tests\Unit\BillingCalculationTest
  ✓ it calculates zero overage when usage within allowance               8.89s  
  ✓ it calculates overage charges correctly when usage exceeds allowance 0.26s  
  ✓ it calculates prorated base price for mid cycle subscription start   0.23s  
  ✓ it handles mid cycle plan upgrade with segmented rates and allowances 0.22s  

   PASS  Tests\Unit\ExampleTest
  ✓ that true is true                                                    0.04s  

   PASS  Tests\Feature\DailyAggregationJobTest
  ✓ it aggregates usage events into daily aggregates                     0.32s  
  ✓ it is idempotent when daily aggregation runs multiple times          0.21s  
  ✓ it runs artisan command usage aggregate daily                        0.75s  

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response                        1.31s  

   PASS  Tests\Feature\InvoiceGenerationTest
  ✓ it generates invoice for active subscription at cycle end            0.33s  
  ✓ it runs artisan billing generate invoices command                    0.23s  

   PASS  Tests\Feature\PlanCacheTest
  ✓ it caches plan and retrieves from cache                              0.27s  
  ✓ it invalidates plan cache properly                                   0.23s  
  ✓ it caches and invalidates merchant plans collection                  0.21s  

   PASS  Tests\Feature\UsageIngestionTest
  ✓ it successfully ingests usage event and returns 201                  0.64s  
  ✓ it replays idempotent event with 200 ok and does not create duplicate 0.39s  
  ✓ it fails validation when required fields are missing                 0.36s  
  ✓ it fails validation when units are non positive                      0.25s  
  ✓ it fails when user has no active subscription                        0.30s  
  ✓ it enforces rate limiting at 120 requests                            1.57s  

  Tests:    20 passed (179 assertions)
  Duration: 24.93s
```
