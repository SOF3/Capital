-- #!mysql
-- #{ capital.account
-- #    { create
-- #        :id string
-- #        :value int
INSERT INTO capital_acc (id, value) VALUES (:id, :value);
-- #    }
-- #    { touch
-- #        :id string
UPDATE capital_acc SET touch = CURRENT_TIMESTAMP WHERE id = :id;
-- #    }
-- #    { fetch
-- #        :id string
SELECT value FROM capital_acc WHERE id = :id;
-- #    }
-- #    { fetch_list
-- #        :ids list:string
SELECT id, value FROM capital_acc WHERE id IN :ids;
-- #    }
-- #    { label
-- #        { add
-- #            :id string
-- #            :name string
-- #            :value string
INSERT INTO capital_acc_label (id, name, value) VALUES (:id, :name, :value);
-- #        }
-- #        { update
-- #            :id string
-- #            :name string
-- #            :value string
UPDATE capital_acc_label SET value = :value WHERE id = :id AND name = :name;
-- #        }
-- #        { add_or_update
-- #            :id string
-- #            :name string
-- #            :value string
INSERT INTO capital_acc_label (id, name, value) VALUES (:id, :name, :value)
ON DUPLICATE KEY UPDATE value = :value;
-- #        }
-- #        { fetch
-- #            :id string
-- #            :name string
SELECT value FROM capital_acc_label WHERE id = :id AND name = :name;
-- #        }
-- #        { fetch_all
-- #            :id string
SELECT name, value FROM capital_acc_label WHERE id = :id;
-- #        }
-- #        { fetch_all_multi
-- #            :ids list:string
SELECT id, name, value FROM capital_acc_label WHERE id IN :ids;
-- #        }
-- #    }
-- #}
