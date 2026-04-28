-- 0004_entry_revisions.sql — entry version history

CREATE TABLE IF NOT EXISTS entry_revisions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_id    INTEGER NOT NULL,
    collection  TEXT NOT NULL,
    slug        TEXT NOT NULL,
    status      TEXT NOT NULL,
    data        TEXT NOT NULL,
    publish_at  INTEGER,
    edited_by   INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at  INTEGER NOT NULL,
    FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_entry_revisions_entry_id  ON entry_revisions(entry_id);
CREATE INDEX IF NOT EXISTS idx_entry_revisions_created   ON entry_revisions(created_at);
