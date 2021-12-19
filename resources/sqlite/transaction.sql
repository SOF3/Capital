-- #!sqlite
-- #{ capital
-- #    { transaction
-- #        { insert
-- #            :id string
-- #            :src string
-- #            :dest string
-- #            :delta int
INSERT INTO tran (id, src, dest, value) VALUES (:id, :src, :dest, :delta);
-- #        }
-- #        { label
-- #            { add
-- #                :id string
-- #                :name string
-- #                :value string
INSERT INTO acc_label (id, name, value) VALUES (:id, :name, :value);
-- #            }
-- #            { update
-- #                :id string
-- #                :name string
-- #                :value string
UPDATE acc_label SET value = :value WHERE id = :id AND name = :name;
-- #            }
-- #            { add_or_update
-- #                :id string
-- #                :name string
-- #                :value string
INSERT OR REPLACE INTO acc_label (id, name, value) VALUES (:id, :name, :value);
-- #            }
-- #            { fetch
-- #                :id string
-- #                :name string
SELECT value FROM acc_label WHERE id = :id AND name = :name;
-- #            }
-- #            { fetch_all
-- #                :id string
SELECT name, value FROM acc_label WHERE id = :id;
-- #            }
-- #            { fetch_all_multi
-- #                :ids list:string
SELECT id, name, value FROM acc_label WHERE id IN :ids;
-- #            }
-- #        }
-- #    }
-- #}

