-- #!mysql
-- #{ capital
-- #    { init
-- #        { mysql
-- #            { tables
CREATE TABLE IF NOT EXISTS capital_acc (
    id CHAR(36) PRIMARY KEY,
    value BIGINT NOT NULL,
    touch TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY (touch)
);
-- #&
CREATE TABLE IF NOT EXISTS capital_acc_label (
    id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    value VARCHAR(255) NOT NULL,

    PRIMARY KEY (id, name),
    KEY (name, value),
    FOREIGN KEY (id) REFERENCES capital_acc(id) ON DELETE CASCADE
);
-- #&
CREATE TABLE IF NOT EXISTS capital_tran (
    id CHAR(36) PRIMARY KEY,
    src CHAR(36) NULL,
    dest CHAR(36) NULL,
    value BIGINT NOT NULL,
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY (created),
    FOREIGN KEY fk_src (src) REFERENCES capital_acc(id) ON DELETE SET NULL,
    FOREIGN KEY fk_dest (dest) REFERENCES capital_acc(id) ON DELETE SET NULL
);
-- #&
CREATE TABLE IF NOT EXISTS capital_tran_label (
    id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    value VARCHAR(255) NOT NULL,

    PRIMARY KEY (id, name),
    KEY (name, value),
    FOREIGN KEY (id) REFERENCES capital_tran(id) ON DELETE CASCADE
);
-- #&
CREATE TABLE IF NOT EXISTS capital_analytics_top_cache (
    query CHAR(32) NOT NULL,
    group_value VARCHAR(255) NOT NULL,
    metric DOUBLE NULL,
    last_updated TIMESTAMP NOT NULL,
    last_updated_with CHAR(32) NOT NULL,
    PRIMARY KEY (query, group_value),
    KEY (query, metric), -- Used for top queries.
    KEY (query, last_updated), -- Used for selecting rows to recompute.
    KEY (last_updated_with) -- Used in the actual recomputation query.
);
-- #            }
-- #            { procedures
-- #                { tran_create
CREATE PROCEDURE capital_tran_create (
    IN param_id CHAR(36),
    IN param_src CHAR(36),
    IN param_dest CHAR(36),
    IN param_delta BIGINT,
    IN param_src_min BIGINT,
    IN param_dest_max BIGINT,
    OUT param_status INT
) BEGIN
    DECLARE var_src_value BIGINT;
    DECLARE var_dest_value BIGINT;

    SELECT value INTO var_src_value FROM capital_acc WHERE id = param_src FOR UPDATE;
    SELECT value INTO var_dest_value FROM capital_acc WHERE id = param_dest FOR UPDATE;

    SET var_src_value = var_src_value - param_delta;
    SET var_dest_value = var_dest_value + param_delta;

    IF var_src_value < param_src_min THEN
        SET param_status = 1;
    ELSEIF var_dest_value > param_dest_max THEN
        SET param_status = 2;
    ELSE
        SET param_status = 0;

        UPDATE capital_acc SET value = var_src_value WHERE id = param_src;
        UPDATE capital_acc SET value = var_dest_value WHERE id = param_dest;

        INSERT INTO capital_tran (id, src, dest, value)
        VALUES (param_id, param_src, param_dest, param_delta);
    END IF;
END
-- #                }
-- #                { tran_create_2
CREATE PROCEDURE capital_tran_create_2 (
    IN param1_id CHAR(36),
    IN param1_src CHAR(36),
    IN param1_dest CHAR(36),
    IN param1_delta BIGINT,
    IN param1_src_min BIGINT,
    IN param1_dest_max BIGINT,
    IN param2_id CHAR(36),
    IN param2_src CHAR(36),
    IN param2_dest CHAR(36),
    IN param2_delta BIGINT,
    IN param2_src_min BIGINT,
    IN param2_dest_max BIGINT,
    OUT param_status INT
) BEGIN
    START TRANSACTION;

    CALL capital_tran_create(
        param1_id, param1_src, param1_dest, param1_delta,
        param1_src_min, param1_dest_max, param_status
    );

    IF param_status = 0 THEN
        CALL capital_tran_create(
            param2_id, param2_src, param2_dest, param2_delta,
            param2_src_min, param2_dest_max, param_status
        );
    END IF;

    IF param_status != 0 THEN
        ROLLBACK;
    ELSE
        COMMIT;
    END IF;
END
-- #                }
-- #            }
-- #        }
-- #    }
-- #}
