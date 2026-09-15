# API & CLI Command Reference

This document provides complete documentation and payload specifications for all REST API endpoints and Artisan CLI commands.

---

## REST API Reference

### 1. Usage Ingestion Endpoint (`POST /api/usage`)

High-throughput, strictly idempotent usage recording endpoint.

- **URL**: `/api/usage`
- **Method**: `POST`
- **Headers**: `Content-Type: application/json`, `Accept: application/json`
- **Rate Limit**: 120 requests/minute per IP / user token

#### Request Payload

```json
{
  "user_id": 1,
  "usage_date": "2026-09-14",
  "units": 150,
  "idempotency_key": "evt-u1-20260914-99882"
}
```

| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `user_id` | `integer` | Yes | ID of the customer performing usage |
| `usage_date` | `date (YYYY-MM-DD)` | Yes | Date on which usage occurred |
| `units` | `integer` | Yes | Positive integer (> 0) representing consumed units |
| `idempotency_key` | `string` | Yes | Unique UUID or client string ensuring atomic deduplication |

#### Response: HTTP 201 Created (New Event)

```json
{
  "success": true,
  "message": "Usage event recorded successfully.",
  "data": {
    "id": 1,
    "user_id": 1,
    "subscription_id": 1,
    "usage_date": "2026-09-14",
    "units": 150,
    "idempotency_key": "evt-u1-20260914-99882",
    "created_at": "2026-09-14T13:45:00.000000Z"
  }
}
```

#### Response: HTTP 200 OK (Idempotent Replay)

Sending the exact same `idempotency_key` returns the existing record without duplicating units:

```json
{
  "success": true,
  "message": "Usage event already recorded (idempotent response).",
  "data": {
    "id": 1,
    "user_id": 1,
    "subscription_id": 1,
    "usage_date": "2026-09-14",
    "units": 150,
    "idempotency_key": "evt-u1-20260914-99882",
    "created_at": "2026-09-14T13:45:00.000000Z"
  }
}
```

---

## Artisan CLI Commands Reference

All commands must be run inside Docker container `mallow-laravel.test-1`.

### 1. High-Throughput Traffic Simulator (`usage:simulate-traffic`)

Simulates realistic, high-throughput traffic through the DTO and service layer **without using database seeders**:

```bash
docker exec mallow-laravel.test-1 php artisan usage:simulate-traffic --users=12 --records-per-user=1000
```

- Ingests **12,000 usage records** across 12 active users.
- Validates rate-limiter resilience, transaction throughput (>13,000 req/sec), and unique idempotency generation.

#### Simulate Previous Month Traffic (Churn Risk Testing)

```bash
docker exec mallow-laravel.test-1 php artisan usage:simulate-traffic --users=5 --records-per-user=35000 --start-date=2026-08-01 --end-date=2026-08-31
```

- Ingests 35,000 events in August for customer #1 to trigger a >50% MoM churn risk alert on the Merchant Dashboard.

---

### 2. Queued Daily Usage Aggregation (`usage:aggregate-daily`)

Rolls up raw usage events into read-optimized `daily_usage_aggregates` records using memory-safe **5,000-row chunks**:

```bash
# Aggregate a specific date
docker exec mallow-laravel.test-1 php artisan usage:aggregate-daily --date=2026-09-14

# Aggregate full month (PowerShell example)
1..30 | ForEach-Object { $d = "2026-09-{0:D2}" -f $_; docker exec mallow-laravel.test-1 php artisan usage:aggregate-daily --date=$d }
```

---

### 3. Cycle-End Invoice Generation (`billing:generate-invoices`)

Calculates base proration, segment allowances, and overage charges for subscriptions in **500-subscription chunks**:

```bash
# Generate invoices for all active subscriptions
docker exec mallow-laravel.test-1 php artisan billing:generate-invoices --start=2026-09-01 --end=2026-09-30

# Generate invoice for a specific subscription
docker exec mallow-laravel.test-1 php artisan billing:generate-invoices --subscription=1 --start=2026-09-01 --end=2026-09-30
```

---

### 4. Mid-Cycle Plan Changes (`subscription:change-plan`)

Supports multi-period subscription phases (Requirement 8). Segments usage before vs. after plan change date, calculating independent allowances, segregated overages, and prorated base amounts:

```bash
docker exec mallow-laravel.test-1 php artisan subscription:change-plan {subscription_id} {target_plan_id} --date={YYYY-MM-DD}
```

#### Example: Upgrading Subscription #2 on Mid-Cycle Date (2026-09-16)

```bash
docker exec mallow-laravel.test-1 php artisan subscription:change-plan 2 2 --date=2026-09-16
```

**Terminal Output & Breakdown:**

```text
Initiating mid-cycle plan change...
• Customer: Craft Foods Co. (craft@example.com)
• Current Plan: Basic Plan (Base: ₹999.00, Included: 50000, Overage: ₹0.0500/unit)
• Target Plan: Premium Plan (Base: ₹4999.00, Included: 250000, Overage: ₹0.0300/unit)
• Effective Date: 2026-09-16

✔ Plan successfully updated to Premium Plan [UPGRADE]

Segregated Billing Breakdown (2026-09-01 to 2026-09-30):
+---------+--------------+-----------------+---------------+--------------------+---------------+---------------+-------------+
| Phase   | Plan         | Active Duration | Prorated Base | Prorated Allowance | Units Used    | Overage Units | Overage Fee |
+---------+--------------+-----------------+---------------+--------------------+---------------+---------------+-------------+
| Phase 1 | Basic Plan   | 15 days         | ₹ 499.50      | 25,000 units       | 759,103 units | 734,103 units | ₹ 36,705.15 |
| Phase 2 | Premium Plan | 15 days         | ₹ 2,499.50    | 125,000 units      | 761,065 units | 636,065 units | ₹ 19,081.95 |
+---------+--------------+-----------------+---------------+--------------------+---------------+---------------+-------------+

  Total Prorated Base:      ₹ 2,999.00
  Total Overage Fees:       ₹ 55,787.10
  Total Projected Invoiced: ₹ 58,786.10
```

---

[← Back to Main README](../README.md)
