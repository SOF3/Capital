-- #!sqlite
-- #{ capital
-- #    { archive
-- #        { account
-- #            { delete
-- #                :expiry int
DELETE FROM acc WHERE JULIANDAY(CURRENT_TIMESTAMP) - JULIANDAY(touch) > :expiry / 86400.0;
-- #            }
-- #        }
-- #        { transaction
-- #            { delete
-- #                :expiry int
DELETE FROM tran WHERE JULIANDAY(CURRENT_TIMESTAMP) - JULIANDAY(touch) > :expiry / 86400.0;
-- #            }
-- #        }
-- #    }
-- #}
