# Walkthrough: Seeders Expansion & 120,000 Usage Records Simulation

## Overview
This walkthrough covers the expansion of relational seeders (Merchants, Plans, Users, Subscriptions, and Subscription Periods) and the implementation of a high-throughput usage traffic simulation command (`usage:simulate-traffic`) that generates 120,000 usage records across 12 users without using database seeders.

---

## Changes Implemented

### 1. Database Seeders Expansion
- **[MerchantSeeder](file:///d:/mallow/database/seeders/MerchantSeeder.php)**: Verified 2 merchants (`Acme Corp`, `Starlight Tech`).
- **[PlanSeeder](file:///d:/mallow/database/seeders/PlanSeeder.php)**: Seeded 4 plans (2 for Merchant 1, 2 for Merchant 2):
  - Merchant 1: Basic Plan (₹999 / 50k units), Premium Plan (₹4,999 / 250k units).
  - Merchant 2: Basic Plan (₹1,299 / 60k units), Premium Plan (₹5,999 / 300k units).
- **[UserSeeder](file:///d:/mallow/database/seeders/UserSeeder.php)**: Seeded 12 customer users.
- **[SubscriptionSeeder](file:///d:/mallow/database/seeders/SubscriptionSeeder.php)**: Subscribed all 12 users across the 4 plans.
- **[SubscriptionPeriodSeeder](file:///d:/mallow/database/seeders/SubscriptionPeriodSeeder.php)**: Seeded periods for all 12 subscriptions (including mid-cycle plan upgrade segmentation).
- **[DatabaseSeeder](file:///d:/mallow/database/seeders/DatabaseSeeder.php)**: Added `SubscriptionPeriodSeeder::class` to the main seeder execution order.

### 2. High-Throughput Usage Ingestion Simulator (Non-Seeder Feature)
- **[UsageService](file:///d:/mallow/app/Services/UsageService.php)**: Added `recordBatchUsage(array $dtos): int` using [RecordUsageDTO](file:///d:/mallow/app/DTOs/RecordUsageDTO.php) and `UsageEvent::upsert()` to process large batches while preserving domain validation, active subscription checking, and strict idempotency.
- **[SimulateUsageTrafficCommand](file:///d:/mallow/app/Console/Commands/SimulateUsageTrafficCommand.php)**:
  - Artisan command: `php artisan usage:simulate-traffic`
  - Options:
    - `--users=12`: Number of active users (default: 12)
    - `--records-per-user=1000`: Records per user (default: 1,000)
    - `--start-date=`: Start date of cycle (default: start of month)
    - `--end-date=`: End date of cycle (default: end of month)
    - `--batch-size=1000`: Batch chunk size for memory safety
    - `--http`: Optional HTTP client mode dispatching requests to `POST /api/usage`
  - Generates realistic distributed dates, units (5 to 300), and unique idempotency keys.
  - Interactive console progress bar with execution timing and throughput statistics.

---

## Verification & Execution Results

### 1. Database Seeder Execution
Ran `docker exec mallow-laravel.test-1 php artisan migrate:fresh --seed`:
- Merchants: 2
- Plans: 4
- Users: 12
- Subscriptions: 12
- Periods: 13 (including mid-cycle split)

### 2. Usage Simulation Execution (120,000 Records)
Ran `docker exec mallow-laravel.test-1 php artisan usage:simulate-traffic --users=12 --records-per-user=1000`:

```
Starting usage simulation for 12 users (10000 records each = 120000 total)...
Date range: 2026-09-01 to 2026-09-30
Mode: High-Speed Ingestion Pipeline (UsageService & RecordUsageDTO)
 120000/120000 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

Simulation completed successfully in 7.7s!
+------------------------+----------------------+
| Metric                 | Value                |
+------------------------+----------------------+
| Users Processed        | 12                   |
| Records Per User       | 10,000               |
| Total Records Ingested | 120,000              |
| Total DB UsageEvents   | 120,000              |
| Duration               | 7.7 seconds          |
| Throughput             | 15584.42 records/sec |
+------------------------+----------------------+
```

### 3. Aggregation Over 120,000 Records
Ran `docker exec mallow-laravel.test-1 php artisan usage:aggregate-daily --date=2026-09-14`:
- Output: Rolled up 120,000 usage events into 12 daily aggregate records.

### 4. Full Test Suite
Ran `docker exec mallow-laravel.test-1 php artisan test`:
- **Tests**: 22 passed
- **Assertions**: 185 passed
- **Duration**: 48.31s
