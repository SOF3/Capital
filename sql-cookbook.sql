-- This file contains some useful SQL queries for server maintenance.

-- List all accounts with their labels on the same row.
SELECT id, capital_acc.value, touch,
    GROUP_CONCAT(
        CONCAT(
            SUBSTRING_INDEX(capital_acc_label.name,'/',-1),
            '=',
            capital_acc_label.value
        ) SEPARATOR ', '
    ) labels
FROM capital_acc
LEFT JOIN capital_acc_label USING (id)
GROUP BY id;
