# Unwrap24 API

Laravel API backend for **Unwrap24** — a platform for building interactive
daily-surprise calendars (Advent calendars and similar countdown/reveal
campaigns for any occasion). This repository is the backend only; it is
consumed by a separate Next.js frontend as a bearer-token JSON API.

This is **Phase 1 (Foundation)**. See [What's deferred to later phases](#whats-deferred-to-later-phases)
for what is intentionally not built yet.

## Stack

- PHP 8.3, Laravel **13.x** (note: the product brief targeted "Laravel
  12.x, the current major version `laravel/laravel` create-project will
  pull" — by the time this was built, `composer create-project
  laravel/laravel` pulls Laravel 13, which is what's installed here).
- Laravel Sanctum 4 — bearer-token API auth (not SPA cookie auth).
- Laravel Socialite 5 — Google OAuth.
- MySQL (production/dev), SQLite in-memory for the automated test suite.
- PHPUnit (the installer's default; Pest was not substituted in, per the
  brief's "don't fight the installer" guidance).

## Setup

```bash
# 1. Install PHP dependencies
composer install

# 2. Copy and configure environment
cp .env.example .env
php artisan key:generate

# 3. Create the MySQL database (run this yourself; credentials below are
#    placeholders - the app will NOT attempt to create the DB or guess
#    credentials)
mysql -u root -p -e "CREATE DATABASE unwrap24 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Then edit .env and set real credentials if not using root with no password:
#   DB_DATABASE=unwrap24
#   DB_USERNAME=root
#   DB_PASSWORD=

# 4. Migrate + seed
php artisan migrate --seed

# 5. Serve
php artisan serve
```

> **Migrations were not run against a live MySQL server during this
> build** — no working local MySQL root credentials were available in
> the build environment, and the instructions were explicit not to guess
> or brute-force them. Instead, every migration was validated by running
> `php artisan migrate:fresh --seed --database=sqlite` against the
> bundled `database/database.sqlite` file (a connection already defined
> in `config/database.php`), which applies all 21 migrations and all
> seeders cleanly, in dependency order, with foreign keys enabled. This
> exercises the same migration code MySQL would run; only the SQL
> dialect differs at the margins (e.g. enum handling), and nothing here
> uses MySQL-specific syntax. You should still run `php artisan migrate`
> yourself once a real MySQL `unwrap24` database exists.

### Seeded admin user

`AdminUserSeeder` creates one admin account:

- Email: `daviescareroute@gmail.com`
- Password: randomly generated at seed time (via `Str::password(20)`)
  and printed **once** to the console output of `php artisan db:seed` —
  it is not stored anywhere else or logged. Capture it from your
  terminal and **change it immediately** via the password reset flow
  (`POST /api/auth/forgot-password` → `POST /api/auth/reset-password`).
  This is a placeholder credential for local/dev bootstrapping, not a
  production-ready secret.

## Auth endpoints

All responses are JSON. Successful responses use `{"data": ...}` (or
`{"message": ..., "data": ...}` where a message is relevant); validation
and other errors use Laravel's standard `{"message": ..., "errors": {...}}`
shape (422) or `{"message": ...}` for other error statuses.

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/api/auth/register` | — | Create account, sends verification email, returns user + token |
| POST | `/api/auth/login` | — | Validate credentials, returns user + token |
| POST | `/api/auth/logout` | Bearer | Revokes the current token only |
| GET | `/api/auth/user` | Bearer | Returns the authenticated user |
| GET | `/api/auth/email/verify/{id}/{hash}` | Signed URL | Verification link target (opened from email, not called by the SPA) |
| POST | `/api/auth/email/resend` | Bearer | Resends the verification email |
| POST | `/api/auth/forgot-password` | — | Sends a password reset link if the email exists (response is identical either way, to avoid leaking account existence) |
| POST | `/api/auth/reset-password` | — | Resets password given `token`, `email`, `password`, `password_confirmation`; revokes all existing tokens for that user |
| GET | `/api/auth/google/redirect` | — | Redirects the browser to Google's OAuth consent screen |
| GET | `/api/auth/google/callback` | — | Google's callback target; see handoff mechanism below |

### Register

```
POST /api/auth/register
{ "name": "Jane Doe", "email": "jane@example.com", "password": "secret123", "password_confirmation": "secret123" }

201
{ "message": "...", "data": { "user": { "name", "email", "role", "avatar_path", "email_verified", "created_at" }, "token": "<plain-text sanctum token>" } }
```

`role` may optionally be sent as `"creator"` or `"business"` (defaults to
`"creator"`). **`admin` can never be self-assigned** through this
endpoint — that's a deliberate security decision, not an oversight.

### Login

```
POST /api/auth/login
{ "email": "jane@example.com", "password": "secret123" }

200
{ "message": "...", "data": { "user": {...}, "token": "..." } }
```

**Unverified users can still log in and get a token.** Laravel's standard
`MustVerifyEmail` pattern is implemented on the `User` model, but login
itself does not block on it — only routes that explicitly add the
`verified` middleware would (none do in Phase 1, since there are no
calendar-mutating endpoints yet). This matches the brief's "reject
unverified-but-don't-block" guidance.

### Email verification

Verification links are generated by Laravel's built-in
`Illuminate\Auth\Notifications\VerifyEmail` notification (fired via
`$user->sendEmailVerificationNotification()`), signed, and point at
`GET /api/auth/email/verify/{id}/{hash}`. Because this is a token-based
API with no browser session, that route is **not** behind `auth:sanctum`
— the signature itself is the authentication (verified manually in
`EmailVerificationController::verify()`, not via
`Illuminate\Foundation\Auth\EmailVerificationRequest`, which assumes a
session-authenticated guard user). On success/already-verified/invalid,
it 302-redirects to `{FRONTEND_URL}/email-verification?status=success|already-verified`
(invalid id/hash → 404) so the Next.js app can render a result page.

### Google OAuth handoff mechanism

**This is the part the frontend team needs to know about.**

1. Frontend links/redirects the user's browser to `GET /api/auth/google/redirect`.
2. This backend redirects to Google's consent screen (Socialite, stateless flow — no session required).
3. Google redirects back to `GET /api/auth/google/callback` on this backend.
4. The backend finds-or-creates the user (matched by `google_id`, then by `email` to link an existing password account), marks their email verified, issues a Sanctum token, and **302-redirects the browser to `{FRONTEND_URL}/auth/google/callback?token=<plain-text-sanctum-token>`**.
5. The frontend's route at that path is responsible for reading `token` out of the URL immediately, storing it per its own security posture, and stripping it from the URL/history.
6. On failure, the redirect instead carries `?error=google_auth_failed`.

This is the simplest handoff that works across two different domains
without shared cookies. If the frontend team wants a different mechanism
(e.g. a one-time code exchanged server-to-server instead of a token in
the URL), that's a Phase 2 change to `GoogleAuthController::callback()`.

### Dashboard stats

```
GET /api/dashboard/stats   (Bearer token required)

200
{
  "data": {
    "total_calendars": 0,
    "published_calendars": 0,
    "draft_calendars": 0,
    "total_visitors": 0,
    "total_door_openings": 0,
    "recent_activity": []
  }
}
```

Scoped to calendars the authenticated user owns directly **or** that
belong to any organization they're a member of. `total_visitors` counts
distinct `visitor_hash` values across `calendar_visits` for those
calendars; `total_door_openings` counts rows in `calendar_openings`.
`recent_activity` is intentionally `[]` — a real activity feed is
Phase 2+ work.

## Database

All Phase 1+ tables are migrated now (21 migrations), including ones
with no business logic wired up yet, because later phases depend on the
schema existing: `users`, `organizations`, `organization_members`,
`plans`, `subscriptions`, `payments`, `calendar_categories`,
`calendar_templates`, `calendars`, `calendar_days`, `calendar_media`,
`calendar_visits`, `calendar_openings`, `leads`, `notifications`
(Laravel's built-in table, see below), `analytics_events`,
`custom_domains`, `reports`, plus Sanctum's `personal_access_tokens`.

Publicly-exposable entities use a secondary indexed `ulid` column
(`organizations`, `subscriptions`, `payments`, `calendar_templates`,
`calendars`) or a unique `slug` (`calendar_categories`,
`calendar_templates`, `calendars`) for lookups — the internal
auto-increment `id` is never exposed in API responses for those models
(see `UserResource` / `CalendarResource`). `Calendar::resolveRouteBinding()`
resolves by `ulid` or `slug`, never the raw id, ready for Phase 2's
public calendar routes.

**Notifications table decision:** Laravel's standard `notifications`
table was generated via `php artisan make:notifications-table` and
migrated. No custom notification classes or in-app notification logic
ship in Phase 1 (only the built-in `VerifyEmail` / password-reset
notifications are used) — this table just exists so Phase 2+ can start
firing `Notification::send(...)` without a schema change.

## Authorization

`App\Policies\CalendarPolicy` (`view`, `update`, `delete`) is registered
in `AppServiceProvider::boot()` via `Gate::policy()`. It allows a user
who either (a) directly owns the calendar (`calendar.user_id ===
auth()->id()`) or (b) belongs to the organization that owns it. No
calendar CRUD routes call it yet — it's scaffolding for Phase 2.

## Engineering notes

- Controllers are thin; auth logic lives in `App\Actions\Auth\*`
  (`RegisterUserAction`, `LoginUserAction`, `GoogleLoginAction`).
- All validation is via Form Requests in `App\Http\Requests\Auth\*`, never
  inline in controllers.
- Every model declares explicit `$fillable` — `$guarded = []` is never
  used anywhere in this codebase.
- `App\Http\Resources\UserResource` and `App\Http\Resources\CalendarResource`
  exist now (the latter with no controller using it yet) so Phase 2 has
  an ID-safe response shape ready to go.

## What's deferred to later phases

Not built in Phase 1, by design:

- Calendar CRUD (create/edit/publish calendars, days, media uploads)
- Billing/payment provider webhooks (Stripe/Paystack) — the `subscriptions`
  and `payments` tables and models exist, but no checkout, webhook
  handling, or plan-enforcement logic
- Analytics event ingestion (writing to `analytics_events`,
  `calendar_visits`, `calendar_openings` from the public calendar page)
- Admin panel / moderation tooling for `reports`
- Custom domain verification flow (DNS checks, SSL)
- Any in-app notification logic beyond the built-in auth notifications
  (the `notifications` table is migrated and ready, unused otherwise)
- Lead export / CRM integration
- Organization management endpoints (invite/remove members, change
  roles) — the `organization_members` table/model exist; no controller

## Tests

```bash
php artisan test
```

14 tests / 40 assertions, all passing, against an in-memory SQLite
database (configured in `phpunit.xml`). Covers: register (success,
validation, duplicate email), login (valid/invalid/unknown credentials),
logout (revokes token; a revoked token can no longer authenticate),
dashboard stats for an authenticated user, and 401s on unauthenticated
access to protected routes.

## CORS

`config/cors.php` allows the origin in `FRONTEND_URL` (default
`http://localhost:3000`) on `api/*` and `sanctum/csrf-cookie`, with
`supports_credentials` true and `Authorization` exposed, for the Next.js
frontend.

## Google Cloud Platform deployment

The API ships as a PHP 8.3 / nginx container intended for **Google Cloud Run**,
with all supporting infrastructure defined in Terraform under
[`terraform/`](terraform/README.md). Application behaviour is unchanged: local
development still uses the `local` filesystem disk and its own MySQL connection.

### Architecture

```
                       ┌────────────────────────────────────────┐
  Next.js frontend ───▶│ Cloud Run · unwrap24-api               │
  (HTTPS, Sanctum      │ PHP 8.3 FPM + nginx, port $PORT        │
   bearer tokens)      │ dedicated service account, scale 0→10  │
                       └──┬──────────────┬──────────────┬───────┘
                          │              │              │
        Cloud SQL socket  │              │ Secret refs  │ ADC (metadata server)
        /cloudsql/...     │              │              │
                          ▼              ▼              ▼
                 ┌────────────────┐ ┌──────────────┐ ┌─────────────────────┐
                 │ Cloud SQL      │ │ Secret       │ │ Cloud Storage       │
                 │ MySQL 8.0      │ │ Manager      │ │ calendar media      │
                 │ TLS required   │ │ 5 secrets    │ │ (Laravel gcs disk)  │
                 └────────────────┘ └──────────────┘ └─────────────────────┘
                          │
                          ▼
                 ┌────────────────┐        Images: Artifact Registry ──▶ Cloud Run
                 │ Cloud Logging  │◀── stdout/stderr (LOG_CHANNEL=stderr)
                 │ dedicated sink │
                 └────────────────┘
```

### GCP services

| Service | Purpose in Unwrap24 |
|---|---|
| **Cloud Run** (`unwrap24-api`) | Runs the container, terminates TLS, probes `/up`, injects env vars and secret references, scales 0→10 |
| **Artifact Registry** (`unwrap24`) | Stores the API Docker images; last 10 versions kept |
| **Cloud SQL for MySQL** (`unwrap24-mysql`) | Primary database (`unwrap24`), reached over the Cloud SQL Unix socket, TLS-only, automated backups |
| **Cloud Storage** (`<project>-unwrap24-production-media`) | Calendar media through Laravel's `gcs` filesystem disk; uniform access, public access prevention |
| **Secret Manager** | `APP_KEY`, DB password, Google OAuth client secret, Stripe secret key and webhook secret; referenced by Cloud Run, never baked into the image |
| **Service account** (`unwrap24-run`) | Dedicated runtime identity, no keys, least-privilege IAM |
| **Cloud Logging** | Laravel logs to stderr; a sink routes Cloud Run revisions to a dedicated, 30-day bucket |
| **IAM** | `cloudsql.client`, `logging.logWriter`, `monitoring.metricWriter` at project level; repository reader, bucket `objectAdmin` and per-secret `secretAccessor` scoped to the resources used |

### Container image

The root [`Dockerfile`](Dockerfile) is a multi-stage build:

- **vendor stage** — `composer install --no-dev` and an authoritative
  optimized autoloader.
- **runtime stage** — `php:8.3-fpm-alpine` with nginx, supervisor, bcmath, exif,
  gd, intl, opcache, pcntl, pdo_mysql and zip; nginx listens on `${PORT}`
  (rendered by `docker/entrypoint.sh`), PHP-FPM stays on loopback, and
  `php artisan config:cache` runs after Cloud Run injects its configuration.
- No `.env`, credentials or Terraform state are copied into the image
  (see [`.dockerignore`](.dockerignore)); configuration arrives at runtime from
  Cloud Run environment variables and Secret Manager references.

### Deployment workflow

1. `cd terraform && terraform init` with a `terraform.tfvars` (see
   `terraform/terraform.tfvars.example`).
2. Bootstrap APIs and Artifact Registry:
   `terraform apply -target=google_project_service.apis -target=google_artifact_registry_repository.docker`.
3. Build and push the image to
   `<region>-docker.pkg.dev/<project>/unwrap24/unwrap24-api:<tag>`.
4. `terraform apply -var="image_tag=<tag>"` creates Cloud SQL, Storage,
   secrets, IAM, logging and the Cloud Run revision.
5. Run `php artisan migrate --force` through the Cloud SQL Auth Proxy or a
   one-off Cloud Run job, then register
   `https://<service-url>/api/auth/google/callback` in the Google OAuth client.

Full commands, destruction steps and cost notes live in
[`terraform/README.md`](terraform/README.md).

### Local validation evidence

| Check | Command | Result |
|---|---|---|
| Terraform formatting | `terraform fmt -recursive` / `terraform fmt -check -recursive` | clean |
| Terraform init | `terraform init -backend=false` | initialized (`hashicorp/google`, `hashicorp/random`) |
| Terraform validation | `terraform validate` | `Success! The configuration is valid.` |
| Docker image | `docker build -t unwrap24-api:local .` | built successfully (multi-stage, PHP 8.3) |
| Container boot | `docker run -e PORT=9090 -p 9090:9090 unwrap24-api:local` | supervisord starts nginx + php-fpm, nginx listens on `PORT` |
| Health endpoint | `curl http://127.0.0.1:9090/up` | `200` |
| API routing/auth | `curl -H 'Accept: application/json' /api/auth/user` | `401 {"message":"Unauthenticated."}` |
| Image contents | inspect `/var/www/html` | `.env.example` only, no `.env`, no dev packages |
| Application tests | `php artisan test --compact` | 60 tests / 230 assertions passing |
| Code style | `vendor/bin/pint --dirty --format agent` | passed |

**What this does and does not prove:** the Terraform above was validated with
`terraform fmt`, `terraform init -backend=false` and `terraform validate`, which
checks syntax, types, provider schema and internal references. It was **not**
`terraform plan`ned against a live project and **not** applied — no Google Cloud
resources (Cloud Run service, Cloud SQL instance, bucket, secrets, IAM) have been
created, and the API has **not** been deployed to GCP. The Docker image was
built locally; pushing and running it on Cloud Run remains part of the
deployment workflow above.
