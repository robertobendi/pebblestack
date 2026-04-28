-- 0005_pageviews.sql — privacy-friendly server-side page view counts

-- Day-bucketed counts per path. No IPs, no fingerprints, no cookies.
-- One row per (path, day_utc) pair; UPSERT increments the count.
CREATE TABLE IF NOT EXISTS pageviews (
    path     TEXT NOT NULL,
    day_utc  INTEGER NOT NULL,    -- midnight UTC unix timestamp for the day
    count    INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (path, day_utc)
);

CREATE INDEX IF NOT EXISTS idx_pageviews_day ON pageviews(day_utc);
