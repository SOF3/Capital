-- This file contains some useful SQL queries for server maintenance.

-- List all accounts with their labels on the same row.
SELECT id, acc.value, touch,
    GROUP_CONCAT(
        CONCAT(
            SUBSTRING_INDEX(acc_label.name,'/',-1),
            '=',
            acc_label.value
        ) SEPARATOR ', '
    ) labels
FROM acc
LEFT JOIN acc_label USING (id)
GROUP BY id;
