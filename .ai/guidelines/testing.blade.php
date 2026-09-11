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
