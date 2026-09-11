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
