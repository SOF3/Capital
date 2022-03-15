-- #!mysql
-- #{ capitrade.init
CREATE TABLE IF NOT EXISTS capitrade_shop (
    id INT PRIMARY KEY AUTO_INCREMENT,
    price BIGINT NOT NULL
);
-- #&
CREATE TABLE IF NOT EXISTS capitrade_shop_product (
    id INT PRIMARY KEY,
    shop_id INT,
    item TEXT,
    cnt INT,
    from_customer BOOL,
    FOREIGN KEY(shop_id) REFERENCES capitrade_shop(shop_id)
);
-- #&
CREATE TABLE IF NOT EXISTS capitrade_shop_access (
    id INT PRIMARY KEY,
    shop_id INT,
    FOREIGN KEY(shop_id) REFERENCES capitrade_shop(shop_id)
);
-- #&
CREATE TABLE IF NOT EXISTS capitrade_shop_access_block (
    access_id INT PRIMARY KEY,
    shop_id INT,
    x INT,
    y INT,
    z INT,
    world VARCHAR(255),
    server_id VARCHAR(255),
    delete_permission VARCHAR(255),
    FOREIGN KEY(access_id) REFERENCES capitrade_shop(access_id),
    FOREIGN KEY(shop_id) REFERENCES capitrade_shop(shop_id)
);
-- #}
