-- #!mysql
-- #{ capital.transaction
-- #    { create
-- #        :id string
-- #        :src string
-- #        :dest string
-- #        :delta int
-- #        :src_min int
-- #        :dest_max int
START TRANSACTION;
-- #&
CALL capital_tran_create(:id, :src, :dest, :delta, :src_min, :dest_max, @var_status);
-- #&
COMMIT;
-- #&
SELECT @var_status AS status;
-- #    }
-- #    { create2
-- #        :id1 string
-- #        :src1 string
-- #        :dest1 string
-- #        :delta1 int
-- #        :src_min1 int
-- #        :dest_max1 int
-- #        :id2 string
-- #        :src2 string
-- #        :dest2 string
-- #        :delta2 int
-- #        :src_min2 int
-- #        :dest_max2 int
CALL capital_tran_create_2(
    :id1, :src1, :dest1, :delta1, :src_min1, :dest_max1,
    :id2, :src2, :dest2, :delta2, :src_min2, :dest_max2,
    @var_status
);
-- #&
SELECT @var_status AS status;
-- #    }
-- #    { label
-- #        { add
-- #            :id string
-- #            :name string
-- #            :value string
INSERT INTO capital_tran_label (id, name, value) VALUES (:id, :name, :value);
-- #        }
-- #        { update
-- #            :id string
-- #            :name string
-- #            :value string
UPDATE capital_tran_label SET value = :value WHERE id = :id AND name = :name;
-- #        }
-- #        { add_or_update
-- #            :id string
-- #            :name string
-- #            :value string
INSERT INTO capital_tran_label (id, name, value) VALUES (:id, :name, :value)
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
