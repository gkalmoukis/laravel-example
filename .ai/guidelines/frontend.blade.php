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
