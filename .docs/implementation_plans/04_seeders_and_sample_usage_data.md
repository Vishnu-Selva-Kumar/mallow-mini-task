# Implementation Plan: Seeders Expansion & 120,000 Usage Ingestion Simulation

## Goal Description
1. Update database seeders to fulfill requirements:
   - **MerchantSeeder**: 2 Merchants (`Acme Corp`, `Starlight Tech`).
   - **PlanSeeder**: 4 Plans (2 Plans per Merchant: Basic Plan & Premium Plan for each).
   - **UserSeeder**: 12 Users (corporate & SMB clients).
   - **SubscriptionSeeder**: 12 Subscriptions (assigning all 12 users to varied plans across both merchants).
   - **SubscriptionPeriodSeeder**: Seed active & historical periods for all 12 users.
   - **DatabaseSeeder**: Include `SubscriptionPeriodSeeder::class` in the default seed run.
2. Provide a scalable, robust mechanism to generate **10,000 sample usage records per user** (12 users × 10,000 = **120,000 records**) for `/api/usage` **without using database seeders**.

---

## Technical Solution: How to generate 120,000 usage records without Seeders?

### The Challenge with Standard HTTP & Seeders:
1. **Why not Seeders?** As requested, seeders bypass the application architecture (`UsageController`, `StoreUsageRequest`, `RecordUsageDTO`, `UsageService`, and idempotency logic).
2. **The Rate Limit Challenge with HTTP**: `/api/usage` is currently protected by `throttle:usage-metering` (120 req/minute). Sending 120,000 HTTP requests at 120 req/min would take **16.6 hours**!

### Proposed Approach: Custom Dedicated Artisan Command (`usage:simulate-traffic`)
We will build a high-performance CLI command:
```bash
php artisan usage:simulate-traffic --users=12 --records-per-user=10000
```

This command provides **two execution modes**:

#### Mode A: Pipeline Execution (Recommended & Default)
- Instantiates and processes records through the exact same application layer as `/api/usage`:
  `StoreUsageRequest` validated data $\rightarrow$ `RecordUsageDTO` $\rightarrow$ `UsageService::recordUsage($dto)`.
- Validates user existence, verifies active subscription, generates unique `idempotency_key`, and executes idempotent database persistence.
- Processes in memory-safe chunks (e.g. 1,000 events per chunk) with a Laravel Console Progress Bar.
- **Speed**: Generates and persists 120,000 records in **~20 to 45 seconds** without hitting rate limits or memory exhaustion.

#### Mode B: HTTP Client Mode (`--http`)
- For real network benchmark testing, the command dispatches concurrent asynchronous HTTP `POST` requests via Laravel's `Http::pool()` directly to `http://localhost:8800/api/usage`.
- An internal API benchmark bypass key (`X-Internal-Ingest-Key`) can be enabled so benchmark runs are not throttled by the 120 req/min rate limiter.

---

## Proposed Changes

### 1. Database Seeders

#### [MODIFY] [MerchantSeeder.php](file:///d:/mallow/database/seeders/MerchantSeeder.php)
- Ensure 2 merchant records (`Acme Corp`, `Starlight Tech`).

#### [MODIFY] [PlanSeeder.php](file:///d:/mallow/database/seeders/PlanSeeder.php)
- Seed 4 plans (2 for Merchant 1, 2 for Merchant 2):
  - Merchant 1: Basic Plan (₹999 / 50,000 units / ₹0.05 overage), Premium Plan (₹4,999 / 250,000 units / ₹0.03 overage).
  - Merchant 2: Basic Plan (₹1,299 / 60,000 units / ₹0.045 overage), Premium Plan (₹5,999 / 300,000 units / ₹0.025 overage).

#### [MODIFY] [UserSeeder.php](file:///d:/mallow/database/seeders/UserSeeder.php)
- Expand from 5 to 12 users with unique names and emails.

#### [MODIFY] [SubscriptionSeeder.php](file:///d:/mallow/database/seeders/SubscriptionSeeder.php)
- Create active subscriptions for all 12 users distributed across plans 1, 2, 3, and 4.

#### [MODIFY] [SubscriptionPeriodSeeder.php](file:///d:/mallow/database/seeders/SubscriptionPeriodSeeder.php)
- Seed `subscription_periods` for all 12 subscriptions covering current and previous billing cycles (including mid-cycle upgrades for demonstration).

#### [MODIFY] [DatabaseSeeder.php](file:///d:/mallow/database/seeders/DatabaseSeeder.php)
- Add `SubscriptionPeriodSeeder::class` to the seeder execution array.

---

### 2. Sample Usage Data Generator (Non-Seeder Feature)

#### [NEW] [SimulateUsageTrafficCommand.php](file:///d:/mallow/app/Console/Commands/SimulateUsageTrafficCommand.php)
- Create Artisan command: `usage:simulate-traffic`.
- Options:
  - `--users=12`: Number of active users to generate usage for.
  - `--records-per-user=10000`: Number of records per user (default: 10,000).
  - `--start-date=2026-09-01`: Start date range for usage.
  - `--end-date=2026-09-30`: End date range for usage.
  - `--http`: Optional flag to send via HTTP `POST /api/usage`.
- Distribution:
  - Spreads 10,000 records across dates in the range randomly.
  - Generates realistic unit consumption (e.g. between 1 and 250 units per event).
  - Generates unique idempotency keys (`sim-{user_id}-{date}-{index}-{hash}`).
  - Emits console progress bar and execution duration summary.

---

## Verification Plan

### Automated Tests
- Run `docker exec mallow-laravel.test-1 php artisan test` to ensure existing 20 unit and feature tests pass.
- Create a feature test `tests/Feature/SimulateUsageTrafficCommandTest.php` to verify the command runs and records usage correctly.

### Manual Verification in Docker
1. Refresh and seed database:
   ```bash
   docker exec mallow-laravel.test-1 php artisan migrate:fresh --seed
   ```
2. Verify seeded counts in tinker:
   ```bash
   docker exec mallow-laravel.test-1 php artisan tinker --execute="echo 'Merchants: ' . App\Models\Merchant::count() . ', Plans: ' . App\Models\Plan::count() . ', Users: ' . App\Models\User::count() . ', Subscriptions: ' . App\Models\Subscription::count() . ', Periods: ' . App\Models\SubscriptionPeriod::count();"
   ```
   Expected:
   - Merchants: 2
   - Plans: 4
   - Users: 12
   - Subscriptions: 12
   - Periods: $\ge 12$
3. Run the usage traffic generator:
   ```bash
   docker exec mallow-laravel.test-1 php artisan usage:simulate-traffic --records-per-user=10000
   ```
4. Verify record count in database:
   ```bash
   docker exec mallow-laravel.test-1 php artisan tinker --execute="echo 'Total Usage Events: ' . App\Models\UsageEvent::count();"
   ```
   Expected: 120,000 events.
