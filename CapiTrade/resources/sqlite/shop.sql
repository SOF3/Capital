-- #!sqlite
-- #{ capitrade
-- #    { init
CREATE TABLE IF NOT EXISTS capitrade_shop (
    shop_id TEXT PRIMARY KEY,
    price INTEGER NOT NULL
);
-- #&
CREATE TABLE IF NOT EXISTS capitrade_shop_schema (
    shop_id TEXT PRIMARY KEY,
    config TEXT NOT NULL,

    PRIMARY KEY(shop_id),
    FOREIGN KEY (shop_id) REFERENCES capitrade_shop(shop_id) ON DELETE CASCADE
);
-- #&
CREATE TABLE IF NOT EXISTS capitrade_shop_acc_sel (
    shop_id TEXT NOT NULL,
    name TEXT NOT NULL,
    value TEXT NOT NULL,

    PRIMARY KEY(shop_id, name),
    FOREIGN KEY (shop_id) REFERENCES capitrade_shop(shop_id) ON DELETE CASCADE
);
-- #&
CREATE TABLE IF NOT EXISTS capitrade_shop_label (
    shop_id TEXT NOT NULL,
    name TEXT NOT NULL,
    value TEXT NOT NULL,

    PRIMARY KEY (shop_id, name),
    FOREIGN KEY (shop_id) REFERENCES capitrade_shop(shop_id) ON DELETE CASCADE
);
-- #&
CREATE INDEX IF NOT EXISTS capitrade_shop_label_kv ON capitrade_shop_label(name, value);
-- #&
CREATE TABLE IF NOT EXISTS capitrade_shop_access (
    access_id INTEGER PRIMARY KEY,
    shop_id TEXT,
    FOREIGN KEY(shop_id) REFERENCES capitrade_shop(shop_id) ON DELETE CASCADE
);
-- #&
CREATE TABLE IF NOT EXISTS capitrade_shop_access_block (
    access_id INTEGER PRIMARY KEY,
    shop_id TEXT,
    x INTEGER,
    y INTEGER,
    z INTEGER,
    world TEXT,
    server_id TEXT,
    delete_permission TEXT,
    FOREIGN KEY(access_id) REFERENCES capitrade_shop(access_id) ON DELETE CASCADE
);
-- #    }
-- #    { get_price
-- #        :shop_id string
SELECT price FROM capitrade_shop WHERE shop_id = :shop_id;
-- #    }
-- #    { get_shop_account_selector
-- #        :shop_id string
SELECT name, value FROM capitrade_shop_acc_sel WHERE shop_id = :shop_id;
-- #    }
-- #    { get_shop_schema_config
-- #        :shop_id string
SELECT config FROM capitrade_shop_schema WHERE shop_id = :shop_id;
-- #    }
-- #}
