# Setup & Installation Guide

This guide covers complete step-by-step instructions to set up, configure, and execute the application using Docker and Laravel Sail.

---

## 1. Prerequisites

- **Docker Desktop** installed and running on your system.
- **Git** installed on your system.
- *(Note: Local PHP and Composer are NOT required on your host machine — all tools run inside Docker).*

---

## 2. Clone Repository

```bash
git clone https://github.com/Vishnu-Selva-Kumar/mallow-mini-task.git
cd mallow-mini-task
```

---

## 3. Environment Configuration

Copy the example environment file to `.env`:

```bash
cp .env.example .env
```

### Default Port & Database Settings
```env
APP_NAME=Laravel
APP_ENV=local
APP_PORT=8800

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=mallow
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379

QUEUE_CONNECTION=database
CACHE_STORE=redis
```

---

## 4. Install Composer Dependencies via Docker

If PHP and Composer are not installed on your host system, install dependencies using Docker:

```bash
docker run --rm -v "${PWD}:/app" -w /app composer install --ignore-platform-reqs
```

*(Alternatively, if PHP & Composer are installed on your host machine: `composer install`)*

---

## 5. Build & Start Docker Containers

Start all 5 containers (Laravel, MySQL, Redis, Mailpit, phpMyAdmin) in detached mode:

```bash
docker compose up -d
```

Verify that all containers are healthy and running:

```bash
docker ps
```

Expected containers:
- `mallow-laravel.test-1` (PHP 8.4 / Laravel Sail app server)
- `mallow-mysql-1` (MySQL 8.4 database)
- `mallow-redis-1` (Redis Alpine cache)
- `mallow-mailpit-1` (Mailpit SMTP & UI)
- `phpmyadmin` (phpMyAdmin Web GUI)

---

## 6. Generate Application Key

```bash
docker exec mallow-laravel.test-1 php artisan key:generate
```

---

## 7. Run Migrations & Seeders

Run database migrations and seed all initial data (2 Merchants, 4 Plans, 12 Users, 12 Subscriptions, 13 Subscription Periods):

```bash
docker exec mallow-laravel.test-1 php artisan migrate:fresh --seed
```

---

## 8. Service URLs & Credentials

| Service | Host URL | Port | Credentials |
| :--- | :--- | :--- | :--- |
| **Laravel App** | [http://localhost:8800](http://localhost:8800) | `8800` | — |
| **Merchant 1 Dashboard** | [http://localhost:8800/merchants/1/dashboard](http://localhost:8800/merchants/1/dashboard) | `8800` | Acme Corp |
| **Merchant 2 Dashboard** | [http://localhost:8800/merchants/2/dashboard](http://localhost:8800/merchants/2/dashboard) | `8800` | Starlight Tech |
| **phpMyAdmin** | [http://localhost:8000](http://localhost:8000) | `8000` | Server: `mysql`<br>User: `sail`<br>Password: `password` |
| **Mailpit** | [http://localhost:8025](http://localhost:8025) | `8025` | Local SMTP UI |

---

[← Back to Main README](../README.md)
