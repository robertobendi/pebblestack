-- 0006_login_attempts.sql — failed-login tracking for brute-force throttling.
-- Stores a salted hash of the IP, never the raw address. Rows age off after
-- the throttle window; the service prunes opportunistically on insert.

CREATE TABLE IF NOT EXISTS login_attempts (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_hash      TEXT NOT NULL,
    attempted_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_hash, attempted_at);
