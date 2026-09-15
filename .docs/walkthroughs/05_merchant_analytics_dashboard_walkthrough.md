# Walkthrough: Merchant Analytics Dashboard & Automated Test Suite

## Overview
Implemented the **Merchant Dashboard** (`GET /merchants/{merchant}/dashboard`) and its API companion (`GET /api/merchants/{merchant}/dashboard`) adhering to the Senior Developer Mini Task Wireframe Reference specifications.

---

## 1. Key Components Implemented

### Domain & Service Layer
- **[MerchantDashboardService](file:///d:/mallow/app/Services/MerchantDashboardService.php)**:
  - Aggregates current cycle usage and plan allowance across all active subscriptions under the merchant.
  - Computes real-time projected overage revenue ($ / ₹) billable at cycle end.
  - Queries Top 5 customers ordered by usage descending, computing percentage of plan allowance consumed.
  - Detects **Month-over-Month (MoM) Churn Risk**: Flags customers whose usage dropped by more than 50% between consecutive cycles (`drop > 50%`).
  - Generates a continuous **30-Day Chronological Daily Usage Trend** (filling zero-usage days).
  - Collects live system status: Redis connection (`Redis::ping()`), cache TTL (10m), nightly aggregation chunk size (5,000), and rate limiter status (120 req/min).
- **[DashboardMetricsDTO](file:///d:/mallow/app/DTOs/DashboardMetricsDTO.php)**:
  - Immutable Data Transfer Object structuring all dashboard analytics.
- **[PlanCacheService](file:///d:/mallow/app/Services/PlanCacheService.php)**:
  - Added safe type-checking and eviction safeguards against stale or malformed cache entries.

### HTTP & UI Layer
- **[MerchantDashboardController](file:///d:/mallow/app/Http/Controllers/MerchantDashboardController.php)**:
  - Created via `php artisan make:controller MerchantDashboardController`.
  - Supports content negotiation: renders Blade view for browser requests (`text/html`) and returns structured JSON for `Accept: application/json` or `/api/*` paths.
- **[routes/web.php](file:///d:/mallow/routes/web.php)**:
  - Registered `GET /merchants/{merchant}/dashboard` and `GET /api/merchants/{merchant}/dashboard`.
- **[merchant.blade.php](file:///d:/mallow/resources/views/dashboard/merchant.blade.php)**:
  - High-end SaaS dashboard featuring:
    - Merchant switcher dropdown / selector ("Acme Corp" & "Starlight Tech").
    - 3 Key Metric Cards: Current Cycle Usage with animated progress bar, Projected Overage Revenue, and Active Plan details.
    - 30-Day Chronological Daily Usage Trend interactive Chart.js line graph.
    - Top 5 Customers by Usage Table with customer details and allowance progress bars.
    - Churn Risk Alert Panel with drop percentage badges and historical comparisons.
    - System Architecture & Operational Health widget.

---

## 2. Automated Test Suite (PHPUnit)

Implemented **[MerchantDashboardTest](file:///d:/mallow/tests/Feature/MerchantDashboardTest.php)** covering all specifications:
1. `it renders html dashboard view for merchant`
2. `it returns structured json dashboard data`
3. `it correctly calculates current cycle usage and allowance`
4. `it accurately projects overage revenue for customers exceeding allowance`
5. `it returns top 5 customers ordered by usage descending`
6. `it accurately flags churn risk when usage drops over 50 percent mom`
7. `it does not flag customer as churn risk when drop is under 50 percent`
8. `it returns 30 consecutive days of usage trend`
9. `it returns 404 for non existent merchant`

---

## 3. Test Verification Results

Inside Docker container (`mallow-laravel.test-1`):

```
PASS  Tests\Unit\BillingCalculationTest (4 tests)
PASS  Tests\Unit\ExampleTest (1 test)
PASS  Tests\Feature\DailyAggregationJobTest (3 tests)
PASS  Tests\Feature\ExampleTest (1 test)
PASS  Tests\Feature\InvoiceGenerationTest (2 tests)
PASS  Tests\Feature\MerchantDashboardTest (9 tests)
PASS  Tests\Feature\PlanCacheTest (3 tests)
PASS  Tests\Feature\SimulateUsageTrafficCommandTest (2 tests)
PASS  Tests\Feature\UsageIngestionTest (6 tests)

Tests:    31 passed (255 assertions)
Duration: 19.57s
```

All 31 tests passed with 255 assertions!
