# AI-Assisted Development Prompt Log

This folder documents all prompts and AI interactions utilized during the development of the **Subscription Billing & Usage-Metering System**, satisfying the submission requirement:

> **Submission Requirement**:
> *Prompt Log: if you used AI-assisted tools (Copilot/Cursor/Claude etc.), include screenshots of the actual prompts you used (from your chat/IDE panel) — so we can see exactly what was asked. Save these in a `/prompts` folder or embed them in the README.*

---

## Log Entries

### Prompt 1: Initial Docker & Sail Project Setup
- **Prompt**:
  > Create implemtation Plan for below requriments 
  > Since you're on Windows 11 + Docker Desktop, you can create the latest Laravel project without installing PHP or Composer on Windows.
  > 1. Create a new Laravel project using Docker (Using the official Composer image)
  > 2. Install Laravel Sail
  > 3. Initialize Sail with services (mysql, redis, mailpit)
  > 4. Start Sail
- **Review Adjustments & Feedback**:
  - Configured `APP_PORT=8800`.
  - Added `phpmyadmin` service dependent on MySQL on port `8000:80`.
  - Initialized Git on default branch `main`.
  - Configured remote origin URL: `git@github-personal:Vishnu-Selva-Kumar/mallow-mini-task.git`.
  - Formatted and executed initial commit.

---

### Prompt 2: Remote URL Correction & Git Push
- **Prompt**:
  > chagne remove url 'git@github-personal:Vishnu-Selva-Kumar/'  
  > git push
- **Action**:
  - Updated git remote URL via `git remote set-url origin git@github-personal:Vishnu-Selva-Kumar/mallow-mini-task.git`.
  - Pushed `main` branch to GitHub remote.

---

### Prompt 3: Commit Rules & Workflow Configuration
- **Prompt**:
  > D:\express-js\.agents\rules\commit_type.yml
  > create rules file for this project and flow this rules
- **Action**:
  - Created `.agents/rules/commit_type.yml` and `.agents/rules/commit_rules.md` tailored for this Laravel project.
  - Enforced Conventional Commits, lowercase descriptions, imperative mood, security checks (never committing `.env` or secrets), and mandatory user confirmation before branch/commit/push.

---

### Prompt 4: Requirements Document Analysis
- **Prompt**:
  > D:\mallow\.docs\requriments\Laravel_Senior_Developer_Mini_Task.pdf
  > just analys my requriment docs and give report for me
- **Action**:
  - Extracted text and visual wireframe from `Laravel_Senior_Developer_Mini_Task.pdf`.
  - Produced comprehensive requirements report covering 50L+ scale, idempotent usage ingestion, chunked aggregation, proration math, Redis caching, and merchant dashboard.
  - Stored report in `.docs/requriments/requirements_analysis_report.md`.
  - Stored setup implementation plan in `.docs/implementation_plans/01_docker_sail_setup_implementation_plan.md`.

---

### Prompt 5: Docker & Artisan Generation Rules
- **Prompt**:
  > create rule files for laravel artisn and docker 
  > example :
  > D:\job-core\job-core\.agents\rules\laravel-artisan-generation.md
  > D:\job-core\job-core\.agents\rules\laravel-docker.md
- **Action**:
  - Created `.agents/rules/laravel-docker.md` configured for container `mallow-laravel.test-1`.
  - Created `.agents/rules/laravel-artisan-generation.md` enforcing Artisan `make:*` commands inside Docker and strictly forbidding manual file creation when generators exist.

---

### Prompt 6: Database Schema & Architecture Design
- **Prompt**:
  > Create implementation plan and flow `.agents/rules` files  
  > Suggestion new branch name  
  > Database Schema & Architecture Design (ERD & Class Structure)
- **Action**:
  - Suggested branch name: `feat/database-schema-and-models`.
  - Designed normalized schema with 50L+ scale indexing, models, relationships, and seeders.
  - Stored plan in `implementation_plan.md` and archived in `.docs/implementation_plans/02_database_schema_and_models.md`.

---

### Prompt 7: Branch Checkout & Implementation Execution
- **Prompt**:
  > create branch from `main` branch and checkout new branch and start work
- **Action**:
  - Created and checked out branch `feat/database-schema-and-models`.
  - Generated all 7 models and migrations via `docker exec mallow-laravel.test-1 php artisan make:model <Name> -m`.
  - Added schema definitions and composite indexes for 50L+ scale.
  - Defined Eloquent relationships across all models.
  - Generated seeders (`MerchantSeeder`, `PlanSeeder`, `UserSeeder`, `SubscriptionSeeder`, `SubscriptionPeriodSeeder`) via Docker.
  - Successfully executed migrations and seeders, verified relationships via Tinker, and ran test suite.

---

## Screenshots Directory
Place IDE chat panel screenshots in this folder (`/prompts`) named chronologically, e.g.:
- `01_project_setup_prompt.png`
- `02_commit_rules_prompt.png`
- `03_requirements_analysis_prompt.png`
