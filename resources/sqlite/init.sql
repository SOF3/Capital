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
-- #        }
-- #    }
-- #}
