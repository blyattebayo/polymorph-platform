# Authentication

The browser signs in with email and password and receives one opaque `pmph_session`
cookie. Only its SHA-256 digest is stored in `auth_sessions`; every protected browser
request resolves the user with one lookup of the digest, unexpired session, and active
user. Logout, password changes, account restrictions, explicit revocation, and the
per-user session limit physically delete session rows. Core and admin routes accept only
the session cookie, while extension integration routes accept only a scoped personal
access token in `Authorization: Bearer ...`. OAuth can later create the same server-side
session after its callback, without changing request authentication.

| Scenario | Credential | Route guard | Persistence |
| --- | --- | --- | --- |
| Login | email + password | public | `users`, then `auth_sessions` |
| Browser request | `pmph_session` cookie | `auth:session` | `auth_sessions` + `users` |
| Public media with optional identity | optional `pmph_session` | `session.optional` | same lookup when present |
| Extension integration | PAT bearer | `auth:pat` | `auth_personal_access_tokens` + `users` |
| Logout/revoke | authenticated session | `auth:session` | delete from `auth_sessions` |
