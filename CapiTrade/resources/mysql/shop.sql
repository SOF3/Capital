-- #!mysql
-- #{ capitrade
-- #    { init
CREATE TABLE IF NOT EXISTS capitrade_shop (
    shop_id CHAR(36) PRIMARY KEY,
    price BIGINT NOT NULL
);
-- #&
CREATE TABLE IF NOT EXISTS capitrade_shop_schema (
    shop_id CHAR(36) PRIMARY KEY,
    config TEXT NOT NULL,

    PRIMARY KEY(shop_id),
    FOREIGN KEY (shop_id) REFERENCES capitrade_shop(shop_id) ON DELETE CASCADE
);
-- #&
CREATE TABLE IF NOT EXISTS capitrade_shop_acc_sel (
    shop_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    value TEXT NOT NULL,

    PRIMARY KEY(shop_id, name),
    FOREIGN KEY (shop_id) REFERENCES capitrade_shop(shop_id) ON DELETE CASCADE
);
-- #&
CREATE TABLE IF NOT EXISTS capitrade_shop_label (
    shop_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    value TEXT NOT NULL,

    PRIMARY KEY (shop_id, name),
    KEY (name, value),
    FOREIGN KEY (shop_id) REFERENCES capitrade_shop(shop_id) ON DELETE CASCADE
);
-- #&
CREATE TABLE IF NOT EXISTS capitrade_shop_access (
    id INT PRIMARY KEY,
    shop_id INT,
    FOREIGN KEY(shop_id) REFERENCES capitrade_shop(shop_id) ON DELETE CASCADE
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
