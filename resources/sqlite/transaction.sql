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
-- #    }
-- #}

