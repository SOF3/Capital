-- #!sqlite
-- #{ capital
-- #    { init
-- #        { sqlite
CREATE TABLE IF NOT EXISTS account (
    id TEXT PRIMARY KEY,
    value INTEGER NOT NULL,
    touch TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
);
-- #&
CREATE INDEX IF NOT EXISTS account_touch ON account(touch);
-- #&
CREATE TABLE IF NOT EXISTS account_label (
    id TEXT NOT NULL,
    key TEXT NOT NULL,
    value TEXT NOT NULL,

    PRIMARY KEY (id, key),
    FOREIGN KEY (id) REFERENCES account(id) ON DELETE CASCADE
);
-- #        &
CREATE INDEX IF NOT EXISTS account_label_kv ON account_label(key, value);
-- #&
CREATE TABLE IF NOT EXISTS transaction (
    id TEXT PRIMARY KEY,
    src TEXT NULL,
    dest TEXT NULL,
    value INTEGER NOT NULL,
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY fk_src (src) REFERENCES account(id) ON DELETE SET NULL,
    FOREIGN KEY fk_dest (dest) REFERENCES account(id) ON DELETE SET NULL,
);
-- #        &
CREATE INDEX IF NOT EXISTS transaction_created ON transaction(created);
-- #&
CREATE TABLE IF NOT EXISTS transaction_label (
    id CHAR(36) NOT NULL,
    key VARCHAR(255) NOT NULL,
    value VARCHAR(255) NOT NULL,

    PRIMARY KEY (id, key),
    FOREIGN KEY (id) REFERENCES transaction(id) ON DELETE CASCADE
);
-- #        &
CREATE INDEX IF NOT EXISTS transaction_label_kv ON transaction_label(key, value);
-- #        }
-- #    }
-- #}
