# SaaS Lab

**Problem:** Every new PHP SaaS experiment rebuilds the same authentication, database, and project scaffolding.

SaaS Lab is a lightweight PHP and SQLite workspace for rapidly building, testing, and validating SaaS ideas. It eliminates repetitive setup with shared authentication, project templates, automatic migrations, and isolated project databases, so new prototypes can launch in minutes.

**V1 scope:** Shared accounts, one project template launcher, isolated project databases, standard activity events, and a minimal Founder Dashboard. Broader validation tooling is out of scope.

![Screenshot placeholder](docs/screenshot-placeholder.svg)

## Requirements

- PHP 8.2+
- PDO SQLite
- Apache with `mod_rewrite` (Hostinger shared hosting)
- Writable directories: `data/`, `storage/logs/`, `storage/uploads/`, `projects/`

No Node.js, Docker, Composer packages, or build step required.

## Installation (Hostinger)

1. Upload or sync this repository.
2. Point the domain document root to `public/`.
3. Copy `config.local.example.php` to `config.local.php`.
4. Set `base_url` to your production URL (subdirectory installs are supported).
5. Ensure `data/`, `storage/`, and `projects/` are writable.
6. Visit `/install` and create the first administrator.

## Core loop

Install from migrations → create a shared user → create a project → open it with the shared account → complete the core action → see the Founder Dashboard update.

## Status

Phases 1–2 complete (foundation + shared authentication). See `docs/V1_IMPLEMENTATION_PLAN.md` and `docs/PHASE_STATUS.md`.

V1 focuses on project launching and basic usage measurement; broader validation and portfolio-management tools may come later.
