-- 0003_form_submissions.sql — public form submissions

CREATE TABLE IF NOT EXISTS form_submissions (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    collection   TEXT NOT NULL,
    data         TEXT NOT NULL,
    ip_hash      TEXT,
    user_agent   TEXT,
    submitted_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_form_submissions_collection   ON form_submissions(collection);
CREATE INDEX IF NOT EXISTS idx_form_submissions_submitted_at ON form_submissions(submitted_at);
