# SaaS Lab

**Problem:** Every new PHP SaaS experiment rebuilds the same authentication, database, and project scaffolding.

SaaS Lab is a lightweight PHP and SQLite workspace for rapidly building, testing, and validating SaaS ideas. It eliminates repetitive setup with shared authentication, project templates, automatic migrations, and isolated project databases, so new prototypes can launch in minutes.

**V1 scope:** Shared accounts, one project template launcher (`logged-in-prototype`), isolated project databases, standard activity events, and a minimal Founder Dashboard.

![Screenshot placeholder](docs/screenshot-placeholder.svg)

## Requirements

- PHP 8.2+
- PDO SQLite
- Apache with `mod_rewrite`
- Writable directories: `data/`, `storage/logs/`, `storage/uploads/`, `projects/`

No Node.js, Docker, Composer packages, or build step required.

## Installation

1. Deploy the application (see **cPanel Deployment** below for Namecheap, or sync files so the web root serves `public/`).
2. Copy `config.local.example.php` to `config.local.php` in the **application root** (not the public web root).
3. Set `base_url` to your production URL (subdirectory installs are supported; session cookie path is derived automatically).
4. Ensure `data/`, `storage/`, and `projects/` are writable (`chmod 775` if needed).
5. Visit `/install` and create the first administrator.
6. Register a member, sign in as admin, and create your first project from `/founder`.

## cPanel Deployment

Namecheap cPanel Git Version Control deploys through the root `.cpanel.yml` file.

| Item | Value |
|------|--------|
| Destination (document root) | `/home/iainmcok/public_html/` |
| Private application root | `/home/iainmcok/saas-lab/` |
| Web source published | contents of `public/` only |
| Production branch | `main` |
| Build command | none (plain PHP; no Composer/npm) |

**What is copied**

- Into `/home/iainmcok/saas-lab/`: `app/`, `core/`, `migrations/`, `templates/`, `config/config.example.php`, `config.local.example.php`, `SECURITY.md`
- Into `/home/iainmcok/public_html/`: everything under `public/` (`index.php`, `.htaccess`, `assets/`), plus a non-public `.saas-lab-root` marker pointing at the private app root

**Deliberately excluded from deployment**

- `.git`, `.github`, `.cpanel.yml`
- `docs/`, `scripts/`
- `config.local.php` (never overwritten; create once on the server)
- SQLite databases, WAL/journal sidecars, `data/installed.lock`
- logs, uploads, IDE files, credentials, `.env*`

**Persistent directories that must not be overwritten**

- `/home/iainmcok/saas-lab/data/` (platform SQLite, install lock)
- `/home/iainmcok/saas-lab/projects/` (generated projects and their SQLite files)
- `/home/iainmcok/saas-lab/storage/logs/`
- `/home/iainmcok/saas-lab/storage/uploads/`
- `/home/iainmcok/saas-lab/config.local.php`

The deploy script creates those directories when missing and replaces only application code directories.

**cPanel workflow**

1. Push to GitHub `main`.
2. In cPanel → Git Version Control → **Update from Remote**.
3. **Deploy HEAD Commit**.
4. On first deploy only: copy `config.local.example.php` → `config.local.php` under `/home/iainmcok/saas-lab/`, set `base_url` (for example `https://iainreid.dev`), then visit `/install`.

## Core loop

Install from migrations → create a shared user → create a project → open it with the shared account → complete the core action → see the Founder Dashboard update.

## Project development

Generated projects live under `projects/{slug}/` and are opened through `/p/{slug}/{page}`.

### Adding a page

Create `app/pages/my-page.php` and optionally `app/views/my-page.php`:

```php
<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/bootstrap.php';
auth()->requireLogin();
project_view('my-page', [
    'project' => project(),
    'user' => auth()->user(),
]);
```

### Adding a migration

Add an ordered SQL file such as `app/migrations/002_create_challenges.sql`. Pending migrations run automatically during project bootstrap when the latest filename differs from the recorded migration key.

### Helpers

- `auth()` — shared authentication
- `project_db()` — parameterized project queries (`fetchAll`, `fetchOne`, `run`)
- `project()` / `current_project_slug()` — current project context
- `csrf_field()` / automatic POST CSRF verification
- `lab_event('event_name', [...])` — best-effort activity logging
- `e()` — HTML escaping

### Events

- `project_opened` — once per authenticated visit token per project
- Configured core action (template default: `item_created`) — counted on the Founder Dashboard

## Security

- Runtime files are gitignored (`*.sqlite`, WAL/journal sidecars, logs, uploads, `config.local.php`, `installed.lock`)
- Database files remain outside `/public`
- HTTPS is expected in production
- Passwords are hashed; CSRF protects state-changing requests; project slugs/pages are validated before filesystem resolution

## Local verification

```bash
php scripts/verify_phase1.php
php scripts/verify_phase2.php
php scripts/verify_acceptance.php
```

## Status

V1 Phases 1–7 are implemented. See `docs/V1_IMPLEMENTATION_PLAN.md` and `docs/PHASE_STATUS.md`.

V1 focuses on project launching and basic usage measurement; broader validation and portfolio-management tools may come later.
