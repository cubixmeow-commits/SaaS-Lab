# SaaS Lab V1 Implementation Plan

## Repository assessment

**Date:** 2026-07-18  
**Branch base:** `main` (`7c8d358` — initial README only)

| Item | Status |
|------|--------|
| Existing application code | None |
| Existing migrations / schema | None |
| Existing templates | None |
| Existing tests | None |
| README | One-paragraph product blurb only |
| Framework / Composer | Not present (correct for V1) |

The repository is a greenfield start. Spec structure and errata apply in full. Nothing functioning will be overwritten.

## Assumptions

1. Document root on Hostinger will point at `/public` (Apache + `.htaccess` rewrite).
2. Root `install.php` remains available for alternate document-root layouts and CLI-style checks; primary UX is `/install` via the front controller.
3. PHP 8.2+ with `pdo_sqlite` is available (verified locally as 8.3.6 during Phase 1).
4. `config.local.php` is the sole untracked override; committed defaults live in `config/config.example.php`.
5. Session cookie `path` defaults to `null` and is derived from `base_url` path (errata §5).
6. `projects.is_active` is **not** created (errata §9); `access_mode` is authoritative.
7. Template core action for V1 starter is `item_created` (errata §3), not a hard-coded `core_action_completed` counter.
8. No Composer, Node, Docker, or framework dependencies will be introduced.

## Target structure

Matches the V1 specification §6 plus `docs/` (errata §12). All SQLite files stay outside `/public`.

## Phased delivery

| Phase | Focus | Gate |
|-------|--------|------|
| **1** | Config, bootstrap, errors, SQLite, migrations, installer, `.gitignore` | Stop for approval |
| **2** | Shared authentication (register/login/logout/profile/roles) | Stop for approval |
| **3** | Front-controller routes, layouts, authorization | Stop for approval |
| **4** | Project launcher (schema already in P1 migrations; factory + UI) | Stop for approval |
| **5** | `logged-in-prototype` template, project DB, CRUD, CSRF | Stop for approval |
| **6** | Events, Founder Dashboard metrics | Stop for approval |
| **7** | Hardening, docs, acceptance verification | Stop for approval |

**Rule:** Do not start the next phase without explicit approval (errata §13).

## Phase 1 deliverables

- Repository scaffolding and `.gitignore` (including SQLite WAL/journal sidecars)
- Example + local config loading with session-path derivation
- Core bootstrap, error handling, logging
- `Database` PDO wrapper (PRAGMA setup + WAL fallback)
- `MigrationRunner` (forward-only, transactional where possible)
- Platform migrations: `schema_migrations`, `users`, `projects`, `project_events`
- Browser installer: environment checks, DB create, migrations, first admin, `data/installed.lock`
- Minimal front controller able to serve `/` and `/install`
- `docs/PHASE_STATUS.md` updates after verification

## Out of scope until later phases

Authentication UX beyond installer admin creation, Founder Dashboard, project factory, generated templates, event helpers, full README polish, production Hostinger smoke test of the complete loop.

## Risks

| Risk | Mitigation |
|------|------------|
| WAL mode unavailable on some hosts | Log and continue with default journal mode |
| Subdirectory installs | Derive session cookie path from `base_url` |
| Partial install failures | No `installed.lock` until admin + migrations succeed; clean error reporting |
| Accidental commit of runtime DBs | Strict `.gitignore`; verify staging before commits |
