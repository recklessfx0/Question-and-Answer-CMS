-- Add a unique constraint to prevent duplicate reputation events for the same
-- (user, reason, source) tuple. This is the DB-level enforcement that backs the
-- application-level dedup guard added in GamificationService::award().
-- The IGNORE keyword means concurrent inserts for the same key are silently dropped
-- rather than raising an error, so callers do not need extra error handling.

ALTER TABLE reputation_events
    ADD UNIQUE KEY uq_reputation_events_user_source_reason (user_id, source_type, source_id, reason);
