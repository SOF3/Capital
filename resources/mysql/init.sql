-- #!mysql
-- #{ capital
-- #    { init
CREATE TABLE IF NOT EXISTS acc (
    id CHAR(36) PRIMARY KEY,
    value BIGINT NOT NULL,
    touch TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY (touch)
);
-- #&
CREATE TABLE IF NOT EXISTS acc_label (
    id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    value VARCHAR(255) NOT NULL,

    PRIMARY KEY (id, name),
    KEY (name, value),
    FOREIGN KEY (id) REFERENCES acc(id) ON DELETE CASCADE
);
-- #&
CREATE TABLE IF NOT EXISTS tran (
    id CHAR(36) PRIMARY KEY,
    src CHAR(36) NULL,
    dest CHAR(36) NULL,
    value BIGINT NOT NULL,
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY (created),
    FOREIGN KEY fk_src (src) REFERENCES acc(id) ON DELETE SET NULL,
    FOREIGN KEY fk_dest (dest) REFERENCES acc(id) ON DELETE SET NULL
);
-- #&
CREATE TABLE IF NOT EXISTS tran_label (
    id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    value VARCHAR(255) NOT NULL,

    PRIMARY KEY (id, name),
    KEY (name, value),
    FOREIGN KEY (id) REFERENCES tran(id) ON DELETE CASCADE
);
-- #&
CREATE PROCEDURE tran_create (
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

    START TRANSACTION;

    SELECT value INTO var_src_value FROM acc WHERE id = param_src FOR UPDATE;
    SELECT value INTO var_dest_value FROM acc WHERE id = param_dest FOR UPDATE;

    SET var_src_value = var_src_value - param_delta;
    SET var_dest_value = var_dest_value + param_delta;

    IF var_src_value < param_src_min THEN
        SET param_status = 1;
    ELSEIF var_dest_value > param_dest_max THEN
        SET param_status = 2;
    ELSE
        SET param_status = 0;

        UPDATE acc SET value = var_src_value WHERE id = param_src;
        UPDATE acc SET value = var_dest_value WHERE id = param_dest;

        INSERT INTO tran (id, src, dest, value) VALUES (param_id, param_src, param_dest, param_delta);
    END IF;

    COMMIT;
END
-- #    }
-- #}
