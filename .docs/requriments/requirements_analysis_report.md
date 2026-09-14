# Requirements Analysis Report: Subscription Billing & Usage-Metering System

**Role & Task**: Mallow Technologies — Senior Laravel Developer Mini Task  
**Source Document**: [`Laravel_Senior_Developer_Mini_Task.pdf`](file:///d:/mallow/.docs/requriments/Laravel_Senior_Developer_Mini_Task.pdf)  
**Date**: September 2026  

---

## 1. Executive Summary

The objective is to design and build a **multi-tenant SaaS subscription billing and usage-metering backend** in Laravel. The system meters customer consumption (e.g., API calls) against subscription plan allowances, calculates prorated billing and overage charges at the cycle end, provides an analytical merchant dashboard, and scales gracefully to handle **50L+ (5,000,000+) usage event records**.

---

## 2. Core Domain & Business Rules

1. **Multi-Tenancy (Merchants)**:
   - Each **Merchant** acts as an isolated tenant.
   - Merchants define one or more **Plans** (Name, Base Price, Billing Cycle, Included Units Allowance, Overage Rate per unit).
2. **Subscriptions & Customers**:
   - Customers subscribe to a merchant's plan.
   - Subscriptions can start mid-cycle or switch plans mid-cycle.
3. **Usage Events**:
   - High-throughput write events recorded per customer per day (e.g., API requests).
4. **Billing & Invoicing Math**:
   - **Base Price Proration**: Prorated based on days active in the billing period.
   - **Overage Calculation**: $\max(0, \text{Total Usage} - \text{Included Allowance}) \times \text{Overage Rate}$.
   - **Mid-Cycle Upgrades/Downgrades (Req 8)**: Segregated calculation:
     - Usage recorded **before** the change is billed against the old plan's rates and allowance.
     - Usage recorded **after** the change is billed against the new plan's rates and allowance.
     - Base fees for both plans are prorated for their respective active days.

---

## 3. Detailed Functional Requirements

| # | Requirement | Technical Implementation Focus |
| :--- | :--- | :--- |
| **1** | **Normalized & Indexed Schema (50L+ Scale)** | Design clean relational schema (`merchants`, `plans`, `customers`, `subscriptions`, `subscription_periods`, `usage_events`, `daily_usage_aggregates`, `invoices`). Provide an explicit scaling roadmap: composite indexes `(customer_id, recorded_at)`, MySQL table partitioning (by range/month), read-model denormalization (`daily_usage_aggregates`), and archival strategy. |
| **2** | **High-Throughput & Idempotent `POST /usage`** | Endpoint must ingest events rapidly. Enforce idempotency using an `idempotency_key` or unique hash constraint to guarantee that retried network requests never double-count usage. |
| **3** | **Queued & Chunked Aggregation & Invoicing Job** | Background scheduled jobs to roll up raw events into daily aggregates in chunks (e.g., `chunkById(5000)`) to prevent memory exhaustion. At cycle end, compute invoice line items, overage math, and proration. |
| **4** | **Plan & Pricing Caching (Redis)** | Cache active plan configurations and pricing in Redis (e.g., TTL 10m). Implement deliberate cache invalidation strategies (e.g., cache tags or events on plan update). |
| **5** | **Merchant Dashboard (`GET /merchants/{id}/dashboard`)** | Return real-time business insights: <br>• Top 5 customers by usage this cycle and % of allowance<br>• Projected overage revenue for current cycle<br>• Churn risk alerts: customers with usage drop > 50% month-over-month (MoM)<br>• 30-day daily usage trend |
| **6** | **Usage Rate Limiting** | Apply throttling to the `POST /usage` endpoint (e.g., 120 requests/minute per API key or customer token) using Laravel's `RateLimiter`. |
| **7** | **Automated Test Suite (PHPUnit / Pest)** | Comprehensive unit and feature tests covering: usage aggregation, overage computation, mid-cycle subscription start proration, mid-cycle upgrade/downgrade segmentation, and idempotency edge cases. |
| **8** | **Mid-Cycle Plan Changes (Upgrade/Downgrade)** | Support subscription periods/phases. Segment usage before vs. after plan change date, calculating independent allowances, overages, and prorated base amounts. |

---

## 4. UI Dashboard Specifications (Wireframe Reference)

The document specifies a merchant dashboard layout containing:
1. **Key Metric Cards**:
   - **Current Cycle Usage**: Total units used vs. total plan allowance (e.g., `184,320 / 250,000 units`).
   - **Projected Overage Revenue**: Current estimated overage billable at cycle end (e.g., `₹ 42,600`).
   - **Active Plan**: Plan name & cycle interval (e.g., `Growth — monthly`).
2. **Top 5 Customers by Usage Table**:
   - Customer Name, Usage units, % of Allowance.
3. **Churn Risk Alert Panel**:
   - Identifies customers whose usage dropped > 50% month-over-month (MoM).
4. **Daily Usage Trend Graph**:
   - 30-day chronological sparkline/trend chart.
5. **System Status Informational Widget**:
   - Plan pricing cache: Redis (TTL 10m).
   - Nightly aggregation job: Queued & chunked (e.g. 5,000 rows/batch).
   - Usage endpoint rate limit: 120 req/min per API key.

---

## 5. Architectural Expectations ("What Reviewers Look For")

- **Senior-Level Architecture**: Strict separation of concerns — **Controllers should be thin**, delegating business logic to **Actions, Service classes, and Data Transfer Objects (DTOs)**.
- **Deliberate Design Decisions**: Every architectural choice (caching strategy, queue chunking size, database indexing, idempotency mechanism) should be purposeful and justified, not incidental.
- **Production-Ready Documentation (`README.md`)**:
  - High-level architecture and ERD diagrams.
  - Setup and execution instructions (Docker / Sail).
  - Explicit documentation of assumptions, edge cases handled, and performance trade-offs under 50L+ volume.
  - "What I'd do differently with more time" section.

---

## 6. Submission Deliverables & Checklist

- [ ] **GitHub Repository**: Full clean Laravel application on GitHub.
- [ ] **Comprehensive `README.md`**: Architecture breakdown, math formulas, 50L+ scaling rationale.
- [ ] **Prompt Log (`/prompts` folder)**: Screenshots or transcripts of prompts used during AI-assisted development.
- [ ] **Screen Recording (5–10 min)**: Narrated walkthrough showing the working application, code architecture, and key design decisions.
- [ ] **Timebox**: 3 days elapsed time from receiving the brief.
