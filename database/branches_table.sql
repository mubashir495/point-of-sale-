-- database/branches_table.sql
-- Safe to run multiple times (idempotent).
-- Creates the `branches` table if it doesn't already exist and makes
-- sure a "Main Branch" exists so old/unassigned data has somewhere to live.

CREATE TABLE IF NOT EXISTS branches (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(150) NOT NULL,
    manager_id  INT NULL,
    address     VARCHAR(255) NULL,
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_branch_name (branch_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed a "Main Branch" so existing/unassigned employees & branch_users
-- have a home instead of disappearing from branch-scoped views.
INSERT INTO branches (branch_name, address, status)
SELECT 'Main Branch', NULL, 'active'
WHERE NOT EXISTS (SELECT 1 FROM branches WHERE branch_name = 'Main Branch');

-- Migrate any employees/branch_users that don't have a branch yet into
-- Main Branch (only touches NULL rows - never overwrites an existing
-- assignment).
SET @main_branch_id = (SELECT id FROM branches WHERE branch_name = 'Main Branch' LIMIT 1);

UPDATE employees SET branch_id = @main_branch_id WHERE branch_id IS NULL;
UPDATE branch_users SET branch_id = @main_branch_id WHERE branch_id IS NULL;
