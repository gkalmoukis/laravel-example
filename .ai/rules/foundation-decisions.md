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

## Money is for stored amounts; derived figures that can go negative are signed cents

`Money` refuses negative values on purpose: a stored amount's direction comes from its
type, never its sign. But plenty of *derived* figures are legitimately negative — net
worth when debts exceed assets, variance when actual is under plan, net cash flow in a
losing month, a closing balance that goes below zero (which the forecast alerts on).

Those are returned as plain signed `int` cents, not `Money`. Do not clamp them at zero to
fit them into `Money`; that would silently report a negative net worth as nothing.

## Commit messages are a subject line only

Earlier guidance asked for Conventional Commits with a scope, a body explaining why, and the
requirement IDs touched — and the first milestones were written that way. The project owner
asked for the opposite: one line, at most 60 characters, a plain type prefix without scope
parentheses, no body, no IDs. `feat: quick add transactions`, not
`feat(transactions): quick add [TXQ-01..TXQ-10]`.

Requirement IDs have not gone anywhere; they stay in code PHPDoc, where they sit next to the
logic they justify rather than in a log nobody greps. The same restraint applies to PR titles
and descriptions.

## The remaining work is driven by a backlog and a loop

`.claude/backlog.md` holds every remaining v1 item in dependency order, and
`.claude/commands/next-item.md` implements exactly one of them per invocation — read the PRD for
its requirement IDs, build, test, commit, tick. The last item of each milestone carries
`<!-- gate -->`, which is where the full `sail composer test` runs; other items run targeted
Pest plus `test:lint` and `test:types`.

All of it stays local on one branch. The loop never pushes and never opens a pull request.
