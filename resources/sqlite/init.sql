-- #!sqlite
-- #{ capital
-- #    { init
-- #        { sqlite
CREATE TABLE IF NOT EXISTS acc (
    id TEXT PRIMARY KEY,
    value INTEGER NOT NULL,
    touch TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
-- #&
CREATE INDEX IF NOT EXISTS acc_touch ON acc(touch);
-- #&
CREATE TABLE IF NOT EXISTS acc_label (
    id TEXT NOT NULL,
    name TEXT NOT NULL,
    value TEXT NOT NULL,

    PRIMARY KEY (id, name),
    FOREIGN KEY (id) REFERENCES acc(id) ON DELETE CASCADE
);
-- #        &
CREATE INDEX IF NOT EXISTS acc_label_kv ON acc_label(name, value);
-- #&
CREATE TABLE IF NOT EXISTS tran (
    id TEXT PRIMARY KEY,
    src TEXT NULL,
    dest TEXT NULL,
    value INTEGER NOT NULL,
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (src) REFERENCES acc(id) ON DELETE SET NULL,
    FOREIGN KEY (dest) REFERENCES acc(id) ON DELETE SET NULL
);
-- #        &
CREATE INDEX IF NOT EXISTS tran_created ON tran(created);
-- #&
CREATE TABLE IF NOT EXISTS tran_label (
    id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    value VARCHAR(255) NOT NULL,

    PRIMARY KEY (id, name),
    FOREIGN KEY (id) REFERENCES tran(id) ON DELETE CASCADE
);
-- #        &
CREATE INDEX IF NOT EXISTS tran_label_kv ON tran_label(name, value);
-- #        &
CREATE TABLE IF NOT EXISTS analytics_top_cache (
    query CHAR(32) NOT NULL,
    group_value VARCHAR(255) NOT NULL,
    metric DOUBLE NOT NULL,
    last_updated TIMESTAMP NOT NULL,
    last_updated_with CHAR(32),
    PRIMARY KEY (query, group_value)
);
-- #        &
CREATE INDEX IF NOT EXISTS analytics_top_cache_top_query (query, metric);
-- #        &
CREATE INDEX IF NOT EXISTS analytics_top_cache_selection (query, last_updated); -- Used for selecting rows to recompute.
-- #        &
CREATE INDEX IF NOT EXISTS analytics_top_cache_selection (query, last_updated_with); -- Used in the actual recomputation query.
-- #        }
-- #    }
-- #}
