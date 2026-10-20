-- database/branches_table_column_fix.sql
-- Run this if you already had a `branches` table from before and got:
--   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'manager_id'
-- Safe to run more than once.

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branches' AND COLUMN_NAME = 'manager_id');
SET @sql := IF(@col = 0, 'ALTER TABLE branches ADD COLUMN manager_id INT NULL AFTER branch_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branches' AND COLUMN_NAME = 'address');
SET @sql := IF(@col = 0, 'ALTER TABLE branches ADD COLUMN address VARCHAR(255) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branches' AND COLUMN_NAME = 'status');
SET @sql := IF(@col = 0, "ALTER TABLE branches ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active'", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branches' AND COLUMN_NAME = 'branch_name');
SET @sql := IF(@col = 0, 'ALTER TABLE branches ADD COLUMN branch_name VARCHAR(150) NOT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Make sure a Main Branch exists so old/unassigned data has a home.
INSERT INTO branches (branch_name, address, status)
SELECT 'Main Branch', NULL, 'active'
WHERE NOT EXISTS (SELECT 1 FROM branches WHERE branch_name = 'Main Branch');
