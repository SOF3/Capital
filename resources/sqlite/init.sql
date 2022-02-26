-- #!sqlite
-- #{ capital
-- #    { init
-- #        { sqlite
CREATE TABLE IF NOT EXISTS capital_acc (
    id TEXT PRIMARY KEY,
    value INTEGER NOT NULL,
    touch TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
-- #&
CREATE INDEX IF NOT EXISTS capital_acc_touch ON capital_acc(touch);
-- #&
CREATE TABLE IF NOT EXISTS capital_acc_label (
    id TEXT NOT NULL,
    name TEXT NOT NULL,
    value TEXT NOT NULL,

    PRIMARY KEY (id, name),
    FOREIGN KEY (id) REFERENCES capital_acc(id) ON DELETE CASCADE
);
-- #        &
CREATE INDEX IF NOT EXISTS capital_acc_label_kv ON capital_acc_label(name, value);
-- #&
CREATE TABLE IF NOT EXISTS capital_tran (
    id TEXT PRIMARY KEY,
    src TEXT NULL,
    dest TEXT NULL,
    value INTEGER NOT NULL,
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (src) REFERENCES capital_acc(id) ON DELETE SET NULL,
    FOREIGN KEY (dest) REFERENCES capital_acc(id) ON DELETE SET NULL
);
-- #        &
CREATE INDEX IF NOT EXISTS capital_tran_created ON capital_tran(created);
-- #&
CREATE TABLE IF NOT EXISTS capital_tran_label (
    id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    value VARCHAR(255) NOT NULL,

    PRIMARY KEY (id, name),
    FOREIGN KEY (id) REFERENCES capital_tran(id) ON DELETE CASCADE
);
-- #        &
CREATE INDEX IF NOT EXISTS capital_tran_label_kv ON capital_tran_label(name, value);
-- #        &
CREATE TABLE IF NOT EXISTS capital_analytics_top_cache (
    query CHAR(32) NOT NULL,
    group_value VARCHAR(255) NOT NULL,
    metric DOUBLE NULL,
    last_updated TIMESTAMP NOT NULL,
    last_updated_with CHAR(32) NOT NULL,
    PRIMARY KEY (query, group_value)
);
-- #        &
CREATE INDEX IF NOT EXISTS capital_analytics_top_cache_top_query ON capital_analytics_top_cache (query, metric);
-- #        &
CREATE INDEX IF NOT EXISTS capital_analytics_top_cache_collection ON capital_analytics_top_cache (query, last_updated); -- Used for selecting rows to recompute.
-- #        &
CREATE INDEX IF NOT EXISTS capital_analytics_top_cache_updater ON capital_analytics_top_cache (last_updated_with); -- Used in the actual recomputation query.
-- #        }
-- #    }
-- #}
