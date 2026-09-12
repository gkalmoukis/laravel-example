<laravel-boost-guidelines>
=== .ai/app.actions rules ===

# App/Actions guidelines

- This application uses the Action pattern and prefers for much logic to live in reusable and composable Action classes.
- Actions live in `app/Actions`, they are named based on what they do, with no suffix.
- Actions will be called from many different places: jobs, commands, HTTP requests, API requests, MCP requests, and more.
- Create dedicated Action classes for business logic with a single `handle()` method.
- Inject dependencies via constructor using private properties.
- Create new actions with `php artisan make:action "{name}" --no-interaction`
- Wrap complex operations in `DB::transaction()` within actions when multiple models are involved.
- Some actions won't require dependencies via `__construct` and they can use just the `handle()` method.

<!-- Example action class -->
```php
<?php

declare(strict_types=1);

namespace App\Actions;

final readonly class CreateFavorite
{
    public function __construct(private FavoriteService $favorites)
    {
        //
    }

    public function handle(User $user, string $favorite): bool
    {
        return $this->favorites->add($user, $favorite);
    }
}
```

=== .ai/controllers rules ===

# Controllers

Controllers are "cruddy by design" and stay thin.

- A controller exposes only the seven resource methods: `index`, `create`, `store`, `show`,
  `edit`, `update`, `destroy`. An architecture test enforces this.
- A non-CRUD verb becomes its own resource controller. "Resend invitation" is
  `InvitationResendController@store`, not `InvitationController@resend`.
- The order inside a method is always: authorize, then validate, then call an Action, then
  return an Inertia response or a redirect.
- Authorization uses policies via `Gate::authorize(...)`. A record belonging to another user
  must return **404**, not 403, so its existence is not revealed — return
  `Response::denyAsNotFound()` from the policy.
- Validation lives only in Form Requests, with array-shape PHPDoc. Only `validated()` data
  reaches an Action.
- Never put business logic in a controller; it belongs in an Action.
- Routes are named, and the frontend imports Wayfinder output rather than hardcoding paths.

=== .ai/database-mysql rules ===

# Database

MySQL is the only relational database, in every environment. There is no SQLite anywhere and
none may be reintroduced.

- Create migrations with `sail artisan make:migration`; never hand-write the file path.
- Foreign keys use `foreignUuid(...)->constrained()` — `users.id` is a **UUID**, not an
  auto-incrementing integer — and always declare an explicit delete behaviour
  (`cascadeOnDelete`, `nullOnDelete`, `restrictOnDelete`).
- Index every foreign key and every column used for filtering or sorting.
- A migration that has been merged is never edited; add a new one instead.
- Timestamps are stored in UTC. Convert only when presenting.
- Money is stored as integer cents in `unsignedBigInteger` columns named `*_cents`. Floats are
  forbidden in money logic.
- MySQL treats NULLs as distinct in unique indexes, so a unique index over a nullable column
  does not prevent duplicate rows where that column is NULL. Enforce those rules in the Action
  and add a test.
- Every model has a factory with useful states, and seeders that use them.
- Tests run against the `testing` database and may run in parallel; never assume an empty table
  unless the test created that state.

=== .ai/frontend rules ===

# Frontend

Inertia v3 with React 19, TypeScript and shadcn/ui.

- Inertia is **v3**: use `Inertia::optional()` rather than `lazy()`, `useHttp` for
  non-navigational requests rather than Axios, and the v3 error handling hooks. Documentation
  returned by `search-docs` may describe Inertia 1.x or 2.x; prefer the official v3 docs when
  they disagree.
- Pest is **5.x** and the browser plugin drives Playwright. Older Pest 3/4 examples may not
  apply.
- Page component paths match the string passed to `Inertia::render()` exactly.
- All URLs come from Wayfinder imports. Never hardcode a path. Wayfinder output in
  `resources/js/actions` and `resources/js/routes` is generated — never edit it, and regenerate
  with `sail artisan wayfinder:generate --with-form` (the `--with-form` flag matters; without it
  every `.form()` helper disappears and the build breaks).
- Forms use `<Form>` with a Wayfinder `.form()`, show per-field server errors, and disable
  submission while processing.
- Add shadcn components only with `sail bunx shadcn@latest add <component>`; compose them rather
  than editing `components/ui`.
- TypeScript is strict and `tsc --noEmit` must be clean. No `any`, no `@ts-ignore`.
- The UI is English. Money and dates are formatted with `Intl` using the user's format locale
  and timezone. Money crosses the wire as integer cents.
- Every page must work at 375px and 1280px, be keyboard operable, and have labelled inputs.

=== .ai/general rules ===

# General Guidelines

- Don't include any superfluous PHP Annotations, except ones that start with `@` for typing variables.

=== .ai/git rules ===

# Git and pull requests

- Never add AI attribution to commit messages, PR titles, or PR descriptions. This includes
  `Co-Authored-By: Claude`, any `noreply@anthropic.com` address, "Generated with Claude Code",
  `Claude-Session:` trailers, `claude.ai/code/session_` links, and robot emoji.
- Commit messages are a **subject line only**, at most 60 characters: a plain type prefix with no
  scope parentheses, then a short summary — `feat: quick add transactions`. No body, no
  requirement-ID refs, no trailers. Requirement IDs still belong in code PHPDoc, not in git.
- PR titles and descriptions follow the same restraint: a plain summary, no requirement IDs.

=== .ai/invitations rules ===

# Invitations and access

There is no public registration. `/register` does not exist and must not be reintroduced — any
reference to a registration route breaks the Wayfinder build.

- Accounts are created only by `AcceptInvitation`, reached through a single-use emailed link.
- Invitation tokens are cryptographically random and only their SHA-256 hash is stored. The
  plaintext exists solely in the emailed link. Never log it or persist it.
- Accepting an invitation marks the account verified, because reaching the form proves control
  of the address.
- Invalid, expired, revoked and already-used links must all render the **same** neutral page.
  Never reveal whether an address was invited or already has an account.
- Only admins may invite. That is the only capability the admin flag grants; admins have no
  access to other users' data.
- The first account on any environment is created with `sail artisan app:invite {email} --admin`.

=== .ai/redis rules ===

# Redis

Redis backs the cache, the queue and sessions in local and production, through the phpredis
extension.

- Queues and sessions use Redis database 0; the cache uses database 1, so clearing the cache
  never drops queued jobs or sessions.
- Failed jobs and job batches live in MySQL, not Redis.
- Tests do **not** use Redis: `phpunit.xml` pins the cache to `array`, the queue to `sync` and
  sessions to `array`. Assert queue behaviour with `Queue::fake()` and dedicated job tests
  rather than by running a worker.
- Anything that sends email or does slow work implements `ShouldQueue`.

=== .ai/sail rules ===

# Sail is the only runtime

Everything runs inside Docker via Laravel Sail. There is no host PHP, Composer, Node or Bun,
so a bare `php`, `composer`, `artisan`, `bun` or `npm` command will fail.

Always prefix commands:

- `sail artisan ...` — never `php artisan ...`
- `sail composer ...` — never `composer ...`
- `sail bun ...` / `sail bunx ...` — never `npm ...`
- `sail test` or `sail artisan test` — never `./vendor/bin/pest` directly

Useful entry points:

- `sail up -d` starts mysql, redis, mailpit and the app; `sail down` stops them.
- `sail down -v` also deletes the database and redis volumes. It is destructive.
- `sail composer dev` runs the queue listener, Pail and the Vite+ dev server together.
- `sail composer test` is the single quality gate and must be green before any commit.
- Mail is captured by Mailpit at http://localhost:8025; nothing leaves the machine.

If Sail is not running, start it rather than falling back to host tooling.

=== .ai/testing rules ===

# Testing

`sail composer test` is the single gate and must be green before every commit. It runs lint,
100% type coverage, PHPStan at max, `tsc`, and Pest in parallel at **exactly** 100% line
coverage, browser tests included. None of those thresholds may be lowered.

This project uses **Pest 5**. Boost ships no Pest 5 skill (its catalogue stops at Pest 4), so
prefer the official Pest 5 documentation over anything `search-docs` returns for Pest 3.x/4.x.

- Write a test for every change; feature tests by default. Create them with
  `sail artisan make:test --pest`.
- Iterate with `sail artisan test --compact --filter=...` and run the whole gate before pushing.
- Never delete or weaken an existing test to make a change pass.
- Use factories and states rather than hand-built fixtures.
- Fake every outgoing HTTP call; unfaked requests fail the suite by design.
- Time is frozen in tests. Datetime columns have second precision, so compare formatted values
  rather than instants when asserting a stored timestamp.
- Assertions that a route is denied for another user's record expect **404**, not 403.

## Browser tests

- Browser tests run Playwright inside the Sail image; no extra setup is needed.
- The application runs in a **separate process**, so the test's own transaction cannot see rows
  that process commits. Assert through the interface, not by re-reading a model.
- Finish an interaction with an assertion that waits — a path change or visible text — before
  asserting anything else, otherwise the assertion can run before the request lands.
- Every browser test asserts no JavaScript and no console errors.
- Failures save a screenshot under `tests/Browser/Screenshots`; read it before guessing.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v5
- phpunit/phpunit (PHPUNIT) - v13
- rector/rector (RECTOR) - v2
- @inertiajs/react (INERTIA_REACT) - v3
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail bun run build`, `vendor/bin/sail bun run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail bun run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `vendor/bin/sail artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail bun run build` or ask the user to run `vendor/bin/sail bun run dev` or `vendor/bin/sail composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `vendor/bin/sail artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `vendor/bin/sail artisan make:test --pest SomeFeatureTest` instead of `vendor/bin/sail artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `vendor/bin/sail artisan test --compact` or filter: `vendor/bin/sail artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
