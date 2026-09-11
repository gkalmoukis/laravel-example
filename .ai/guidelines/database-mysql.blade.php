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
