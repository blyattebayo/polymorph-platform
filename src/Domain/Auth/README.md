# Authentication

The browser signs in with email and password and receives one opaque `pmph_session`
cookie. Only its SHA-256 digest is stored in `auth_sessions`; every protected browser
request resolves the user with one lookup of the digest, unexpired session, and active
user. Logout, password changes, account restrictions, explicit revocation, and the
per-user session limit physically delete session rows.

Core and admin routes accept only the browser session cookie. The Context Router MCP
protocol is a separate OAuth protected resource: core issues an audience-bound opaque
bearer after authorization code + S256 PKCE, and the plugin accepts it only on
`/protocol`.

| Scenario | Credential | Route guard | Persistence |
| --- | --- | --- | --- |
| Login | email + password | public | `users`, then `auth_sessions` |
| Browser request | `pmph_session` cookie | `auth:session` | `auth_sessions` + `users` |
| Public media with optional identity | optional `pmph_session` | `session.optional` | same lookup when present |
| MCP authorization | code + PKCE | public `/oauth/*` routes | OAuth clients, codes, grants, access tokens |
| Context Router MCP request | OAuth bearer | `oauth.resource` | OAuth access token + grant + active user |
| Logout/revoke | authenticated session | `auth:session` | delete from `auth_sessions` |
