-- #!mysql
-- #{ capital.analytics
-- #    { fetch-top
-- #        :queryHash string
-- #        :limit int
-- #        :offset int
-- #        :orderSign int
SELECT group_value, metric FROM analytics_top_cache
WHERE query = :queryHash
ORDER BY metric * :orderSign
LIMIT :offset, :limit;
-- #    }
-- #    { collect-updates
-- #        :queryHash string
-- #        :runId string
-- #        :expiry int
-- #        :limit int
UPDATE analytics_top_cache
SET
    last_updated = CURRENT_TIMESTAMP,
    last_updated_with = :runId
WHERE
    query = :queryHash
    AND TIMESTAMPDIFF(SECOND, last_updated, CURRENT_TIMESTAMP) > :expiry
ORDER BY last_updated ASC
LIMIT :limit;
-- #    }
-- #}
