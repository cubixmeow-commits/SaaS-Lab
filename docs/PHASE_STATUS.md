# Phase Status

## Current phase

**Phase 2 — Shared authentication** (complete — awaiting approval to start Phase 3)

## Completed phases

- Phase 1 — Foundation (installer, SQLite, migrations, bootstrap)
- Phase 2 — Shared authentication (register/login/logout/profile/roles/visit token)

## Phase 2 summary

Implemented shared account authentication on top of the Phase 1 platform schema.

- `Auth` service with the public `auth()` helper API
- Registration, login, logout, and profile (display name) pages
- Password hashing/verification, generic failure messaging
- Admin/member roles and suspended-account rejection
- Session ID regeneration after authentication
- Stable `lab_visit_token` (survives regenerate; cleared on logout)
- `lab_opened_projects` session set helpers for later event dedupe (cleared on logout)
- `requireLogin()` / `requireAdmin()` / `canAccessProject()` authorization helpers
- Routes: `/register`, `/login`, `/logout`, `/profile`

## Files created

```
CREATED
core/Auth.php
app/account/pages/register.php
app/account/pages/login.php
app/account/pages/logout.php
app/account/pages/profile.php
app/account/views/register.php
app/account/views/login.php
app/account/views/logout.php
app/account/views/profile.php
app/shared/views/errors/403.php
scripts/verify_phase2.php
```

## Files modified

```
MODIFIED
core/bootstrap.php
core/helpers.php
core/Router.php
app/shared/pages/home.php
app/shared/views/home.php
app/shared/views/layouts/main.php
app/install/pages/install.php
public/assets/app.css
docs/PHASE_STATUS.md
```

## Verification performed

1. `php -l` on application PHP files — clean
2. `php scripts/verify_phase2.php` — passed:
   - Registration + auto-login
   - Duplicate registration generic failure
   - Login success/failure
   - Suspended user blocked
   - Logout clears auth, visit token, opened-projects set
   - Admin vs member role / archived access rules
   - Profile name update
   - Visit token stability within a visit
3. HTTP checks via `php -S 127.0.0.1:8080`:
   - Install → redirect to `/login`
   - Admin login → home shows signed-in admin
   - Profile name update persists
   - Logout → `/login`
   - Member registration works
   - Duplicate registration shows generic error
   - Invalid login shows generic error
   - Unauthenticated `/profile` redirects to `/login`

## Known issues / notes

- Founder Dashboard routes are not wired yet; `requireAdmin()` is ready for Phase 3.
- Email change and password reset remain out of V1 scope by design.
- Runtime DB/lock files from local verification are gitignored.

## Assumptions

- Same as Phase 1: document root `public/`, `config.local.php` required for install.
- V1 uses all-members access for `lab` projects; archived is admin-only.

## Next phase (do not start without approval)

**Phase 3 — Platform routing and layouts:** expand front controller for `/projects`, `/founder`, authorization gates, and shared authenticated layouts.
