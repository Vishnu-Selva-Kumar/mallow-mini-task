# Walkthrough: Mid-Cycle Plan Changes (Upgrade/Downgrade) & Subscription Phases

## Overview
Implemented complete support for **Mid-Cycle Plan Changes (Upgrade/Downgrade)** and subscription periods/phases (Requirement 8). The system segments usage before and after plan changes, calculating independent allowances, overages, and prorated base amounts.

---

## 1. Key Components Implemented

### Domain & Service Layer
- **[SubscriptionService](file:///d:/mallow/app/Services/SubscriptionService.php)**:
  - Executes atomic plan upgrades and downgrades within a database transaction.
  - Automatically identifies if the change is an `upgrade` or `downgrade`.
  - Closes the preceding active `SubscriptionPeriod` at `effectiveDate - 1 second` (`status = 'closed'`).
  - Creates a new active `SubscriptionPeriod` starting at `effectiveDate` through the cycle end (`status = 'active'`).
  - Updates `Subscription::plan_id` to the target plan.
- **[BillingService](file:///d:/mallow/app/Services/BillingService.php)**:
  - Calculates segregated proration across all `SubscriptionPeriod` records in the billing cycle.
  - Prorates base prices based on segment active days ($\frac{\text{Segment Days}}{\text{Cycle Days}} \times \text{Base Price}$).
  - Prorates plan allowances independently per phase ($\text{Included Units} \times \frac{\text{Segment Days}}{\text{Cycle Days}}$).
  - Attributes usage events to the exact active segment and applies that plan's overage rate to any excess units.
  - Added query fallback to `DailyUsageAggregate` if raw `UsageEvent` records were archived.
- **[MerchantDashboardService](file:///d:/mallow/app/Services/MerchantDashboardService.php)**:
  - Dynamically calculates effective allowance and overage units across multi-period subscription segments, updating merchant-level metrics and top customer allowance percentages accurately.

### Artisan Console Command
- **[ChangeSubscriptionPlanCommand](file:///d:/mallow/app/Console/Commands/ChangeSubscriptionPlanCommand.php)**:
  - Generated via `php artisan make:command ChangeSubscriptionPlanCommand`.
  - Signature: `subscription:change-plan {subscription} {plan} {--date=}`.
  - Renders a terminal table showing active duration, prorated base, prorated allowance, recorded units, overage units, and overage fees for each phase.

---

## 2. Verification & Automated Test Results

### Feature Test Suite: `tests/Feature/MidCyclePlanChangeTest.php`
- Generated via `php artisan make:test MidCyclePlanChangeTest`.
- Covers 8 comprehensive test scenarios:
  1. `test_subscription_service_handles_mid_cycle_upgrade_with_accurate_proration`: Validates closing old period, opening new period, setting plan ID.
  2. `test_billing_calculates_segregated_prorations_and_overages_before_and_after_change`: Validates exact mathematical segregation of base prices, allowances, and overage rates.
  3. `test_mid_cycle_downgrade_correctly_segments_and_charges`: Validates mid-cycle downgrades with independent 10-day vs. 20-day proration.
  4. `test_cli_command_subscription_change_plan_executes_successfully`: Validates `php artisan subscription:change-plan` CLI output table.
  5. `test_invoice_generation_persists_segmented_billing`: Validates `Invoice` model stores the exact sum of segmented bases and overages.
  6. `test_merchant_dashboard_reflects_mid_cycle_plan_changes`: Validates `GET /api/merchants/{merchant}/dashboard` accurately computes blended allowance and projected overage.
  7. `test_cannot_change_to_a_plan_belonging_to_another_merchant`: Ensures cross-merchant plan switching is rejected.
  8. `test_cannot_change_to_the_same_plan`: Ensures redundant plan changes are rejected.

### Test Execution Output
```
   PASS  Tests\Feature\MidCyclePlanChangeTest
  ✓ subscription service handles mid cycle upgrade with accurate proration
  ✓ billing calculates segregated prorations and overages before and after change
  ✓ mid cycle downgrade correctly segments and charges
  ✓ cli command subscription change plan executes successfully
  ✓ invoice generation persists segmented billing
  ✓ merchant dashboard reflects mid cycle plan changes
  ✓ cannot change to a plan belonging to another merchant
  ✓ cannot change to the same plan

  Tests:    8 passed (45 assertions)
```

### Full Project Test Suite
```
  Tests:    39 passed (300 assertions)
  Duration: 24.68s
```

---

## 3. Real-World Execution & Dashboard Verification

### CLI Command Execution
Executed mid-cycle upgrade on Subscription 2 (Craft Foods Co.) from **Basic Plan** (₹999 / 50,000 units) to **Premium Plan** (₹4,999 / 250,000 units) on `2026-09-16`:

```bash
docker exec mallow-laravel.test-1 php artisan subscription:change-plan 2 2 --date=2026-09-16
```

**Output:**
```
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

  Total Prorated Base:    ₹ 2,999.00
  Total Overage Fees:     ₹ 55,787.10
  Total Projected Invoiced: ₹ 58,786.10
```

### Invoice Generated
```bash
docker exec mallow-laravel.test-1 php artisan billing:generate-invoices --subscription=2 --start=2026-09-01 --end=2026-09-30
```
- **Base Amount**: ₹ 2,999.00 (₹499.50 + ₹2,499.50)
- **Overage Amount**: ₹ 55,787.10 (₹36,705.15 + ₹19,081.95)
- **Total Amount**: ₹ 58,786.10
- **Units Used**: 1,520,168 units

### Live Merchant Dashboard Verification
Visiting `http://localhost:8800/merchants/1/dashboard`:
- Top Customer listing shows **Craft Foods Co.** on **Premium Plan** with blended allowance of **150,000 units** (25,000 Phase 1 + 125,000 Phase 2).
- Merchant projected overage revenue dynamically recalculates to ₹ 335,460.06 reflecting the new rate structure.
