# Laravel Artisan File Generation Rule

## Core Rule

NEVER manually create Laravel framework files when an equivalent Laravel Artisan `make:*` command exists.

Do not create these files manually using the IDE file explorer, `touch`, shell redirection, copy/paste templates, or by writing the file from scratch.

Always use the appropriate Laravel Artisan command.

> **Docker Execution:** All Artisan commands in this rule must be executed according to the conventions defined in `.agents/rules/laravel-docker.md`. Do not run `php artisan` directly on the host. Refer to `laravel-docker.md` for the container execution context (`docker exec mallow-laravel.test-1 php artisan ...` or `docker compose exec -T laravel.test php artisan ...`).

---

## Models

Use:

```bash
docker exec mallow-laravel.test-1 php artisan make:model ModelName
```

Do not manually create `app/Models/ModelName.php`.

If related model components are required, use the appropriate Artisan options:

```bash
docker exec mallow-laravel.test-1 php artisan make:model Plan -m        # Model + migration
docker exec mallow-laravel.test-1 php artisan make:model Plan -mf       # Model + migration + factory
docker exec mallow-laravel.test-1 php artisan make:model Plan -mfs      # Model + migration + factory + seeder
```

Do not separately create the model and migration manually.

### `--all` Flag

Use `php artisan make:model Plan --all` ONLY when the task actually requires the complete set of supported model-related components.

Do not automatically use `--all` for every model. Before using `--all`, determine which components are actually required.

---

## Controllers

Always create controllers using Artisan:

```bash
docker exec mallow-laravel.test-1 php artisan make:controller MerchantDashboardController
docker exec mallow-laravel.test-1 php artisan make:controller UsageController --api
docker exec mallow-laravel.test-1 php artisan make:controller PlanController --resource
```

Never manually create `app/Http/Controllers/*.php`.

---

## Form Requests

Always create Form Request classes using:

```bash
docker exec mallow-laravel.test-1 php artisan make:request RecordUsageRequest
```

Never manually create `app/Http/Requests/*.php`.

---

## Migrations

Always create migrations using:

```bash
docker exec mallow-laravel.test-1 php artisan make:migration create_merchants_table
```

Never manually create migration files.

---

## Seeders

Always create seeders using:

```bash
docker exec mallow-laravel.test-1 php artisan make:seeder PlanSeeder
```

Never manually create seeder classes.

---

## Factories

Always create factories using:

```bash
docker exec mallow-laravel.test-1 php artisan make:factory PlanFactory
```

Never manually create factory classes.

---

## Middleware

Always create middleware using:

```bash
docker exec mallow-laravel.test-1 php artisan make:middleware EnsureValidMerchantApiKey
```

Never manually create middleware classes.

---

## Policies

Always create policies using:

```bash
docker exec mallow-laravel.test-1 php artisan make:policy PlanPolicy
```

---

## Resources (API Resources)

Always create API Resources using:

```bash
docker exec mallow-laravel.test-1 php artisan make:resource MerchantDashboardResource
```

Never manually create API Resource classes.

---

## Jobs

Always create Jobs using:

```bash
docker exec mallow-laravel.test-1 php artisan make:job AggregateDailyUsageJob
docker exec mallow-laravel.test-1 php artisan make:job GenerateCycleInvoiceJob
```

Never manually create Job classes.

---

## Events

Always create Events using:

```bash
docker exec mallow-laravel.test-1 php artisan make:event UsageRecordedEvent
```

Never manually create Event classes.

---

## Listeners

Always create Listeners using:

```bash
docker exec mallow-laravel.test-1 php artisan make:listener InvalidatePlanCacheListener
```

Never manually create Listener classes.

---

## Mail

Always create Mail classes using:

```bash
docker exec mallow-laravel.test-1 php artisan make:mail InvoiceGeneratedMail
```

Never manually create Mail classes.

---

## Notifications

Always create Notifications using:

```bash
docker exec mallow-laravel.test-1 php artisan make:notification ChurnRiskAlertNotification
```

Never manually create Notification classes.

---

## Commands

Always create Artisan Commands using:

```bash
docker exec mallow-laravel.test-1 php artisan make:command RunDailyUsageAggregation
```

Never manually create command classes.

---

## Exceptions

When the Laravel version/project supports the required Artisan generator, use the appropriate `php artisan make:*` command instead of manually creating the class.

If no official Artisan generator exists for the required file type (e.g., Services, DTOs, Actions), manual creation in `app/Services/`, `app/DTOs/`, or `app/Actions/` is allowed only after verifying that no suitable `make:*` command exists.

---

## General Artisan Rule

Before creating any Laravel framework class:

1. Identify the required Laravel component.
2. Check whether Laravel provides a `php artisan make:*` command.
3. Use the Artisan command via Docker when available.
4. Do not manually create the generated file.
5. After generation, inspect the generated file.
6. Modify the generated file only for the project-specific implementation.
7. Do not replace Artisan-generated structure with a manually created structure.

---

## Existing Files

If the requested Model, Controller, Request, Migration, etc. already exists:

- DO NOT run the generator again unless the developer explicitly wants a new class.
- First inspect the existing file and determine whether it should be modified.
- Do not overwrite existing project files unnecessarily.

---

## Docker / Laravel Sail Integration

Before running any Artisan command, respect the project's Docker/Sail workflow as defined in `.agents/rules/laravel-docker.md`.

All `php artisan make:*` commands shown in this rule must be wrapped with the container execution syntax specified in `laravel-docker.md`. Do not run Artisan directly on the host.

---

## Forbidden Behavior

NEVER do the following when an Artisan generator exists:

- Manually create `app/Models/*.php`
- Manually create `app/Http/Controllers/*.php`
- Manually create `app/Http/Requests/*.php`
- Manually create migrations
- Manually create factories
- Manually create seeders
- Manually create policies
- Manually create jobs
- Manually create events
- Manually create listeners
- Manually create mail classes
- Manually create notifications
- Manually create middleware
- Manually create Artisan commands

Do not use `touch app/Models/Plan.php`.
Do not use IDE "New File" for a Laravel generated class when an Artisan generator exists.
Do not manually copy an existing Laravel class and rename it when an Artisan generator can create the required class.

---

## Final Requirement

This rule must be treated as a mandatory Laravel development workflow.

Whenever a Laravel framework file can be generated through an official Artisan `make:*` command, ALWAYS use the Artisan command first via Docker and NEVER manually create the equivalent file.
