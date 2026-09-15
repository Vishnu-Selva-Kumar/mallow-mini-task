# Implementation Plan: Mid-Cycle Plan Changes (Upgrade/Downgrade) & Subscription Phases

Implement support for **Mid-Cycle Plan Changes (Upgrade/Downgrade)** and subscription periods/phases (Requirement 8). This ensures segregated calculations where usage before the plan change is billed against the previous plan's rates and allowance, usage after the plan change is billed against the new plan's rates and allowance, base subscription fees are prorated across their respective active days, and verified directly via the Artisan command and the Merchant Dashboard.

## Suggested Branch Name

`feat/mid-cycle-plan-changes`

---

## User Review Required

> [!IMPORTANT]
> **Adherence to Project Rules (`.agents/rules`)**:
> - **Artisan File Generation (`.agents/rules/laravel-artisan-generation.md`)**:
>   - Commands, Events, and Feature Tests will be generated via `docker exec mallow-laravel.test-1 php artisan make:*`.
>   - Services without official artisan generators (`app/Services/SubscriptionService.php`) are created cleanly in `app/Services/`.
> - **Docker Execution (`.agents/rules/laravel-docker.md`)**:
>   - All PHP, Composer, and Artisan commands will run exclusively inside `mallow-laravel.test-1`.
> - **Git & Commit Workflow (`.agents/rules/commit_rules.md` & `commit_type.yml`)**:
>   - Branch `feat/mid-cycle-plan-changes` will be created/checked out only with user confirmation.
>   - Commits will strictly follow `<type>(<scope>): <description>`.

> [!NOTE]
> **Scope Adjustment per Review Feedback**:
> - **No HTTP API Endpoint needed**: We will not register `POST /api/subscriptions/{subscription}/change-plan` or create `SubscriptionPlanController`.
> - **CLI & Dashboard Focus**: Mid-cycle plan changes will be performed via `php artisan subscription:change-plan` for target users and verified directly on the **Merchant Dashboard** (`/merchants/{merchant}/dashboard`).

---

## Core Mathematical Segregation (Requirement 8)

1. **Cycle Proration Fraction**:
   $$\text{Proration Fraction}_i = \frac{\text{Segment Days}_i}{\text{Total Days in Cycle}}$$
2. **Base Fee Proration**:
   $$\text{Segment Base Fee}_i = \text{round}(\text{Plan}_i.\text{BasePrice} \times \text{Proration Fraction}_i, 2)$$
3. **Segregated Allowance & Overage**:
   $$\text{Segment Allowance}_i = \text{round}(\text{Plan}_i.\text{IncludedUnits} \times \text{Proration Fraction}_i)$$
   $$\text{Segment Overage Units}_i = \max(0, \text{Segment Recorded Units}_i - \text{Segment Allowance}_i)$$
   $$\text{Segment Overage Fee}_i = \text{round}(\text{Segment Overage Units}_i \times \text{Plan}_i.\text{OverageRate}, 2)$$
4. **Total Invoice**:
   $$\text{Total Base} = \sum_{i=1}^{n} \text{Segment Base Fee}_i, \quad \text{Total Overage} = \sum_{i=1}^{n} \text{Segment Overage Fee}_i$$
   $$\text{Total Invoiced Amount} = \text{Total Base} + \text{Total Overage}$$

---

## Proposed Changes

### 1. Domain & Service Layer

#### [NEW] `app/Services/SubscriptionService.php`
- Handles subscription plan transitions:
  - `changePlan(Subscription $subscription, Plan $newPlan, Carbon $effectiveDate): SubscriptionPeriod`:
    - Validates that `$newPlan->merchant_id === $subscription->user->merchant_id` (or subscription merchant context).
    - Determines upgrade vs. downgrade.
    - Closes current active `SubscriptionPeriod`: sets `ends_at = $effectiveDate->copy()->subSecond()` and `status = 'closed'`.
    - Creates new `SubscriptionPeriod`: sets `starts_at = $effectiveDate`, `ends_at = currentCycleEnd`, `plan_id = $newPlan->id`, `status = 'active'`.
    - Updates `Subscription::plan_id = $newPlan->id`.
    - Dispatches `SubscriptionPlanChangedEvent`.
    - Returns the newly activated `SubscriptionPeriod`.

#### [MODIFY] [BillingService.php](file:///d:/mallow/app/Services/BillingService.php)
- Audit & enhance `calculateSubscriptionBilling()`:
  - Ensure strict timestamp & date boundary alignment across consecutive periods.
  - Return detailed segment breakdown including plan names, active days, prorated base, prorated allowances, and overage fees.

---

### 2. Events & Commands (Artisan Generated)

#### [NEW] `app/Events/SubscriptionPlanChangedEvent.php`
- Created via Artisan:
  ```bash
  docker exec mallow-laravel.test-1 php artisan make:event SubscriptionPlanChangedEvent
  ```
- Attributes: `Subscription $subscription`, `Plan $oldPlan`, `Plan $newPlan`, `Carbon $effectiveDate`.

#### [NEW] `app/Console/Commands/ChangeSubscriptionPlanCommand.php`
- Created via Artisan:
  ```bash
  docker exec mallow-laravel.test-1 php artisan make:command ChangeSubscriptionPlanCommand
  ```
- Signature: `subscription:change-plan {subscription : Subscription ID} {plan : Target Plan ID} {--date= : Effective date (YYYY-MM-DD, defaults to today)}`
- Logic:
  - Finds subscription and target plan.
  - Invokes `SubscriptionService::changePlan()`.
  - Calculates and renders a formatted CLI table showing:
    - Period 1 (Old Plan): Active days, prorated base, allowance, usage, overage.
    - Period 2 (New Plan): Active days, prorated base, allowance, usage, overage.
    - Total estimated cycle billable amount.

---

### 3. Automated Tests (Artisan Generated)

#### [NEW] `tests/Feature/MidCyclePlanChangeTest.php`
- Created via Artisan:
  ```bash
  docker exec mallow-laravel.test-1 php artisan make:test MidCyclePlanChangeTest
  ```
- Test Cases:
  1. **Mid-Cycle Upgrade via CLI**:
     - Upgrade subscription mid-month (e.g. Sep 16 from Basic to Premium).
     - Verify two `SubscriptionPeriod` records (one closed, one active).
     - Verify segregated allowance and prorated base amounts.
  2. **Mid-Cycle Downgrade via CLI**:
     - Downgrade subscription mid-month.
     - Verify correct period segmentation and overage calculation.
  3. **Multiple Changes in Single Cycle**:
     - 3 periods within a 30-day month; verify the sum of all segment bases and segregated overages.
  4. **Invoice Generation for Multi-Period Subscription**:
     - Run `billing:generate-invoices` and assert generated invoice matches the sum of segmented calculations.
  5. **Merchant Dashboard Reflection**:
     - Verify `GET /merchants/{merchant}/dashboard` accurately reflects updated plan, aggregated metrics, and overage revenue after a plan change.

---

## Verification Plan

### Automated Tests
Run inside Docker container:
```bash
docker exec mallow-laravel.test-1 php artisan test --filter=MidCyclePlanChangeTest
docker exec mallow-laravel.test-1 php artisan test --filter=BillingCalculationTest
docker exec mallow-laravel.test-1 php artisan test
```

### Manual Verification & Dashboard Check
1. **Run Plan Change Command for a User**:
   ```bash
   docker exec mallow-laravel.test-1 php artisan subscription:change-plan 2 2 --date=2026-09-16
   ```
2. **Generate Invoice**:
   ```bash
   docker exec mallow-laravel.test-1 php artisan billing:generate-invoices --subscription=2 --start=2026-09-01 --end=2026-09-30
   ```
3. **Verify on Merchant Dashboard**:
   - Visit `http://localhost:8800/merchants/1/dashboard` in browser.
   - Verify active plan details, updated usage metrics, and overage revenue reflect the change.
