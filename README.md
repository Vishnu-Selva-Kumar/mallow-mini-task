# Subscription Billing & Usage-Metering System

[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat&logo=laravel)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.4+-777BB4?style=flat&logo=php)](https://php.net)
[![Tests](https://img.shields.io/badge/Tests-39%20passed%20(300%20assertions)-brightgreen)](tests)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

A high-performance, multi-tenant SaaS subscription billing and usage-metering backend engine built with Laravel. The system meters customer consumption (e.g. API requests) against subscription plan allowances, executes high-throughput idempotent ingestion, manages Redis-cached plan pricing, schedules memory-safe chunked rollups and cycle-end invoice generation, supports mid-cycle upgrades/downgrades with segregated proration, and scales gracefully under **50 Lakh+ (5,000,000+) usage event records**.

Developed for the **Mallow Technologies — Senior Laravel Developer Mini Task**.

---

## 📚 Complete Documentation Index

To keep this guide concise, comprehensive technical specifications have been organized into dedicated topic files. Click any topic below to open its full documentation:

| Document | Description | Link |
| :--- | :--- | :--- |
| **Architecture & Domain Model** | High-level system architecture, Mermaid ERD schema, and core mathematical proration & overage formulas. | [📖 Read Architecture Guide](docs/architecture.md) |
| **Setup & Installation Guide** | Complete Docker & Sail setup, environment variables, dependencies installation, and service configuration. | [🚀 Read Setup Guide](docs/setup-and-installation.md) |
| **API & CLI Reference** | Ingestion endpoint (`POST /api/usage`), idempotent replays, traffic simulator, daily aggregation, and invoicing commands. | [⚡ Read API & CLI Reference](docs/api-and-cli-reference.md) |
| **Merchant Analytics Dashboard** | Real-time SaaS dashboard wireframe, metric cards, top customers with blended allowances, churn risk detection, and trends. | [📊 Read Dashboard Guide](docs/merchant-dashboard.md) |
| **50L+ Scaling Strategy** | Composite indexing, read-model denormalization, memory-safe chunking, race conditions, and production roadmap. | [🛡️ Read Scaling Strategy](docs/scaling-and-architecture-decisions.md) |

---

## 🚀 60-Second Quick Start (Docker / Sail)

All PHP, Composer, and Artisan commands run directly inside Docker. No local PHP installation required.

```bash
# 1. Clone repository & configure environment
git clone https://github.com/Vishnu-Selva-Kumar/mallow-mini-task.git
cd mallow-mini-task
cp .env.example .env

# 2. Install dependencies via Docker
docker run --rm -v "${PWD}:/app" -w /app composer install --ignore-platform-reqs

# 3. Start all 5 Docker containers (Laravel, MySQL, Redis, Mailpit, phpMyAdmin)
docker compose up -d

# 4. Generate app key & run migrations with seed data
docker exec mallow-laravel.test-1 php artisan key:generate
docker exec mallow-laravel.test-1 php artisan migrate:fresh --seed

# 5. Run test suite
docker exec mallow-laravel.test-1 php artisan test
```

---

## 🌐 Live Service URLs

| Service | Host URL | Port | Credentials / Notes |
| :--- | :--- | :--- | :--- |
| **Laravel App** | [http://localhost:8800](http://localhost:8800) | `8800` | Application Root |
| **Merchant 1 Dashboard** | [http://localhost:8800/merchants/1/dashboard](http://localhost:8800/merchants/1/dashboard) | `8800` | Acme Corp (Interactive Blade UI) |
| **Merchant 2 Dashboard** | [http://localhost:8800/merchants/2/dashboard](http://localhost:8800/merchants/2/dashboard) | `8800` | Starlight Tech (Interactive Blade UI) |
| **Dashboard JSON API** | [http://localhost:8800/api/merchants/1/dashboard](http://localhost:8800/api/merchants/1/dashboard) | `8800` | JSON Telemetry Endpoint |
| **phpMyAdmin** | [http://localhost:8000](http://localhost:8000) | `8000` | Server: `mysql`, User: `sail`, Pass: `password` |
| **Mailpit** | [http://localhost:8025](http://localhost:8025) | `8025` | Local SMTP Testing Web UI |

---

## 💻 Essential CLI Commands

```bash
# Ingest 120,000 usage events (10k per user across 12 active users)
docker exec mallow-laravel.test-1 php artisan usage:simulate-traffic --users=12 --records-per-user=10000

# Simulate previous month (August) traffic to trigger Churn Risk Alerts (>50% MoM drop)
docker exec mallow-laravel.test-1 php artisan usage:simulate-traffic --users=1 --records-per-user=35000 --start-date=2026-08-01 --end-date=2026-08-31

# Aggregate usage events for a date into daily rollups (5,000 chunked)
docker exec mallow-laravel.test-1 php artisan usage:aggregate-daily --date=2026-09-15

# Change subscription plan mid-cycle (Upgrade/Downgrade with segregated breakdown)
docker exec mallow-laravel.test-1 php artisan subscription:change-plan 2 2 --date=2026-09-16

# Generate cycle invoices for all subscriptions (500 chunked)
docker exec mallow-laravel.test-1 php artisan billing:generate-invoices --start=2026-09-01 --end=2026-09-30
```

---

## 🧪 Automated Testing Suite

The application includes a comprehensive test suite covering edge cases, proration mathematics, race-condition idempotency, background jobs, mid-cycle plan changes, and merchant analytics.

```bash
# Run all tests inside Docker
docker exec mallow-laravel.test-1 php artisan test
```

**Status**: **39 passed (300 assertions)** across Unit and Feature suites.

---

## 📄 License

This project is open-sourced software licensed under the [MIT license](LICENSE).
