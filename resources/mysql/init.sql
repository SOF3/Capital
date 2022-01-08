-- #!mysql
-- #{ capital
-- #    { init
-- #        { mysql
-- #            { tables
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
CREATE TABLE IF NOT EXISTS acc_archive (
    archive_batch BIGINT NOT NULL,
    id CHAR(36) NOT NULL,
    value BIGINT NOT NULL,
    touch TIMESTAMP NOT NULL,
    labels TEXT NOT NULL,
    PRIMARY KEY (archive_batch, id)
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
CREATE TABLE IF NOT EXISTS tran_archive (
    archive_batch BIGINT NOT NULL,
    id CHAR(36) NOT NULL,
    src CHAR(36) NULL,
    dest CHAR(36) NULL,
    value BIGINT NOT NULL,
    touch TIMESTAMP NOT NULL,
    labels TEXT NOT NULL,
    PRIMARY KEY (archive_batch, id)
);
-- #            }
-- #            { procedures
-- #                { tran_create
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

        UPDATE acc SET value = var_src_value, touch = CURRENT_TIMESTAMP WHERE id = param_src;
        UPDATE acc SET value = var_dest_value, touch = CURRENT_TIMESTAMP WHERE id = param_dest;

        INSERT INTO tran (id, src, dest, value)
        VALUES (param_id, param_src, param_dest, param_delta);
    END IF;
END
-- #                }
-- #                { tran_create_2
CREATE PROCEDURE tran_create_2 (
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

    CALL tran_create(
        param1_id, param1_src, param1_dest, param1_delta,
        param1_src_min, param1_dest_max, param_status
    );

    IF param_status = 0 THEN
        CALL tran_create(
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
-- #                { archive_acc
CREATE PROCEDURE acc_archive (
    IN param_expiry INT,
    IN param_batch_id BIGINT,
    OUT param_num_rows BIGINT
) BEGIN
    START TRANSACTION;

    INSERT INTO acc_archive (archive_batch, id, value, touch, labels)
    SELECT param_batch_id, id, acc.value, touch,
        GROUP_CONCAT(acc_label.name, '=', acc_label.value SEPARATOR '\0')
    FROM acc LEFT JOIN acc_label USING (id)
    WHERE touch < DATE_SUB(NOW(), INTERVAL param_expiry SECOND);

    DELETE FROM acc_label WHERE id IN (SELECT id FROM acc_archive WHERE archive_batch = param_batch_id);
    DELETE FROM acc WHERE id IN (SELECT id FROM acc_archive WHERE archive_batch = param_batch_id);

    COMMIT;
END
-- #                }
-- #                { archive_tran
CREATE PROCEDURE tran_archive (
    IN param_expiry INT,
    IN param_batch_id BIGINT,
    OUT param_num_rows BIGINT
) BEGIN
    START TRANSACTION;

    INSERT INTO tran_archive (archive_batch, id, value, touch, labels)
    SELECT param_batch_id, id, tran.value, touch,
        GROUP_CONCAT(tran_label.name, '=', tran_label.value SEPARATOR '\0')
    FROM tran LEFT JOIN tran_label USING (id)
    WHERE touch < DATE_SUB(NOW(), INTERVAL param_expiry SECOND);

    DELETE FROM tran_label WHERE id IN (SELECT id FROM tran_archive WHERE archive_batch = param_batch_id);
    DELETE FROM tran WHERE id IN (SELECT id FROM tran_archive WHERE archive_batch = param_batch_id);

    COMMIT;
END
-- #                }
-- #            }
-- #        }
-- #    }
-- #}
