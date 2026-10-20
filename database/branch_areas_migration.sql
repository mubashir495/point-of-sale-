-- database/branch_areas_migration.sql
-- Adds per-branch Area separation (same pattern as Employees/Products/
-- Customers/Orders). Safe to run more than once.

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'areas' AND COLUMN_NAME = 'branch_id');
SET @sql := IF(@col = 0, 'ALTER TABLE areas ADD COLUMN branch_id INT NULL AFTER city_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @main_branch_id = (SELECT id FROM branches WHERE branch_name = 'Main Branch' LIMIT 1);
UPDATE areas SET branch_id = @main_branch_id WHERE branch_id IS NULL;
