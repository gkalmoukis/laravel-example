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
