# Deployment (Laravel Forge)

Everything here is pending: it needs a Forge account, a provisioned server, DNS and a
transactional email provider, none of which exist yet. The CI side is already written and
committed — `.github/workflows/deploy.yml` triggers on a green `tests` run on `main` and does
nothing until the `FORGE_DEPLOY_HOOK_URL` secret is set.

## Decisions still open

These block the first production deploy and need an answer before it happens.

| ID | Question | Working default |
|---|---|---|
| OD-10 | Security headers and CSP approach | none chosen yet |
| OD-13 | Queue supervision: a plain `queue:work` daemon, or Horizon | plain daemon, as below |
| OD-14 | Transactional email provider — invitations and verification depend on it | none chosen yet |
| OD-15 | Staging environment | staging site auto-deployed from green `main`, production deployed manually |

## Server

1. Provision an Ubuntu server with **PHP 8.5**, MySQL, Redis and Bun. Confirm PHP 8.5 is
   available in Forge at provisioning time; the application requires it.
2. **Pin the MySQL major version to match Sail and CI.** Both currently use **8.4**. If the
   server runs a different major, change `compose.yaml` and `.github/workflows/tests.yml`
   together so all three agree (DB-01).
3. Bind Redis to the private interface and firewall it. It must not be publicly reachable.

## Site

4. Create the site with zero-downtime deployments enabled.
5. Deployment script, in this order — Wayfinder generation during `bun run build` needs PHP and
   `vendor/` present, so the order matters:

   ```bash
   composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   bun install --frozen-lockfile
   bun run build
   php artisan migrate --force
   php artisan optimize
   php artisan queue:restart
   ```

6. Turn **off** Forge's "deploy on push". Deployment is triggered only by CI, so nothing that
   failed the gate can reach production.
7. Copy the site's deploy hook URL into the GitHub repository secret
   `FORGE_DEPLOY_HOOK_URL`. The deploy workflow stays inert until this exists.
8. Add a daemon: `php artisan queue:work redis --tries=3 --max-time=3600` (or Horizon, per
   OD-13).
9. Enable the scheduler (`php artisan schedule:run` every minute). It prunes finished
   invitations.
10. Issue a Let's Encrypt certificate. `APP_URL` uses `https`; HTTPS is forced outside local.

## Environment

Set these in Forge's environment editor. They are never committed.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<domain>

DB_CONNECTION=mysql
DB_DATABASE=<database>
DB_USERNAME=<user>
DB_PASSWORD=<password>

REDIS_HOST=127.0.0.1
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true

MAIL_MAILER=<provider>        # OD-14
MAIL_FROM_ADDRESS=<address>
```

Leave `zend.assertions` at PHP's production default. Assertions are enabled only in the Sail
image and CI, where they are a development aid and a coverage requirement.

## First account

There is no public registration, so the first production account is created over SSH:

```bash
php artisan app:invite you@yourdomain.com --admin
```

The command prints the link and sends the email. This is why OD-14 must be answered first —
without a working mail provider the invitation cannot be delivered, though the printed link
still works.

## After the first deploy

11. Point uptime monitoring at `/up`.
12. Configure automated MySQL backups with off-server storage, and restore one once to prove it
    works.
