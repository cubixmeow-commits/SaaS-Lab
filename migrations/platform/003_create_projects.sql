CREATE TABLE projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    access_mode TEXT NOT NULL DEFAULT 'lab'
        CHECK (access_mode IN ('lab', 'private', 'public', 'archived')),
    template_key TEXT NOT NULL,
    current_version TEXT NOT NULL DEFAULT '0.1.0',
    core_action_name TEXT NOT NULL DEFAULT 'core_action_completed',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
