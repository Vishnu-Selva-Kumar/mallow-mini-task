# Merchant Analytics Dashboard

The Merchant Analytics Dashboard provides real-time business telemetry, high-level metric cards, top customers by usage, churn risk alerts, 30-day daily usage trends, and system health status.

---

## 1. Accessing the Dashboard

The dashboard supports content negotiation:
- **Web Browser (`text/html`)**: Renders a high-end Blade user interface with Chart.js visualization.
- **REST API (`application/json`)**: Returns structured JSON payload for external frontend / mobile clients.

### Available URLs

| Merchant | Web Browser URL | JSON API URL |
| :--- | :--- | :--- |
| **Acme Corp (ID: 1)** | [http://localhost:8800/merchants/1/dashboard](http://localhost:8800/merchants/1/dashboard) | [http://localhost:8800/api/merchants/1/dashboard](http://localhost:8800/api/merchants/1/dashboard) |
| **Starlight Tech (ID: 2)** | [http://localhost:8800/merchants/2/dashboard](http://localhost:8800/merchants/2/dashboard) | [http://localhost:8800/api/merchants/2/dashboard](http://localhost:8800/api/merchants/2/dashboard) |

---

## 2. Dashboard Wireframe & Core Features

### Key Metric Cards
1. **Current Cycle Usage**:
   - Total recorded units vs. combined plan allowances (e.g. `9,132,934 / 900,000 units`).
   - Dynamic progress bar visually indicating percentage consumed.
2. **Projected Overage Revenue**:
   - Real-time estimated billable overage fees due at cycle end (e.g. `₹ 335,460.06`).
   - Total overage units exceeding allowances.
3. **Active Plan & Subscribers**:
   - Active plan tier name, base fee, interval, and total subscriber count.

### Top 5 Customers by Usage
- Displays top 5 highest consumption customers for the current billing cycle.
- Calculates the percentage of plan allowance consumed.
- **Multi-Period Plan Support**: Automatically blends prorated allowances for customers who upgraded/downgraded mid-cycle (e.g., Craft Foods Co. on Phase 1 Basic + Phase 2 Premium gets 25k + 125k = 150k units).

### Churn Risk Alerts (MoM Drop > 50%)
- Compares each customer's current 30-day cycle consumption with their previous 30-day cycle.
- Automatically flags customers whose consumption decreased by more than 50%:
  $$\text{Usage Drop} = \frac{\text{Previous Cycle Usage} - \text{Current Cycle Usage}}{\text{Previous Cycle Usage}} > 0.50 \quad (50\%)$$
- Customers without prior cycle data or zero usage in both cycles are excluded to avoid false positives.

#### Simulating Previous Month Traffic for Churn Alerts
To populate this panel with live test alerts:
```bash
docker exec mallow-laravel.test-1 php artisan usage:simulate-traffic --users=1 --records-per-user=35000 --start-date=2026-08-01 --end-date=2026-08-31
```
This generates 35,000 usage events (~3.5M units) in August for User #1 (Beta Retail Pvt Ltd). When compared to September usage (~48k units), the dashboard immediately flags a **98.6% usage drop alert**.

### 30-Day Chronological Usage Trend
- Interactive line graph powered by Chart.js.
- Spans 30 consecutive calendar days, automatically backfilling zero-usage days to maintain an unbroken chronological timeline.

### Live System Status Widget
Displays real-time infrastructure telemetry:
- **Redis Cache Status**: Connection health via `Redis::ping()`, active cache TTL (10 minutes).
- **Nightly Aggregation Status**: Scheduled batch processing status and chunk size (5,000 rows).
- **API Rate Limiter**: Active protection threshold (120 req/min per IP/token).

---

## 3. JSON API Response Format

```json
{
  "success": true,
  "data": {
    "merchant": {
      "id": 1,
      "name": "Acme Corp"
    },
    "active_plan": {
      "id": 1,
      "name": "Basic Plan",
      "billing_cycle": "monthly",
      "base_price": 999,
      "included_units": 50000,
      "overage_rate": 0.05
    },
    "metrics": {
      "current_cycle_usage": 9132934,
      "total_allowance": 900000,
      "usage_percentage": 1014.8,
      "projected_overage_revenue": 335460.06,
      "projected_overage_units": 8232934,
      "active_subscribers": 6,
      "cycle_start": "2026-09-01",
      "cycle_end": "2026-09-30"
    },
    "top_customers": [
      {
        "user_id": 1,
        "customer_name": "Beta Retail Pvt Ltd",
        "customer_email": "beta@example.com",
        "plan_name": "Premium Plan",
        "usage_units": 1527482,
        "allowance_units": 150000,
        "percentage_of_allowance": 1018.3
      }
    ],
    "churn_risks": [],
    "daily_usage_trend": [
      { "date": "Sep 01", "full_date": "2026-09-01", "units": 297308 },
      { "date": "Sep 02", "full_date": "2026-09-02", "units": 309892 }
    ],
    "system_status": {
      "redis_cache": { "status": "operational", "ttl_minutes": 10 },
      "nightly_aggregation": { "status": "operational", "chunk_size": 5000 },
      "rate_limit": { "status": "enforced", "limit": 120, "interval": "1 minute" }
    }
  }
}
```

---

[← Back to Main README](../README.md)
