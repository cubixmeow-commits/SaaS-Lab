CREATE TABLE project_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    user_id INTEGER,
    session_id TEXT,
    event_name TEXT NOT NULL,
    event_data TEXT,
    project_version TEXT NOT NULL,
    occurred_at TEXT NOT NULL,
    FOREIGN KEY (project_id)
        REFERENCES projects(id)
        ON DELETE CASCADE,
    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE INDEX idx_project_events_project_time
    ON project_events(project_id, occurred_at);

CREATE INDEX idx_project_events_user_time
    ON project_events(user_id, occurred_at);

CREATE INDEX idx_project_events_name_time
    ON project_events(event_name, occurred_at);
