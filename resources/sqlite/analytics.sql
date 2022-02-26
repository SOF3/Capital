-- #!sqlite
-- #{ capital.analytics
-- #    { count
-- #        :queryHash string
SELECT COUNT(*) cnt FROM capital_analytics_top_cache
WHERE query = :queryHash AND metric IS NOT NULL;
-- #    }
-- #    { collect-updates
-- #        :queryHash string
-- #        :runId string
-- #        :expiry int
-- #        :limit int
UPDATE capital_analytics_top_cache
SET
    last_updated = CURRENT_TIMESTAMP,
    last_updated_with = :runId
WHERE query = :queryHash AND group_value IN (
    SELECT group_value FROM capital_analytics_top_cache
    WHERE query = :queryHash
        AND JULIANDAY(CURRENT_TIMESTAMP)- JULIANDAY(last_updated) > :expiry
    ORDER BY last_updated ASC
    LIMIT :limit
);
-- #    }
-- #}
