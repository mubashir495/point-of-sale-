-- database/branch_scoping_migration.sql
-- Adds real per-branch data separation for Products, Customers and
-- Orders/Sales. Safe to run more than once.

-- Products: each branch has its own catalog + stock.
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'branch_id');
SET @sql := IF(@col = 0, 'ALTER TABLE products ADD COLUMN branch_id INT NULL AFTER id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Customers: each branch has its own customer list.
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'branch_id');
SET @sql := IF(@col = 0, 'ALTER TABLE customers ADD COLUMN branch_id INT NULL AFTER id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Orders: stamped with the branch of whoever created them, so Sales /
-- Counter Sale / Delivery are all scoped automatically.
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'branch_id');
SET @sql := IF(@col = 0, 'ALTER TABLE orders ADD COLUMN branch_id INT NULL AFTER id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Migrate existing (unassigned) rows into Main Branch so old data
-- doesn't disappear once scoping is enforced.
SET @main_branch_id = (SELECT id FROM branches WHERE branch_name = 'Main Branch' LIMIT 1);

UPDATE products  SET branch_id = @main_branch_id WHERE branch_id IS NULL;
UPDATE customers SET branch_id = @main_branch_id WHERE branch_id IS NULL;
UPDATE orders    SET branch_id = @main_branch_id WHERE branch_id IS NULL;
