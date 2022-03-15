-- #!sqlite
-- #{ capitrade.init
CREATE TABLE IF NOT EXISTS capitrade_shop (
    shop_id INTEGER PRIMARY KEY AUTOINCREMENT,
    price INTEGER NOT NULL
);
-- #&
CREATE TABLE IF NOT EXISTS capitrade_shop_product_item (
    id INTEGER PRIMARY KEY,
    shop_id INTEGER,
    item TEXT,
    cnt INTEGER,
    from_customer INTEGER,
    FOREIGN KEY(shop_id) REFERENCES capitrade_shop(shop_id)
);
-- #&
CREATE TABLE IF NOT EXISTS capitrade_shop_access (
    access_id INTEGER PRIMARY KEY,
    shop_id INTEGER,
    FOREIGN KEY(shop_id) REFERENCES capitrade_shop(shop_id)
);
-- #&
CREATE TABLE IF NOT EXISTS capitrade_shop_access_block (
    access_id INTEGER PRIMARY KEY,
    shop_id INTEGER,
    x INTEGER,
    y INTEGER,
    z INTEGER,
    world TEXT,
    server_id TEXT,
    delete_permission TEXT,
    FOREIGN KEY(access_id) REFERENCES capitrade_shop(access_id),
    FOREIGN KEY(shop_id) REFERENCES capitrade_shop(shop_id)
);
-- #}
