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
