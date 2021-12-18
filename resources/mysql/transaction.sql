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
START TRANSACTION;
-- #&
CALL tran_create(:id, :src, :dest, :delta, :src_min, :dest_max, @var_status);
-- #&
COMMIT;
-- #&
SELECT @var_status AS status;
-- #        }
-- #        { create2
-- #            :id1 string
-- #            :src1 string
-- #            :dest1 string
-- #            :delta1 int
-- #            :src_min1 int
-- #            :dest_max1 int
-- #            :id2 string
-- #            :src2 string
-- #            :dest2 string
-- #            :delta2 int
-- #            :src_min2 int
-- #            :dest_max2 int
CALL tran_create_2(
    :id1, :src1, :dest1, :delta1, :src_min1, :dest_max1,
    :id2, :src2, :dest2, :delta2, :src_min2, :dest_max2,
    @var_status
);
-- #&
SELECT @var_status AS status;
-- #        }
-- #    }
-- #}
