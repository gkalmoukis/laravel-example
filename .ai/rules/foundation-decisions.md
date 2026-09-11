# Recorded project rules

Decisions taken during implementation that later sessions must respect. Each records what was
decided and why, so it is not "corrected" back.

## Mass assignment stays protected

The starter kit ships `Unguard => true` in `config/essentials.php`. This project overrides it to
`false`. Models therefore declare their fillable attributes explicitly, and columns that must
never come from request data — verification timestamps, the admin flag, remember tokens — are
written with `forceFill()` by the Action that owns them.

## Password rules are defined in FortifyServiceProvider, not Essentials

Essentials' `SetDefaultPassword` applies no rules outside production and adds `symbols()` inside
it. The requirement is at least twelve characters with mixed case and numbers in every
environment, and breach checking in production only. Essentials' version is disabled and
`Password::defaults()` is set explicitly.

## Assertions execute in development and tests

`zend.assertions = 1` is set in `docker/8.5/php.ini`. PHP's default of `-1` compiles `assert()`
out entirely, which hides contract violations and leaves those lines uncovered, breaking the
100% coverage gate. CI sets the same value. Production keeps PHP's default.

## Eloquent scopes are written as static query helpers

Rector forces `scope*` methods — and `#[Scope]`-attributed ones — to be `protected`, while the
strict architecture preset forbids protected methods on final classes. The two cannot both be
satisfied by a scope, so reusable queries are plain `public static` methods returning a Builder.

## Wayfinder must be regenerated with --with-form

`sail artisan wayfinder:generate` without `--with-form` strips every `.form()` helper and breaks
the build, because `vite.config.ts` generates them. Always pass the flag, or let Vite do it.

## Browser tests assert through the interface

The application under browser test runs in a separate process. Under MySQL's REPEATABLE READ the
test's own transaction cannot see rows that process commits, so re-reading a model returns a
stale snapshot. Assert what the user sees instead.

## Invitations carry the admin grant

`invitations.is_admin` is not in the original schema list. It exists because `app:invite --admin`
and the first production admin have no other way to grant the flag.

## Boost has no Pest 5 skill

Boost's catalogue stops at Pest 4 and has no `infer-conventions` skill at all. The gap is covered
by `.ai/guidelines/testing.blade.php`. Prefer official Pest 5 documentation over `search-docs`
results describing Pest 3.x or 4.x.
