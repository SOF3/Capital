-- #!sqlite
-- #{ capital
-- #    { account
-- #        { create
-- #            :id string
-- #            :value int
INSERT INTO acc (id, value) VALUES (:id, :value);
-- #        }
-- #        { sqlite.unsafe
-- #            { delta
-- #            :id string
-- #            :delta int
UPDATE acc SET value = value + :delta WHERE id = :id;
-- #            }
-- #        }
-- #        { fetch
-- #            :id string
SELECT value FROM acc WHERE id = :id;
-- #        }
-- #        { fetch_list
-- #            :ids list:string
SELECT id, value FROM acc WHERE id IN :ids;
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
