# Project Foundation — Technical Requirements

**Stack:** Laravel 13 · Inertia v3 · React 19 · shadcn/ui · MySQL · Redis · Laravel Sail · Laravel Forge · Nuno Maduro's strict conventions
**Status:** Draft v0.3 · **Date:** 2026-09-11 · **Scope:** foundation only — product features are specified in `docs/prd.md`

---

## 0. How to read this document

Each requirement has an ID (e.g. `ENV-05`) so it can be referenced in tickets, PRs, and agent prompts. **MUST** means required for the foundation milestone; **SHOULD** means strongly recommended and may be deferred with a note. Section 2 records the decisions taken and their rationale; section 19 is the decision log (resolved and still-open questions); section 21 is the changelog.

Version numbers reflect the reference starter kit as observed on 2026-09-11. They are a baseline, not a pin: the actual pin is whatever lands in `composer.lock` and `bun.lock` at bootstrap.

---

## 1. Purpose and scope

The goal is a production-grade, empty-but-complete application skeleton that any future feature can be built on without revisiting infrastructure, tooling, or conventions. "Complete" means: invitation-only authentication works end to end, the local environment runs entirely in Docker via Sail, every quality gate (including browser tests) is wired and green, CI enforces the same gates, the app deploys to Laravel Forge from a green `main`, and Claude Code follows the same rules the humans do — without leaving any AI attribution in the git history.

**In scope:** skeleton, invitation-only onboarding, authentication and account settings, local environment, MySQL and Redis setup, code conventions, frontend conventions, unit/feature/browser testing and static analysis, CI, dependency policy, Claude Code + Laravel Boost configuration, git attribution policy, Forge deployment pipeline, security baseline.

**Out of scope:** domain features, roles and permissions beyond the single "can invite" capability, billing, multi-tenancy, localisation, server provisioning details beyond what Forge requires.

---

## 2. Foundational decisions

| ID | Decision | Rationale | Alternative rejected |
|---|---|---|---|
| D-01 | Base the project on **`nunomaduro/laravel-starter-kit-inertia-react`**, then adapt it for Sail, MySQL, and Redis. | It already combines Inertia + React + shadcn (`components.json`), Fortify, Wayfinder, Essentials, Boost guidelines, Rector/PHPStan/Pint/OxLint/Oxfmt at max strictness, 100% type and code coverage, and an Actions-oriented, "Cruddy by design" architecture. | Official `laravel/react-starter-kit` plus manually adding Essentials and the strict tooling. |
| D-02 | **Laravel Sail is the only supported local runtime.** No reliance on host PHP, Composer, Node, or Bun. | Identical environment for every developer and every agent. | Herd / Valet / native PHP. |
| D-03 | **MySQL for local development, tests, CI, and production.** The kit's SQLite defaults are removed. | Production parity (strict mode, JSON columns, collation, FK behaviour). | SQLite in-memory for tests. |
| D-04 | **Bun** remains the JS package manager and script runner, via `sail bun` / `sail bunx`. | The kit is Bun-first; Sail and Forge both support Bun. | npm. |
| D-05 | **Laravel Fortify** for authentication, with **public registration disabled**. | Used by the reference kit and the official starter kits. | WorkOS AuthKit. |
| D-06 | **Nuno's quality bar is permanent:** 100% type coverage, PHPStan level `max`, 100% line coverage. | Decided (OD-07). Cheapest to hold on a greenfield codebase. | 90% coverage + mutation testing. |
| D-07 | **Action classes have no `Action` suffix** (`CreateUser`, not `CreateUserAction`). | The kit's AI guidelines mandate it; overrides the Essentials README example. | Suffix naming. |
| D-08 | **Mass-assignment protection stays ON** (Essentials `Unguard` = `false`). | Safety by default. | Globally unguarded models. |
| D-09 | **Redis** for cache, queues, and sessions in local and production. | Decided (OD-01). Matches Forge's standard server stack; production parity. | Database drivers. |
| D-10 | **Private application, invitation-link onboarding.** Accepting an invitation creates the account and verifies the email. | Decided (OD-02, OD-03). | Public registration. |
| D-11 | **Laravel Forge** is the deployment target. | Decided (OD-06). | Laravel Cloud, self-managed Docker. |
| D-12 | **Claude Code is the only configured AI agent.** Other agent artifacts shipped by the kit are removed. | Decided (OD-11). One source of truth for agent instructions. | Multi-agent configuration. |
| D-13 | **No AI attribution anywhere in git history or PRs** — no `Co-Authored-By: Claude`, no "Generated with Claude Code", no session trailers or links. Enforced in four layers (§13). | Explicit requirement. The Claude Code setting alone is not sufficient (see §2.1). | Settings-only enforcement. |

### 2.1 Known frictions between the chosen pieces

1. **The kit assumes SQLite and `php artisan serve`.** Under Sail, MySQL runs in its own container and the `laravel.test` container already serves the app. → `ENV-04`, `ENV-05`, `DB-03`.
2. **The kit uses Vite+ (`vp`) instead of plain Vite.** HMR from the host browser through Sail must be verified. → `ENV-06`.
3. **Boost's documentation API lags the kit's versions** (Inertia v3, Pest 5 vs docs for Inertia 1.x/2.x, Pest 3.x/4.x). → `AI-05`.
4. **Boost + Sail regeneration bug:** `boost:update` run with host PHP can strip Sail references (laravel/boost #508). → `AI-03`.
5. **Auto-bumping dependencies** in the kit's `post-update-cmd`. → `DEP-01`.
6. **Unpinned `latest` tags** for `vite` and `vite-plus`. → `STACK-02`.
7. **Essentials `make:action` stub vs kit guideline** on naming. → `D-07`.
8. **Browser tests need Playwright browsers inside the Sail container**, which the stock Sail image does not include. → `ENV-14`.
9. **Claude Code's `attribution` setting does not remove everything.** Users report it being ignored intermittently, and newer session trailers/links (`Claude-Session:`, `claude.ai/code/session_…`) are not controlled by it. → §13.

---

## 3. Technology stack (baseline)

| Layer | Choice | Baseline version | Notes |
|---|---|---|---|
| Language | PHP | 8.5 | Sail default runtime; Forge server must run the same minor. |
| Framework | laravel/framework | ^13.18 | |
| Server-driven SPA | inertiajs/inertia-laravel | 3.x (kit pins v3.0.6) | No Axios, `useHttp`, `Inertia::optional()` replaces `lazy()`. |
| Inertia client | @inertiajs/react, @inertiajs/vite | ^3.5 | SSR not used (OD-05). |
| Auth backend | laravel/fortify | ^1.37 | Registration feature disabled. |
| Typed routes | laravel/wayfinder + @laravel/vite-plugin-wayfinder | ^0.1 | |
| Better defaults | nunomaduro/essentials | ^1.2 | See §6.1. |
| UI runtime | React | ^19.2 | With React Compiler. |
| Language (FE) | TypeScript | ^6 | |
| Styling | Tailwind CSS 4 | ^4.3 | |
| Components | shadcn/ui on Radix | — | cva, clsx, tailwind-merge, lucide-react, sonner. |
| Build | Vite+ (`vp`) + laravel-vite-plugin | ^3.1 | |
| JS runtime | Node 24 + Bun | — | |
| Database | MySQL 8.x | Sail service | Major version pinned to Forge's (DB-01). |
| Cache / queue / session | Redis via phpredis | Sail service | |
| Mail (local) | Mailpit | Sail service | Production provider: OD-14. |
| Testing | Pest 5 (+ laravel, type-coverage, **browser**), PHPUnit 13, Playwright (Chromium) | ^5.0 / ^1.61 | |
| Static analysis | Larastan 3 (`max`, bleedingEdge) + `tsc` | ^3.10 | |
| Refactor / format | Rector 2 + rector-laravel, Pint, OxLint, Oxfmt | | |
| AI tooling | Claude Code + Laravel Boost 2 | Boost ^2.4 | |
| DX | Pail, Tinker, Collision | | |
| Supply chain | roave/security-advisories | dev-latest | |
| Hosting | Laravel Forge | — | Nginx, PHP-FPM, MySQL, Redis on the server. |

| ID | Requirement | Level |
|---|---|---|
| STACK-01 | `composer.lock` and `bun.lock` are committed; CI and Forge install from lockfiles only. | MUST |
| STACK-02 | Replace `latest` tags in `package.json` (`vite`, `vite-plus`) with explicit versions. | MUST |
| STACK-03 | No dependency is added, removed, or major-bumped without explicit approval. | MUST |

---

## 4. Local development environment (Sail)

### 4.1 Prerequisites

Docker Engine / Docker Desktop with Compose v2. Windows developers work inside WSL2. No local PHP, Composer, Node, or Bun is required. On Docker Desktop for Linux, use the `default` Docker context; if file permission errors appear inside containers, set `SUPERVISOR_PHP_USER=root`.

### 4.2 Requirements

| ID | Requirement | Level |
|---|---|---|
| ENV-01 | `laravel/sail` is a dev dependency and `compose.yaml` is committed. Services: **`mysql`, `redis`, `mailpit`**. Further services are added only via `sail artisan sail:add` and recorded here. | MUST |
| ENV-02 | `laravel.test` builds from the PHP 8.5 runtime with a project-unique image name (e.g. `sail-8.5/<project-slug>`). After ENV-14, it builds from the published `docker/8.5` directory. | MUST |
| ENV-03 | `.env.example` is Sail-ready (§4.4); copying it to `.env` is the only env step needed locally. | MUST |
| ENV-04 | All SQLite bootstrapping is removed (the `database.sqlite` touch in `post-create-project-cmd`, `DB_CONNECTION=sqlite`, SQLite settings in `phpunit.xml`). | MUST |
| ENV-05 | `composer dev` runs inside the container via `sail composer dev`, does **not** start `artisan serve`, and runs the queue listener, Pail, and the Vite+ dev server concurrently (§4.5). | MUST |
| ENV-06 | The Vite+ dev server is reachable from the host on `VITE_PORT` (5173) with working HMR. If needed, set `server.host = '0.0.0.0'`, `server.port` from `VITE_PORT`, `strictPort: true`, `hmr.host = 'localhost'` in `vite.config.ts`. | MUST |
| ENV-07 | Every documented command uses the Sail form (`sail artisan`, `sail composer`, `sail bun`, `sail bunx`, `sail test`). Developers configure `alias sail='sh $([ -f sail ] && echo sail \|\| echo vendor/bin/sail)'`. | MUST |
| ENV-08 | Coverage works inside the container (Xdebug via the kit's per-process `XDEBUG_MODE=coverage`, or PCOV via `PHP_EXTENSIONS`). Step debugging is opt-in via `SAIL_XDEBUG_MODE=develop,debug`. | MUST |
| ENV-09 | All outgoing mail (invitations, verification, password reset) is captured by Mailpit at `http://localhost:8025`. | MUST |
| ENV-10 | The onboarding sequence (§4.3) succeeds on macOS (Apple Silicon and Intel), Linux, and Windows/WSL2. | MUST |
| ENV-11 | MySQL and Redis data persist in Docker volumes; `sail down -v` is documented as a destructive reset. | MUST |
| ENV-12 | `composer setup` is safe to re-run and never rotates an existing `APP_KEY`. | SHOULD |
| ENV-13 | `composer setup` tolerates MySQL not yet being ready right after `sail up -d`. | SHOULD |
| ENV-14 | **Playwright is baked into the Sail image.** Publish the Sail Dockerfiles (`sail artisan sail:publish`) and add a build step that installs Chromium plus its OS dependencies at the exact `playwright` version in `bun.lock`, into a shared path (e.g. `PLAYWRIGHT_BROWSERS_PATH=/opt/ms-playwright`) readable by the `sail` user. Browser tests then run with `sail composer test` and no extra manual steps. The published Dockerfile becomes owned code and is reviewed on every Sail upgrade. | MUST |
| ENV-15 | Git hooks are enabled on the host with `git config core.hooksPath .githooks` as part of onboarding (§13). | MUST |

### 4.3 Reference onboarding sequence

```bash
git clone <repo> && cd <repo>
cp .env.example .env
git config core.hooksPath .githooks          # AI-attribution stripping + pre-commit lint

# Install PHP dependencies without local PHP (use the laravelsail/phpXX-composer
# image matching the Sail runtime; confirm the 8.5 tag exists before documenting it)
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
  laravelsail/php85-composer:latest composer install --ignore-platform-reqs

./vendor/bin/sail build                       # includes Playwright (ENV-14)
sail up -d
sail composer setup                           # key (if empty), migrate, bun install, build
sail artisan db:seed                          # local admin user (DB-07)
sail composer dev                             # queue + logs + Vite+ HMR
# App: http://localhost   Mailpit: http://localhost:8025
```

### 4.4 `.env.example` (local defaults)

```dotenv
APP_NAME="<Project>"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
APP_TIMEZONE=UTC
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=<project_slug>
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0
REDIS_CACHE_DB=1

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="no-reply@example.test"
MAIL_FROM_NAME="${APP_NAME}"

VITE_PORT=5173
# SAIL_XDEBUG_MODE=develop,debug,coverage
```

### 4.5 Composer scripts (target shape)

```json
"setup": [
  "composer install",
  "@php -r \"file_exists('.env') || copy('.env.example', '.env');\"",
  "@php artisan key:generate",
  "@php artisan migrate --force",
  "bun install",
  "bun run build"
],
"dev": [
  "Composer\\Config::disableProcessTimeout",
  "bunx concurrently -c \"#c4b5fd,#fb7185,#fdba74\" \"php artisan queue:listen --tries=1\" \"php artisan pail --timeout=0\" \"bun run dev\" --names=queue,logs,vite --kill-others"
]
```

Quality scripts (`lint`, `test:lint`, `test:type-coverage`, `test:types`, `test:unit`, `test`) are kept as in the kit and run via `sail composer <script>`. `key:generate` must be guarded per ENV-12.

---

## 5. Data stores

### 5.1 MySQL

| ID | Requirement | Level |
|---|---|---|
| DB-01 | MySQL is the only relational database in every environment. The Sail MySQL image is pinned to the **same major version as the Forge server**. | MUST |
| DB-02 | `utf8mb4` / `utf8mb4_unicode_ci`, Laravel `strict` mode enabled. | MUST |
| DB-03 | Tests run against Sail's auto-created `testing` database (`phpunit.xml`: `DB_CONNECTION=mysql`, `DB_DATABASE=testing`). | MUST |
| DB-04 | Parallel testing works against MySQL (per-process `testing_test_N` databases); verify the `sail` user's privileges on `testing%`. | MUST |
| DB-05 | Migrations via `sail artisan make:migration`; FKs via `foreignId()->constrained()` with an explicit delete behaviour; FKs and hot filter/sort columns indexed; merged migrations never edited. | MUST |
| DB-06 | All timestamps stored in UTC; conversion only at the presentation layer (FE-18). | MUST |
| DB-07 | Every model has a factory with useful states and seeder coverage. `DatabaseSeeder` creates a known local **admin** user (credentials documented in the README, local only) plus sample pending invitations. | MUST |
| DB-08 | Agents inspect the schema with Boost's `database-schema` tool and use `database-query` for read-only queries. | SHOULD |

### 5.2 Redis

| ID | Requirement | Level |
|---|---|---|
| REDIS-01 | Redis is used via the `phpredis` extension (present in the Sail image and on Forge servers). | MUST |
| REDIS-02 | `CACHE_STORE`, `QUEUE_CONNECTION`, and `SESSION_DRIVER` are `redis` in local and production. | MUST |
| REDIS-03 | Cache uses a separate Redis database (`REDIS_CACHE_DB=1`) from queues and sessions (`REDIS_DB=0`), so `cache:clear` never drops queued jobs or sessions. | MUST |
| REDIS-04 | Tests do not depend on Redis: `phpunit.xml` keeps `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array`. Queue behaviour is asserted with `Queue::fake()` and dedicated job tests. CI therefore needs no Redis service. | MUST |
| REDIS-05 | Failed jobs are stored in MySQL (`failed_jobs`, `database-uuids` driver); `job_batches` is kept for batching. | MUST |
| REDIS-06 | The unused database-driver tables (`sessions`, `cache`, `cache_locks`, `jobs`) are removed from the default migrations before the first deploy. | SHOULD |
| REDIS-07 | Queue supervision in production: plain `queue:work` daemon or Horizon (OD-13). | MUST |

---

## 6. Backend architecture and conventions

### 6.1 Essentials configuration

`config/essentials.php` is published and committed.

| Configurable | Setting | Notes |
|---|---|---|
| Strict models | ON | Missing attributes, lazy loading, and assigning undefined attributes throw. |
| Automatic eager loading | ON | Keep `$with` minimal. |
| Unguarded models | **OFF** | D-08. |
| Immutable dates | ON | `CarbonImmutable` everywhere. |
| Force HTTPS | ON | Verify it does not break `http://localhost` under Sail; if it does, scope to non-local environments. |
| Safe console | ON | Blocks destructive commands in production. |
| Asset prefetching | ON | |
| Prevent stray requests | ON | |
| Fake sleep | ON | |

### 6.2 PHP code style

The kit's `pint.json` and `rector.php` are kept unchanged: `declare(strict_types=1)` everywhere, `final` classes, strict comparison, `DateTimeImmutable`, `mb_*` functions, fully imported symbols, no useless `else`, `protected` → `private`, fixed class element order; Rector's Laravel sets plus dead code, code quality, type declarations, privatization, early return, and coding style.

### 6.3 Architecture rules

| ID | Requirement | Level |
|---|---|---|
| ARCH-01 | **Actions:** `app/Actions`, named by what they do with no suffix, `final readonly`, single `handle()`, constructor-injected private dependencies, `DB::transaction()` for multi-model writes, created via `sail artisan make:action "Name" --no-interaction`. Callable from controllers, jobs, commands, and MCP tools. | MUST |
| ARCH-02 | **Cruddy controllers:** only the seven resource methods; non-CRUD verbs become new resource controllers. Controllers authorize → validate (Form Request) → call an Action → return an Inertia response or redirect. | MUST |
| ARCH-03 | Validation only in Form Requests with array-shape PHPDoc; only `validated()` data reaches Actions. | MUST |
| ARCH-04 | Authorization via Policies/Gates on the server; the frontend only hides UI based on server-provided abilities. | MUST |
| ARCH-05 | Models declare typed `@property` PHPDoc, use `casts()`, type relationships with generics, never lazy-load. | MUST |
| ARCH-06 | Backed enums with TitleCase cases. | MUST |
| ARCH-07 | `Inertia::render('path/to/page', $props)` with explicit, minimal props (arrays, API Resources, DTOs). Expensive data uses deferred or optional props. | MUST |
| ARCH-08 | Named routes; `route()` on the backend, Wayfinder on the frontend. | MUST |
| ARCH-09 | `env()` only inside `config/*.php`. | MUST |
| ARCH-10 | Jobs, commands, listeners, and notifications delegate business logic to Actions. Mailables and notifications that send email implement `ShouldQueue`. | MUST |
| ARCH-11 | APIs (if added) use API Resources and versioned routes. | MUST (when applicable) |
| ARCH-12 | No new top-level folders without approval; files generated via `sail artisan make:*`; sibling conventions followed. | MUST |
| ARCH-13 | PHPDoc over inline comments; no superfluous annotations. | MUST |
| ARCH-14 | Curly braces always, constructor promotion, explicit types everywhere, no empty zero-argument constructors. | MUST |
| ARCH-15 | Descriptive naming (`isRegisteredForDiscounts()`, not `discount()`). | MUST |
| ARCH-16 | Scheduled tasks are defined in `routes/console.php` and are idempotent. | MUST |

---

## 7. Authentication, invitations, and email verification

### 7.1 Authentication (Fortify)

| ID | Requirement | Level |
|---|---|---|
| AUTH-01 | Fortify features: password reset, email verification, and two-factor authentication (`confirm => true`, `confirmPassword => true`). **`Features::registration()` is removed.** | MUST |
| AUTH-02 | Fortify customisation only through `app/Actions/Fortify/*`. `CreateNewUser` is removed or unused, since accounts are created only by `AcceptInvitation` (INV-06). | MUST |
| AUTH-03 | Login rate-limited in `FortifyServiceProvider` (baseline: 5/min keyed by email + IP). | MUST |
| AUTH-04 | `Password::defaults()` defined centrally: min 12 characters, mixed case, numbers; `uncompromised()` in production. Used by invitation acceptance, password reset, and password change. | MUST |
| AUTH-05 | Every frontend reference to registration routes (links, pages, Wayfinder imports) is removed, otherwise the Wayfinder build fails. `GET /register` returns 404, asserted by a test. | MUST |
| AUTH-06 | Account settings pages (profile, password, two-factor) are retained and fully tested. | MUST |
| AUTH-07 | Mail templates are published (`vendor:publish --tag=laravel-mail`) once branding is defined. | SHOULD |

### 7.2 Email verification

| ID | Requirement | Level |
|---|---|---|
| VER-01 | `User` implements `MustVerifyEmail`. Every authenticated application route is behind the `verified` middleware. | MUST |
| VER-02 | Users created through invitation acceptance are marked verified at creation (`email_verified_at = now()`): they reached the form through a single-use link delivered to that address, which proves ownership. | MUST |
| VER-03 | Changing the email address in profile settings clears `email_verified_at` and sends a new verification link; the user is gated by `verified` until they confirm. | MUST |
| VER-04 | Verification and resend endpoints keep Fortify's throttling. | MUST |

### 7.3 Invitation-only onboarding

| ID | Requirement | Level |
|---|---|---|
| INV-01 | Only users allowed by `InvitationPolicy@create` may invite (who that is: OD-12; default admins only, via an `is_admin` boolean on `users`). | MUST |
| INV-02 | An `invitations` table stores: `email`, `token_hash`, `invited_by` (FK → users, `nullOnDelete`), `expires_at`, `accepted_at`, `revoked_at`, timestamps. At most one pending invitation per email. | MUST |
| INV-03 | Tokens are cryptographically random (≥ 64 characters); **only a hash is stored**, the plaintext exists only in the emailed link. Links expire after a configurable period (`config('invitations.expires_after_days')`, default 7) and are single-use. | MUST |
| INV-04 | Inviting an email that already belongs to a user fails validation. Resending issues a new token and invalidates the previous one. Revoking makes the link unusable immediately. | MUST |
| INV-05 | The invitation email is a queued notification to the invited address; locally it appears in Mailpit. | MUST |
| INV-06 | **Acceptance:** `GET` shows the invited email (read-only) plus name, password, and password confirmation fields; `POST` runs `AcceptInvitation`, which in one transaction creates the user (verified per VER-02), marks the invitation accepted, logs the user in, and redirects to the dashboard. | MUST |
| INV-07 | Invalid, expired, revoked, and already-used links all render the same neutral error page with no account-existence leakage. The acceptance endpoints are rate-limited. | MUST |
| INV-08 | **First user bootstrap:** since there is no public registration, an Artisan command (e.g. `sail artisan app:invite {email} --admin`) creates an invitation and prints the link. The same command is used on Forge for the first production admin. Documented in the README. | MUST |
| INV-09 | **Structure (Cruddy):** `InvitationController` (`index`, `store`, `destroy` = revoke), `InvitationResendController` (`store`), `InvitationAcceptanceController` (`create`, `store`). Actions: `CreateInvitation`, `ResendInvitation`, `RevokeInvitation`, `AcceptInvitation`. | MUST |
| INV-10 | A settings page lists pending invitations with resend and revoke (shadcn Table, Dialog for confirmation), visible only to users allowed by INV-01. | MUST |
| INV-11 | Expired and revoked invitations are pruned by the scheduler (`Prunable` model + `model:prune`) after a retention period. | SHOULD |
| INV-12 | Tests: feature tests for every Action and endpoint (valid, expired, reused, revoked, duplicate email, unauthorised inviter) and a browser test for the full invite → email → accept → dashboard flow (QA-09). | MUST |

---

## 8. Frontend requirements (Inertia v3 + React + shadcn/ui)

```
resources/js/
├── actions/        # Wayfinder-generated (controllers) — do not edit
├── routes/         # Wayfinder-generated (named routes) — do not edit
├── components/
│   └── ui/         # shadcn/ui generated primitives
├── hooks/
├── layouts/
├── lib/            # utils (cn(), date formatting, constants)
├── pages/
└── types/
```

| ID | Requirement | Level |
|---|---|---|
| FE-01 | TypeScript strict; `tsc --noEmit` clean; no `any` / `@ts-ignore` / `@ts-expect-error` without justification. | MUST |
| FE-02 | Page file paths match `Inertia::render()` names. | MUST |
| FE-03 | Page props and shared data (`auth.user`, flash, app name, abilities such as `canInvite`) are typed in `resources/js/types`. | MUST |
| FE-04 | Wayfinder output is never hand-edited and follows the kit's `.gitignore` policy. | MUST |
| FE-05 | All URLs come from Wayfinder imports; no hardcoded paths. | MUST |
| FE-06 | Forms use `<Form>` / `useForm` with Wayfinder `.form()`; per-field server errors; submit disabled while processing. | MUST |
| FE-07 | Non-navigational requests use Inertia v3 `useHttp`; no Axios without approval. | MUST |
| FE-08 | Every deferred prop has an animated skeleton. | MUST |
| FE-09 | Inertia v3 API changes respected (`Inertia::optional()`, `httpException` / `networkError`, `router.cancelAll()`). | MUST |
| FE-10 | shadcn components added only via `sail bunx shadcn@latest add <component>`; `components.json` is the source of truth; app components compose `ui/` primitives; edits to `ui/` files are minimal and noted in the PR; reuse before writing new components. | MUST |
| FE-11 | Tailwind 4 utilities + shadcn CSS-variable theme (light and dark); no inline styles for design values; `cn()` for class merging. | MUST |
| FE-12 | React Compiler enabled; no default `useMemo` / `useCallback` / `memo`. | SHOULD |
| FE-13 | `lucide-react` icons; Laravel flash messages shown as `sonner` toasts from one place in the app layout. | MUST |
| FE-14 | Accessibility: labelled inputs, keyboard-operable overlays, visible focus, WCAG AA contrast. | MUST |
| FE-15 | `vp lint` and `vp fmt` clean over `resources/`. | MUST |
| FE-16 | The kit's app shell and auth layouts are retained. | MUST |
| FE-17 | **SSR is off.** No SSR process runs on Forge. The `build:ssr` script is removed unless kept green at no cost. | MUST |
| FE-18 | **English UI.** UI strings are written directly in English; no i18n library; `<html lang="en">`. Money, numbers, and dates are formatted with `Intl` using the user's **format locale** (default `el-GR`, e.g. `1.234,56 €`, `31/12/2027`) and **timezone** (default `Europe/Athens`) from user preferences (PRD `PREF-01`). Money travels between server and client as integer cents. | MUST |

---

## 9. Testing and quality gates

| ID | Requirement | Level |
|---|---|---|
| QA-01 | `sail composer test` is the single gate: `test:lint`, `test:type-coverage` (100%), `test:types` (PHPStan max + `tsc`), `test:unit` (Pest, parallel, coverage **exactly 100%**, browser tests included). | MUST |
| QA-02 | Every change ships with tests; feature tests by default; created via `sail artisan make:test --pest`. | MUST |
| QA-03 | Tests use factories and states. | MUST |
| QA-04 | Every outgoing HTTP call in tests is faked. | MUST |
| QA-05 | Architecture tests: Pest `php`, `security`, `laravel` presets plus custom rules (Actions final/readonly with `handle()`; no debug functions; no `env()` outside config; controllers expose only resource methods). | MUST |
| QA-06 | Essentials behaviour (strict models, lazy-loading prevention, Safe Console) is covered by tests. | MUST |
| QA-07 | Iterate with targeted runs (`sail artisan test --compact --filter=…`); run the full gate before pushing; never delete tests without approval. | MUST |
| QA-08 | The 100% type coverage, PHPStan `max`, and 100% line coverage thresholds are permanent (D-06). Lowering any of them requires an explicit decision recorded in §19. | MUST |
| QA-09 | **Browser tests from day one** (Pest browser plugin + Playwright/Chromium, running inside Sail per ENV-14 and in CI). Required flows: login (success and failure), 2FA challenge, logout, password reset via the emailed link, invitation acceptance (valid and expired link), email change → re-verification, settings pages, invitation management. Plus a smoke test visiting every authenticated page and asserting no JavaScript errors or console errors. | MUST |
| QA-10 | A `.githooks/pre-commit` hook runs `./vendor/bin/sail composer test:lint` when the containers are running and skips with a warning when they are not. | SHOULD |

---

## 10. Continuous integration (GitHub Actions)

| ID | Requirement | Level |
|---|---|---|
| CI-01 | On every PR and push to `main`: PHP 8.5 with a coverage driver, MySQL service container (same major as Forge), `composer install` from lock, `bun install --frozen-lockfile`, Playwright Chromium install (`bunx playwright install --with-deps chromium`, cached by Playwright version), `bun run build`, then `composer test`. No Redis service (REDIS-04). | MUST |
| CI-02 | CI runs natively (no Sail) with the same PHP, MySQL, Node, Bun, and Playwright versions as the Sail image. | MUST |
| CI-03 | Branch protection on `main`: green CI (including the attribution checks in §13) and one review required. | MUST |
| CI-04 | After CI passes on `main`, a job triggers the Forge deployment (DEPLOY-07). | MUST |
| CI-05 | Weekly dependency update PRs via Dependabot or Renovate. | SHOULD |

---

## 11. Dependency management

| ID | Requirement | Level |
|---|---|---|
| DEP-01 | Remove `@update:requirements` from `post-update-cmd`; keep the script for deliberate manual use. | MUST |
| DEP-02 | Keep `roave/security-advisories`. | MUST |
| DEP-03 | Move `minimum-stability` from `dev` to `stable` once all packages have stable tags. | SHOULD |
| DEP-04 | Boost updates stay manual (AI-03). | MUST |

---

## 12. AI-assisted development (Claude Code + Laravel Boost)

| ID | Requirement | Level |
|---|---|---|
| AI-01 | Run `sail artisan boost:install` selecting **Claude Code only**, with guidelines, skills, and MCP enabled. | MUST |
| AI-02 | The MCP server in `.mcp.json` runs through Sail: command `./vendor/bin/sail`, args `["artisan", "boost:mcp"]`. | MUST |
| AI-03 | Boost resources are only regenerated with `sail artisan boost:update`; afterwards verify `CLAUDE.md` still instructs the use of `sail` (laravel/boost #508). | MUST |
| AI-04 | Custom guidelines in `.ai/guidelines/` (committed): the kit's `app.actions` and `general`, plus **sail**, **database-mysql**, **redis**, **controllers**, **frontend**, **invitations**, and **git** (GIT-02). | MUST |
| AI-05 | Frontend and testing guidelines spell out Inertia v3 and Pest 5 differences, and instruct Claude to prefer official upstream docs when `search-docs` returns older versions. | MUST |
| AI-06 | Skills: `laravel-best-practices`, `fortify-development`, `wayfinder-development`, `inertia-react-development`, `pest-testing`, `tailwindcss-development`, `infer-conventions`. Domain skills later under `.ai/skills/<name>/SKILL.md`. | MUST |
| AI-07 | Project rules in `.ai/rules/` (committed), recorded through Boost's `record-rule` tool. | MUST |
| AI-08 | **Committed:** `CLAUDE.md`, `.mcp.json`, `boost.json`, `.claude/skills/`, `.claude/settings.json`, `.ai/`. **Git-ignored:** `.claude/settings.local.json`. | MUST |
| AI-09 | Remove the kit's other agent artifacts: `.agents/`, `.amp/`, `.codex/`, `.cursor/`, `.gemini/`, `.junie/`, `AGENTS.md`, `GEMINI.md`, `opencode.json`. `boost.json` lists Claude Code only. | MUST |
| AI-10 | Agent operating rules in guidelines: `search-docs` before code changes; a test for every change and targeted test runs; `vendor/bin/pint --dirty --format agent` after PHP edits; no new dependencies, base folders, or documentation files without approval; no throwaway verification scripts; concise explanations. | MUST |
| AI-11 | Project-level Claude Code settings live in `.claude/settings.json` (committed) and include the attribution policy from GIT-01. Team-wide additions (e.g. permissions) go through a PR. | MUST |

---

## 13. No AI attribution in git history or pull requests

Nothing identifying Claude Code or Anthropic may appear in commit messages, PR titles, or PR descriptions. That includes `Co-Authored-By: Claude …`, `noreply@anthropic.com`, "🤖 Generated with [Claude Code](…)", `Claude-Session:` trailers, and `claude.ai/code/session_…` links. Human `Co-authored-by` trailers are preserved.

The Claude Code setting is the primary control, but it has two known gaps: users report it being ignored intermittently, and newer session trailers and links are not covered by it. The requirement is therefore enforced in four layers.

| Layer | ID | Mechanism | Catches |
|---|---|---|---|
| 1. Tool | GIT-01 | `.claude/settings.json` → `"attribution": { "commit": "", "pr": "" }` | The standard trailers and PR footer that Claude Code appends. |
| 2. Instructions | GIT-02 | `.ai/guidelines/git.md` compiled into `CLAUDE.md` | Attribution the model writes itself as part of the message text. |
| 3. Local enforcement | GIT-03 | `.githooks/commit-msg` strips matching lines | Anything that reaches a local commit. |
| 4. Remote enforcement | GIT-04, GIT-05 | CI rejects PRs with attributed commits; a workflow strips attribution from PR bodies | `--no-verify`, commits made elsewhere, PR descriptions. |

| ID | Requirement | Level |
|---|---|---|
| GIT-01 | `.claude/settings.json` sets `attribution.commit` and `attribution.pr` to empty strings. The deprecated `includeCoAuthoredBy` key is not used. Developers may set the same in `~/.claude/settings.json` for their other projects. | MUST |
| GIT-02 | `.ai/guidelines/git.md` instructs Claude never to add co-author trailers, "Generated with" lines, session trailers, session links, or robot emoji to commit messages, PR titles, or PR bodies, and to write commit messages in Conventional Commits format. | MUST |
| GIT-03 | `.githooks/commit-msg` removes every line matching the attribution pattern before the commit is created. Enabled via `git config core.hooksPath .githooks` (ENV-15). Must be POSIX `sh` and work on macOS, Linux, and WSL2. | MUST |
| GIT-04 | A CI job fails the PR if any commit message between base and head matches the pattern. Remediation is an interactive rebase / reword. | MUST |
| GIT-05 | A workflow on `pull_request` (`opened`, `edited`, `reopened`) removes matching lines from the PR body via the GitHub API. | MUST |
| GIT-06 | The repository uses squash merges with the (sanitised) PR title and description as the default commit message. | SHOULD |
| GIT-07 | Acceptance: on a scratch branch, ask Claude Code to commit and open a PR; neither the commits nor the PR body contains attribution, and GIT-04 passes. | MUST |

**Shared pattern** (case-insensitive, extended regex), used by GIT-03, GIT-04, and GIT-05:

```
co-authored-by:.*(claude|anthropic)|noreply@anthropic\.com|generated with.*claude|claude-session:|claude\.ai/code/session_
```

**`.claude/settings.json`**

```json
{
  "attribution": {
    "commit": "",
    "pr": ""
  }
}
```

**`.ai/guidelines/git.md`**

```markdown
# Git and pull requests

- Never add AI attribution to commit messages, PR titles, or PR descriptions. This includes
  `Co-Authored-By: Claude`, any `noreply@anthropic.com` address, "Generated with Claude Code",
  `Claude-Session:` trailers, `claude.ai/code/session_` links, and robot emoji.
- Write commit messages in Conventional Commits format: `type(scope): summary`, then a short body
  explaining why.
- PR descriptions contain: summary, notable decisions, test evidence, and requirement IDs touched.
```

**`.githooks/commit-msg`** (tested: strips Claude attribution, keeps human co-authors)

```sh
#!/bin/sh
# Strip AI attribution lines from every commit message.
set -eu
PATTERN='co-authored-by:.*(claude|anthropic)|noreply@anthropic\.com|generated with.*claude|claude-session:|claude\.ai/code/session_'
tmp="$(mktemp)"
grep -viE "$PATTERN" "$1" > "$tmp" || true
cat "$tmp" > "$1"
rm -f "$tmp"
```

**CI commit check** (step in the main workflow; checkout with `fetch-depth: 0`)

```yaml
- name: Reject AI attribution in commit messages
  if: github.event_name == 'pull_request'
  run: |
    PATTERN='co-authored-by:.*(claude|anthropic)|noreply@anthropic\.com|generated with.*claude|claude-session:|claude\.ai/code/session_'
    if git log --format=%B "${{ github.event.pull_request.base.sha }}..${{ github.event.pull_request.head.sha }}" | grep -iE "$PATTERN"; then
      echo "AI attribution found in commit messages. Reword the affected commits."
      exit 1
    fi
```

**`.github/workflows/strip-pr-attribution.yml`**

```yaml
name: Strip AI attribution from PR body
on:
  pull_request:
    types: [opened, edited, reopened]
permissions:
  pull-requests: write
jobs:
  sanitize:
    runs-on: ubuntu-latest
    steps:
      - env:
          GH_TOKEN: ${{ github.token }}
          PR: ${{ github.event.pull_request.number }}
          REPO: ${{ github.repository }}
        run: |
          PATTERN='co-authored-by:.*(claude|anthropic)|noreply@anthropic\.com|generated with.*claude|claude-session:|claude\.ai/code/session_'
          body="$(gh pr view "$PR" --repo "$REPO" --json body -q .body)"
          clean="$(printf '%s\n' "$body" | grep -viE "$PATTERN" || true)"
          if [ "$body" != "$clean" ]; then
            gh pr edit "$PR" --repo "$REPO" --body "$clean"
          fi
```

---

## 14. Deployment (Laravel Forge)

| ID | Requirement | Level |
|---|---|---|
| DEPLOY-01 | A Forge-provisioned server runs Nginx, PHP 8.5 (confirm availability at provisioning), MySQL (major version recorded here and mirrored in Sail per DB-01), Redis, and Bun. | MUST |
| DEPLOY-02 | Zero-downtime deployments are enabled on the site. | MUST |
| DEPLOY-03 | Deployment steps, in this order: `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction` → `bun install --frozen-lockfile` → `bun run build` (Wayfinder generation needs PHP and `vendor/` present) → `php artisan migrate --force` → `php artisan optimize` → `php artisan queue:restart` (or `horizon:terminate`, OD-13). | MUST |
| DEPLOY-04 | A Forge daemon runs the queue worker on the `redis` connection (`queue:work redis --tries=3 --max-time=3600`), or Horizon per OD-13. | MUST |
| DEPLOY-05 | The Forge scheduler is enabled (`schedule:run` every minute) for invitation pruning (INV-11) and future scheduled tasks. | MUST |
| DEPLOY-06 | SSL via Let's Encrypt; `APP_URL` uses `https`; HTTPS forced (Essentials). | MUST |
| DEPLOY-07 | Forge "deploy on push" is **disabled**. The CI job from CI-04 calls the site's Forge deploy hook (stored as the GitHub secret `FORGE_DEPLOY_HOOK_URL`), so only commits that passed the full gate are deployed. | MUST |
| DEPLOY-08 | Production environment variables are managed in Forge's environment editor and never committed: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_SECURE_COOKIE=true`, production mail credentials (OD-14). | MUST |
| DEPLOY-09 | `/up` is monitored for uptime. | MUST |
| DEPLOY-10 | Automated MySQL backups are configured through Forge with off-server storage, and a restore has been tested once. | SHOULD |
| DEPLOY-11 | The first production admin is created with the INV-08 command over SSH. | MUST |
| DEPLOY-12 | A staging site mirrors production (OD-15). | SHOULD |

---

## 15. Security and configuration baseline

| ID | Requirement | Level |
|---|---|---|
| SEC-01 | `APP_DEBUG=false` outside local. | MUST |
| SEC-02 | HTTPS enforced outside local; secure, HTTP-only, `SameSite=lax` session cookies in production. | MUST |
| SEC-03 | Safe Console active in production. | MUST |
| SEC-04 | `trustProxies` configured if the site sits behind a Forge load balancer, and when using `sail share`. | MUST (when applicable) |
| SEC-05 | Login throttling, 2FA, and rate-limited invitation acceptance (AUTH-03, INV-07). | MUST |
| SEC-06 | No secrets in the repository; `.env.example` has placeholders only. | MUST |
| SEC-07 | Mass-assignment protection on. | MUST |
| SEC-08 | Invitation tokens stored hashed only (INV-03). | MUST |
| SEC-09 | Redis on Forge is not publicly exposed (bound to the private interface / firewalled). | MUST |
| SEC-10 | Security headers / CSP strategy decided before the first production deploy (OD-10). | MUST (before first deploy) |

---

## 16. Repository and documentation

| ID | Requirement | Level |
|---|---|---|
| REPO-01 | `README.md` covers: stack summary, Sail onboarding (§4.3) including the git hooks step, the local admin credentials, the `app:invite` command, a table of Composer/Bun scripts, and links to this document and `.ai/guidelines`. | MUST |
| REPO-02 | `.editorconfig` and `.gitattributes` from the kit are kept. | MUST |
| REPO-03 | This document is versioned in the repository (`docs/laravel-foundation-requirements.md`). | MUST |
| REPO-04 | Conventional Commits (reinforced by GIT-02). | MUST |

---

## 17. Milestone 0 — acceptance criteria

1. On a machine with only Docker, a new developer follows the README and reaches `http://localhost` within 15 minutes (excluding image download).
2. `/register` returns 404; no registration links exist in the UI.
3. The seeded admin invites a new email → the invite appears in Mailpit → the link opens the acceptance form → the account is created, verified, and logged in. Expired, revoked, and reused links show the neutral error page.
4. Login with 2FA, logout, password reset, and email change → re-verification all work in the browser.
5. Cache, queue, and sessions use Redis locally (`sail artisan config:show cache.default` etc.), and queued mail is processed by the `composer dev` queue listener.
6. HMR works from the host browser.
7. `sail composer test` passes: lint clean, 100% type coverage, PHPStan max 0 errors, `tsc` 0 errors, 100% coverage, all feature **and browser** tests green against MySQL in parallel.
8. The same gate passes in CI, including GIT-04.
9. A Claude Code session connected to Boost MCP through Sail can create a commit and a PR with **no attribution** in either (GIT-07).
10. No SQLite references remain; no non-Claude agent artifacts remain.
11. A green `main` deploys to Forge via the deploy hook; the app is served over HTTPS; the queue worker and scheduler are running; the first production admin was created via `app:invite`.
12. `.ai/guidelines`, `.ai/rules`, `.claude/settings.json`, `.githooks/`, and `config/essentials.php` are committed and reviewed.

---

## 18. Suggested bootstrap order

1. Create the project from `nunomaduro/laravel-starter-kit-inertia-react`.
2. Remove non-Claude agent artifacts (AI-09); add `.claude/settings.json`, `.githooks/`, and the attribution workflows (§13) **before the first AI-assisted commit**.
3. Install Sail with MySQL, Redis, and Mailpit; publish the Dockerfiles; add Playwright (ENV-14); set a unique image name; write `.env.example`.
4. Strip SQLite; point `phpunit.xml` at the MySQL `testing` database; adapt `composer dev` / `setup`.
5. Verify Vite+ HMR from the host.
6. Run `sail composer test` and fix whatever MySQL and the browser tests surface.
7. Apply DEP-01 and STACK-02; publish and commit `config/essentials.php`.
8. `sail artisan boost:install` (Claude Code only); add custom guidelines; confirm MCP through Sail.
9. Disable registration; implement invitations and email verification (§7) with their tests.
10. Architecture tests and Essentials behaviour tests.
11. CI, branch protection, attribution checks.
12. Provision Forge; configure the deploy hook, daemon, scheduler, SSL, and backups; decide OD-10 and OD-14; first deploy; create the first admin.
13. README; walk through the acceptance criteria.

---

## 19. Decision log

### 19.1 Resolved

| ID | Question | Decision |
|---|---|---|
| OD-01 | Cache / queue / session drivers | **Redis** |
| OD-02 | Registration model | **Private, invitation link only** |
| OD-03 | Mandatory email verification | **Yes** (invitation acceptance counts as verification, VER-02) |
| OD-04 | Locale | **Single locale, English** (display timezone split out as OD-16) |
| OD-16 | Display timezone and formatting | **Per-user preference**, defaults `Europe/Athens` and `el-GR` formatting (set by the PRD; FE-18 amended) |
| OD-05 | Inertia SSR | **Off** *(answer given as "OD-6 → off"; interpreted as OD-05 — please confirm)* |
| OD-06 | Deployment target | **Laravel Forge** |
| OD-07 | Coverage policy | **Keep 100%** |
| OD-08 | Browser tests | **From the start** |
| OD-09 | Boost-generated agent files | **Commit** |
| OD-11 | AI agents | **Claude Code only** |

### 19.2 Open

| ID | Question | Default if unanswered | Needed by |
|---|---|---|---|
| OD-10 | Security headers / CSP approach | — | Before first deploy |
| OD-12 | Who can send invitations? | Admins only (`is_admin` flag); first admin via `app:invite --admin` | Before §7 implementation |
| OD-13 | Queue supervision: plain `queue:work` daemon or Horizon? | `queue:work` daemon | Before first deploy |
| OD-14 | Transactional email provider for production (invitations and verification depend on it) | — | Before first deploy |
| OD-15 | Staging environment | Staging site on Forge, auto-deployed from green `main`; production deployed manually | Before first deploy |

---

## 20. Sources

- Essentials — https://github.com/nunomaduro/essentials
- Laravel News, "Better defaults for your Laravel applications with Essentials" — https://laravel-news.com/laravel-essentials
- Jon Purvis, "Opinionated Starter Kit by Nuno Maduro" — https://jonathanpurvis.co.uk/opinionated-starter-kit-by-nuno-maduro/
- Laravel Starter Kit (Blade) — https://github.com/nunomaduro/laravel-starter-kit
- Laravel Starter Kit (Inertia & React) — https://github.com/nunomaduro/laravel-starter-kit-inertia-react
- Laravel 13.x — Sail: https://laravel.com/docs/13.x/sail
- Laravel — Starter Kits: https://laravel.com/docs/master/starter-kits
- Laravel 13.x — Boost: https://laravel.com/docs/boost
- laravel/boost issue #508 (boost:update drops Sail): https://github.com/laravel/boost/issues/508
- Laravel News, "Support for Bun lands in Laravel Sail and Forge": https://laravel-news.com/laravel-sail-bun
- Claude Code settings reference (`attribution`): https://code.claude.com/docs/en/settings
- anthropics/claude-code issues on attribution gaps: #4224 (setting intermittently ignored), #41873 (session URL not covered), #77830 (`Claude-Session:` trailer not covered)

---