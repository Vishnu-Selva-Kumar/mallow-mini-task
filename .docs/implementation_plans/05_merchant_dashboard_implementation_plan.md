# Implementation Plan: Merchant Analytics Dashboard (UI & API)

Build the **Merchant Dashboard** (`GET /merchants/{id}/dashboard`) providing real-time business insights, high-level metric cards, top customers by usage, churn risk alerts (>50% MoM drop), 30-day chronological daily usage trends, and a system status widget, along with a comprehensive automated test suite.

## Suggested Branch Name

`feat/merchant-analytics-dashboard`

---

## User Review Required

> [!IMPORTANT]
> **Dual Rendering Strategy**:
> - **Web Browser (`text/html`)**: Renders a rich, modern Blade UI layout with metric cards, progress bars, interactive Chart.js 30-day trend graph, churn risk badges, and system status widgets matching the wireframe specification.
> - **API Consumer (`application/json`)**: Returns structured JSON with all computed analytics, making the endpoint versatile for frontend SPA/mobile consumers and automated testing.

> [!NOTE]
> **Month-over-Month (MoM) Churn Risk Formula**:
> A customer is flagged under churn risk if:
> $$\text{Usage Drop} = \frac{\text{Previous Cycle Usage} - \text{Current Cycle Usage}}{\text{Previous Cycle Usage}} > 0.50 \quad (50\%)$$
> Customers with no previous cycle activity or zero usage in both periods are excluded to prevent false positives.

---

## Proposed Changes

Following `.agents/rules/laravel-artisan-generation.md` and `.agents/rules/laravel-docker.md`:
- All controllers and test classes will be generated via Artisan commands executed in the running Docker container (`docker exec mallow-laravel.test-1 php artisan ...`).
- Business logic is strictly kept out of controllers, encapsulated inside a dedicated `MerchantDashboardService` and DTOs.

### Domain & Service Layer

#### [NEW] [MerchantDashboardService.php](file:///d:/mallow/app/Services/MerchantDashboardService.php)
- Encapsulates query logic for calculating merchant-wide analytics:
  - `getCurrentCycleMetrics(Merchant $merchant)`: Aggregates current cycle units used across all active subscriptions vs. total plan allowances, and calculates projected overage revenue ($ / ₹).
  - `getTopCustomersByUsage(Merchant $merchant, int $limit = 5)`: Fetches top 5 users by usage in the current cycle with percentage of their plan's allowance.
  - `getChurnRiskCustomers(Merchant $merchant)`: Compares current 30-day cycle vs. previous 30-day cycle per user; flags those with > 50% usage drop.
  - `get30DayUsageTrend(Merchant $merchant)`: Queries `daily_usage_aggregates` (or `usage_events`) for the last 30 consecutive days, filling missing dates with 0 units for a continuous timeline.
  - `getSystemStatus()`: Verifies live Redis connection (`Redis::ping()`), cache TTL configuration, rate limits (120 req/min), and aggregation chunk size (5,000).

#### [NEW] [DashboardMetricsDTO.php](file:///d:/mallow/app/DTOs/DashboardMetricsDTO.php)
- Immutable Data Transfer Object containing the parsed and structured dashboard metrics.

---

### HTTP & Presentation Layer

#### [NEW] [MerchantDashboardController.php](file:///d:/mallow/app/Http/Controllers/MerchantDashboardController.php)
- Created via Artisan:
  ```bash
  docker exec mallow-laravel.test-1 php artisan make:controller MerchantDashboardController
  ```
- Method `show(Request $request, Merchant $merchant)`:
  - Calls `MerchantDashboardService::getDashboardData($merchant)`.
  - If `$request->wantsJson()` or path is `/api/*`, returns `response()->json(['success' => true, 'data' => $data])`.
  - Otherwise, returns `view('dashboard.merchant', compact('merchant', 'data'))`.

#### [MODIFY] [routes/web.php](file:///d:/mallow/routes/web.php)
- Register dashboard routes:
  - `GET /merchants/{merchant}/dashboard` (Web view & content-negotiated JSON).
  - `GET /api/merchants/{merchant}/dashboard` (Explicit API route).

#### [NEW] [merchant.blade.php](file:///d:/mallow/resources/views/dashboard/merchant.blade.php)
- Premium, high-converting Blade dashboard view matching the wireframe:
  - **Header & Merchant Switcher**: Displays current merchant ("Acme Corp" / "Starlight Tech"), active plan badge, and cycle period.
  - **3 Key Metric Cards**:
    - Current Cycle Usage (e.g. `184,320 / 250,000 units` with colored progress bar).
    - Projected Overage Revenue (e.g. `₹ 42,600` or `$42,600.00`).
    - Active Plan details (Plan name, interval, subscriber count).
  - **Top 5 Customers by Usage Table**: User name, usage units, plan allowance, and usage percentage bar.
  - **Churn Risk Alert Panel**: Warning indicator, customers with >50% MoM drop, previous vs. current units, and drop percentage badge.
  - **30-Day Daily Usage Trend Graph**: Interactive line/area chart using Chart.js with responsive gradient fill and hover tooltips.
  - **System Status Informational Widget**: Live Redis cache status (TTL 10m), nightly aggregation job status (5,000 batch), and rate limit badge (120 req/min).

---

### Automated Testing Suite (PHPUnit)

#### [NEW] [MerchantDashboardTest.php](file:///d:/mallow/tests/Feature/MerchantDashboardTest.php)
- Created via Artisan:
  ```bash
  docker exec mallow-laravel.test-1 php artisan make:test MerchantDashboardTest --feature
  ```
- Test scenarios:
  1. `it returns 200 ok and renders dashboard view for merchant` (Web request).
  2. `it returns structured json dashboard data with all required metrics` (JSON request).
  3. `it correctly calculates current cycle usage and allowance percentage`.
  4. `it accurately projects overage revenue for customers exceeding allowance`.
  5. `it identifies top 5 customers ordered by usage descending`.
  6. `it accurately flags churn risk customers with greater than 50% MoM usage drop`.
  7. `it does not flag customers with less than 50% usage drop as churn risk`.
  8. `it returns 30 consecutive days of chronological usage trend data`.
  9. `it returns 404 for non-existent merchant`.

---

## Verification Plan

### Automated Tests
Execute full test suite inside Docker:
```bash
docker exec mallow-laravel.test-1 php artisan test --filter=MerchantDashboardTest
docker exec mallow-laravel.test-1 php artisan test
```

### Manual Verification
1. **API Validation**:
   ```bash
   docker exec mallow-laravel.test-1 php artisan route:list --name=dashboard
   curl -H "Accept: application/json" http://localhost:8800/merchants/1/dashboard
   ```
2. **Web Browser UI Validation**:
   - Access `http://localhost:8800/merchants/1/dashboard` in browser.
   - Verify metric cards, top 5 customers progress bars, churn risk alerts, 30-day Chart.js rendering, and system status widgets.
