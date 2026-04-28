-- 0002_media.sql — media library

CREATE TABLE IF NOT EXISTS media (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    path          TEXT NOT NULL UNIQUE,        -- relative to webroot, e.g. /uploads/2026/04/abc.jpg
    original_name TEXT NOT NULL,
    mime_type     TEXT NOT NULL,
    size          INTEGER NOT NULL,
    width         INTEGER,
    height        INTEGER,
    alt           TEXT,
    created_at    INTEGER NOT NULL,
    uploaded_by   INTEGER REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_media_created_at ON media(created_at);
