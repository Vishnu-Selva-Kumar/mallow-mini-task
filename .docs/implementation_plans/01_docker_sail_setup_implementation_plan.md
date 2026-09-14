# Implementation Plan: Docker Desktop, Laravel Sail & Git Setup

Initialize a brand new Laravel application in `d:/mallow` using Docker containers (avoiding any local installation of PHP or Composer on Windows), configure Laravel Sail with `mysql`, `redis`, `mailpit`, add `phpmyadmin`, configure ports (`APP_PORT=8800`), initialize Git repository pointing to `git@github-personal:Vishnu-Selva-Kumar/mallow-mini-task.git`, and bring up the container stack.

## Configuration Decisions

> **Port Configuration**:
> - Application Port (`APP_PORT`): `8800` (Laravel accessible at `http://localhost:8800`)
> - phpMyAdmin: `http://localhost:8000` (port `8000:80`)
> - Mailpit Web UI: `http://localhost:8025` / SMTP: `1025`
> - MySQL: `3306`
> - Redis: `6379`

> **Git Configuration**:
> - Default Branch: `main`
> - Remote Origin URL: `git@github-personal:Vishnu-Selva-Kumar/mallow-mini-task.git`
> - Initial Commit: `chore: initialize Laravel project with Sail, phpMyAdmin, and Docker environment`

---

## Step-by-Step Execution Record

### Step 1: Create Laravel Project via Composer Docker Image
Run the official Composer Docker container to scaffold the latest Laravel application directly in `d:/mallow`:
```powershell
docker run --rm -v "${PWD}:/app" -w /app composer:latest create-project laravel/laravel . --prefer-dist
```

### Step 2: Install Laravel Sail Dev Dependency
Install `laravel/sail` as a development dependency using the Composer Docker image:
```powershell
docker run --rm -v "${PWD}:/app" -w /app composer:latest require laravel/sail --dev
```

### Step 3: Initialize Sail Services Non-Interactively
Generate `compose.yaml` and configure `.env` for `mysql`, `redis`, and `mailpit`:
```powershell
docker run --rm -v "${PWD}:/app" -w /app php:8.4-cli php artisan sail:install --with=mysql,redis,mailpit
```

### Step 4: Configure APP_PORT & Add phpMyAdmin Service
1. **Update `.env` & `.env.example`**:
   - `APP_URL=http://localhost:8800`
   - `APP_PORT=8800`
2. **Update `compose.yaml`**:
   - Add the `phpmyadmin` service connected to the `sail` network and dependent on `mysql`:
   ```yaml
       phpmyadmin:
           image: 'phpmyadmin:latest'
           container_name: phpmyadmin
           restart: always
           ports:
               - '8000:80'
           environment:
               PMA_HOST: mysql
           networks:
               - sail
           depends_on:
               - mysql
   ```

### Step 5: Initialize Git Repository & Remote
1. Initialize git with default branch `main`:
   ```powershell
   git init -b main
   ```
2. Configure remote origin:
   ```powershell
   git remote add origin git@github-personal:Vishnu-Selva-Kumar/mallow-mini-task.git
   ```
3. Stage and commit:
   ```powershell
   git add .
   git commit -m "chore: initialize Laravel project with Sail, phpMyAdmin, and Docker environment"
   ```

### Step 6: Start Docker Containers
Bring up the entire Sail and phpMyAdmin stack in detached mode:
```powershell
docker compose up -d
```

---

## Verification Results

1. **Container Status (`docker compose ps`)**:
   - `mallow-laravel.test-1`: `0.0.0.0:8800->80/tcp` (Up)
   - `phpmyadmin`: `0.0.0.0:8000->80/tcp` (Up)
   - `mallow-mailpit-1`: `0.0.0.0:8025->8025/tcp`, `1025->1025/tcp` (Up)
   - `mallow-mysql-1`: `0.0.0.0:3306->3306/tcp` (Up)
   - `mallow-redis-1`: `0.0.0.0:6379->6379/tcp` (Up)

2. **HTTP Endpoints**:
   - `curl -I http://localhost:8800` -> **HTTP 200 OK**
   - `curl -I http://localhost:8000` -> **HTTP 200 OK**
   - `curl -I http://localhost:8025` -> **HTTP 200 OK**

3. **Database Migrations**:
   - `docker compose exec -T laravel.test php artisan migrate --force` -> **Migrated**

4. **Test Suite**:
   - `docker compose exec -T laravel.test php artisan test` -> **2 passed (2 assertions)**
