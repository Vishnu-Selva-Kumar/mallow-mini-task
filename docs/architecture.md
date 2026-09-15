# System Architecture & Domain Model

This document details the architectural foundation, entity relationships, and mathematical models powering the **Subscription Billing & Usage-Metering System**.

---

## 1. High-Level Architecture

The application adopts a **Service-Oriented Architecture (SOA)** with strict separation of concerns:

- **Thin Controllers**: Purely dispatch incoming requests and format JSON/Blade responses.
- **Form Requests**: Enforce strict type checking, boundaries, and relationship constraints before reaching domain layers.
- **Data Transfer Objects (DTOs)**: Immutable, typed data structures bridging HTTP transport and business services.
- **Dedicated Services**: Pure domain logic encapsulated inside `UsageService`, `BillingService`, `SubscriptionService`, `MerchantDashboardService`, and `PlanCacheService`.
- **Chunked Background Jobs**: Scheduled worker jobs processing high-volume datasets in memory-safe windows.

```mermaid
flowchart TD
    Client(["HTTP Client / API Consumer"]) -->|POST /api/usage| RL["RateLimiter (120 req/min)"]
    RL --> UC["UsageController"]
    UC --> SUR["StoreUsageRequest (Validation)"]
    SUR --> DTO["RecordUsageDTO"]
    DTO --> US["UsageService"]
    
    subgraph StorageLayer ["Storage and Caching Layer"]
        US -->|Idempotency Check| UE[("usage_events (MySQL)")]
        US -->|Active Subscription Check| SUB[("subscriptions (MySQL)")]
        PCS["PlanCacheService"] -->|Cache Pricing 10m TTL| REDIS[("Redis Cache")]
    end

    subgraph JobLayer ["Scalable Scheduled Jobs"]
        CRON["Laravel Scheduler / Artisan CLI"] --> ADUJ["AggregateDailyUsageJob (chunk 5000)"]
        ADUJ -->|Grouped Rollup Upsert| DUA[("daily_usage_aggregates (MySQL)")]
        
        CRON --> GIJ["GenerateInvoiceJob (chunkById 500)"]
        GIJ --> BS["BillingService (Proration & Segment Math)"]
        BS --> INV[("invoices (MySQL)")]
    end
```

---

## 2. Entity-Relationship Diagram (ERD)

```mermaid
erDiagram
    MERCHANTS ||--o{ PLANS : "configures"
    PLANS ||--o{ SUBSCRIPTIONS : "subscribes"
    USERS ||--o{ SUBSCRIPTIONS : "owns"
    SUBSCRIPTIONS ||--o{ SUBSCRIPTION_PERIODS : "segmented into"
    PLANS ||--o{ SUBSCRIPTION_PERIODS : "governs"
    SUBSCRIPTIONS ||--o{ USAGE_EVENTS : "incurs"
    USERS ||--o{ USAGE_EVENTS : "performs"
    SUBSCRIPTIONS ||--o{ DAILY_USAGE_AGGREGATES : "rolls up into"
    SUBSCRIPTIONS ||--o{ INVOICES : "billed via"
    USERS ||--o{ INVOICES : "receives"

    MERCHANTS {
        bigint id PK
        string name
        timestamp created_at
    }

    PLANS {
        bigint id PK
        bigint merchant_id FK
        string name
        decimal base_price
        string billing_cycle
        bigint included_units
        decimal overage_rate
    }

    USERS {
        bigint id PK
        string name
        string email UK
        string password
    }

    SUBSCRIPTIONS {
        bigint id PK
        bigint user_id FK
        bigint plan_id FK
        string status
    }

    SUBSCRIPTION_PERIODS {
        bigint id PK
        bigint subscription_id FK
        bigint plan_id FK
        timestamp starts_at
        timestamp ends_at
        string status
    }

    USAGE_EVENTS {
        bigint id PK
        bigint user_id FK
        bigint subscription_id FK
        date usage_date
        bigint units
        string idempotency_key UK
    }

    DAILY_USAGE_AGGREGATES {
        bigint id PK
        bigint user_id FK
        bigint subscription_id FK
        date usage_date
        bigint total_usage
    }

    INVOICES {
        bigint id PK
        bigint user_id FK
        bigint subscription_id FK
        date invoice_date
        decimal base_amount
        decimal overage_amount
        decimal total_amount
        bigint units_used
        string status
    }
```

---

## 3. Core Mathematical Formulas

### 1. Base Price Proration

For mid-cycle subscription starts or plan changes:

$$\text{Proration Fraction} = \frac{\text{Active Days in Cycle}}{\text{Total Days in Cycle}}$$

$$\text{Prorated Base Fee} = \text{Plan Base Price} \times \text{Proration Fraction}$$

### 2. Usage Overage Charges

$$\text{Prorated Included Units} = \lfloor \text{Included Allowance} \times \text{Proration Fraction} \rceil$$

$$\text{Overage Units} = \max(0, \text{Total Recorded Units} - \text{Prorated Included Units})$$

$$\text{Overage Fee} = \text{Overage Units} \times \text{Plan Overage Rate}$$

$$\text{Total Invoice Amount} = \text{Prorated Base Fee} + \text{Overage Fee}$$

### 3. Mid-Cycle Upgrades & Downgrades (Requirement 8)

When a customer upgrades or downgrades mid-cycle, the cycle is partitioned across multiple `SubscriptionPeriod` segments:

$$\text{Total Base} = \sum_{i=1}^{n} \left(\text{Plan}_i.\text{BasePrice} \times \frac{\text{SegmentDays}_i}{\text{CycleDays}}\right)$$

$$\text{Total Overage} = \sum_{i=1}^{n} \left(\max\left(0, \text{SegmentUsage}_i - \left\lfloor \text{Plan}_i.\text{Allowance} \times \frac{\text{SegmentDays}_i}{\text{CycleDays}} \right\rceil\right) \times \text{Plan}_i.\text{OverageRate}\right)$$

$$\text{Total Cycle Billing} = \text{Total Base} + \text{Total Overage}$$

---

[← Back to Main README](../README.md)
