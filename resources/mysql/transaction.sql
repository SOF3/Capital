-- #!mysql
-- #{ capital
-- #    { transaction
-- #        { create
-- #            :id string
-- #            :src string
-- #            :dest string
-- #            :delta int
-- #            :src_min int
-- #            :dest_max int
CALL tran_create(:id, :src, :dest, :delta, :src_min, :dest_max, @var_status);
-- #&
SELECT @var_status AS status;
-- #        }
-- #    }
-- #}
