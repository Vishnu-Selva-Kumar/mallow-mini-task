# Laravel Docker Execution Rules

## Environment Setup
- Local PHP and Composer are **NOT** installed on the host system (Windows).
- All CLI commands related to PHP, Composer, Artisan, and Testing **MUST** be executed inside the running Docker container shell.

## Docker Execution Context
- **Target container:** `mallow-laravel.test-1` (or `docker compose exec -T laravel.test`)
- **Non-interactive (automated) syntax:** `docker exec mallow-laravel.test-1 <command>` or `docker compose exec -T laravel.test <command>`
- **Interactive shell syntax:** `docker exec -it mallow-laravel.test-1 bash`

## Command Standards

### Artisan Commands
```bash
# DO NOT run on host:
php artisan <command>

# DO run inside container:
docker exec mallow-laravel.test-1 php artisan route:list
docker exec mallow-laravel.test-1 php artisan make:test BillingTest --feature
docker exec mallow-laravel.test-1 php artisan migrate
```

### Composer Commands
```bash
# DO NOT run on host:
composer install
composer require <package>

# DO run inside container:
docker exec mallow-laravel.test-1 composer install
docker exec mallow-laravel.test-1 composer require <package>
```

### Running Tests (PHPUnit / Pest)
```bash
# DO NOT run on host:
php artisan test
./vendor/bin/phpunit

# DO run inside container:
docker exec mallow-laravel.test-1 php artisan test
docker exec mallow-laravel.test-1 php artisan test --filter=ExampleTest
```

## Agent Execution Guidelines
1. **Always verify the container is running first:** `docker ps --filter name=mallow-laravel.test-1`
2. **Wrap every PHP/Composer/Artisan command** inside `docker exec mallow-laravel.test-1 ...` or `docker compose exec -T laravel.test ...`
3. The host project workspace root (`d:/mallow`) is volume-synced to `/var/www/html/` inside the container.
4. **Never attempt `php`, `composer`, or `./vendor/bin/*` directly on the host shell.**
