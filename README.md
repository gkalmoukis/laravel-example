# Fin

A private personal finance planner and tracker. Invitation-only, English UI, Greek-style number
and date formatting by default.

- **Product requirements:** [`docs/prd.md`](docs/prd.md)
- **Foundation requirements:** [`docs/laravel-foundation-requirements.md`](docs/laravel-foundation-requirements.md)
- **Deployment:** [`docs/deployment.md`](docs/deployment.md)
- **Agent instructions:** [`CLAUDE.md`](CLAUDE.md), compiled from [`.ai/guidelines`](.ai/guidelines)

## Stack

Laravel 13 · Inertia v3 · React 19 · TypeScript · shadcn/ui · Tailwind 4 · MySQL 8.4 · Redis ·
Laravel Sail · Pest 5 with Playwright · PHPStan at max · deployed to Laravel Forge.

## Getting started

Everything runs in Docker through Laravel Sail. You need Docker with Compose v2 and nothing
else — no host PHP, Composer, Node or Bun.

```bash
git clone git@github.com:gkalmoukis/laravel-example.git fin && cd fin
cp .env.example .env
git config core.hooksPath .githooks          # attribution stripping + pre-commit lint

# Install PHP dependencies without host PHP. The image runs PHP 8.4 while this project
# needs 8.5, so platform requirements are ignored for this one bootstrap step; every
# later command runs on 8.5 inside the container.
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
  laravelsail/php84-composer:latest composer install --ignore-platform-reqs

./vendor/bin/sail build                       # includes Playwright's Chromium
sail up -d
sail composer setup                           # key, migrate, bun install, build
sail artisan app:invite you@example.com --admin   # your first account; prints the link
sail composer dev                             # queue + logs + Vite with HMR
```

The app is at <http://localhost> and every outgoing email is captured by Mailpit at
<http://localhost:8025>.

Add `alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'` to your shell so the
commands below work as written.

### Inviting people

There is no public registration; `/register` does not exist, and there is no seeded account
in any environment. The first account is created from the console:

```bash
sail artisan app:invite someone@example.com --admin
```

The command prints the invitation link and emails it. `--admin` lets the new account invite
others, which is the only thing the admin flag grants. Admins signed in to the app can invite
from **Settings → Invitations**.

Invitation links are single-use and expire after seven days
(`config/invitations.php`).

## Commands

| Command | What it does |
|---|---|
| `sail up -d` / `sail down` | Start / stop the stack |
| `sail down -v` | Stop **and delete** the database and Redis volumes |
| `sail composer setup` | Key, migrate, install and build. Safe to re-run; never rotates an existing `APP_KEY` |
| `sail composer dev` | Queue listener, logs and the Vite dev server together |
| `sail composer test` | **The gate.** Lint, 100% type coverage, PHPStan max, `tsc`, 100% line coverage, browser tests |
| `sail composer lint` | Apply Rector, Pint and the frontend formatter |
| `sail artisan test --compact --filter=X` | Run one slice while iterating |
| `sail artisan app:invite {email} [--admin]` | Create an invitation and print the link |
| `sail bunx shadcn@latest add <component>` | Add a UI primitive |
| `sail artisan wayfinder:generate --with-form` | Regenerate typed routes (the flag is required) |

`sail composer test` must be green before every commit and is enforced again in CI.

## Conventions

Actions hold the business logic, controllers stay cruddy and thin, validation lives in form
requests, authorization lives in policies, and a record belonging to another user returns 404
rather than 403. Money is integer cents. Commit messages follow Conventional Commits.

The full rules are in [`.ai/guidelines`](.ai/guidelines) and are compiled into `CLAUDE.md`;
decisions that should not be reverted are recorded in [`.ai/rules`](.ai/rules).
