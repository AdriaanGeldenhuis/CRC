-- =====================================================================
-- 022_calendar_events_category.sql
-- Adds `category` to calendar_events. The personal-events UI has a
-- category picker but the table never had a column to store it.
--
-- Idempotent / self-healing. Top-level statements only so it runs the same
-- via the mysql CLI and via PDO::exec() in _scripts/migrate.php.
-- =====================================================================

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='calendar_events' AND COLUMN_NAME='category');
SET @s := IF(@c=0, "ALTER TABLE calendar_events ADD COLUMN category VARCHAR(100) DEFAULT 'general'", 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
