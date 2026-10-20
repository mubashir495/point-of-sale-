-- ============================================================
-- RBAC (Role-Based Access Control) Schema
-- Inventory System - inventory-system
--
-- Run this file once against your existing `inventory_db` database.
-- It is SAFE to re-run: every statement uses CREATE TABLE IF NOT EXISTS
-- or checks for the column before altering.
--
-- Tables created : roles, permissions, role_permissions
-- Table altered  : branch_users (adds role_id, status, profile_image,
--                  full_name, email, phone)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. roles
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `is_system`   TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = built-in default role, cannot be deleted',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2. permissions  (fixed catalogue: 16 modules x 6 actions)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissions` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_key`   VARCHAR(50)  NOT NULL,
  `module_label` VARCHAR(100) NOT NULL,
  `action`       ENUM('view','create','edit','delete','print','export') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_module_action` (`module_key`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. role_permissions  (pivot: which role has which permission)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_permission` (`role_id`, `permission_id`),
  CONSTRAINT `fk_rp_role`
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permission`
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4. Alter existing `branch_users` (the system's real "users" table)
--    Columns are added only if missing, so this is safe to re-run.
-- ------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branch_users' AND COLUMN_NAME = 'role_id');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `branch_users` ADD COLUMN `role_id` INT UNSIGNED NULL AFTER `user_type`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branch_users' AND COLUMN_NAME = 'status');
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE `branch_users` ADD COLUMN `status` ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER `role_id`",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branch_users' AND COLUMN_NAME = 'profile_image');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `branch_users` ADD COLUMN `profile_image` VARCHAR(255) NULL AFTER `status`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branch_users' AND COLUMN_NAME = 'full_name');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `branch_users` ADD COLUMN `full_name` VARCHAR(150) NULL AFTER `username`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branch_users' AND COLUMN_NAME = 'email');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `branch_users` ADD COLUMN `email` VARCHAR(150) NULL AFTER `full_name`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branch_users' AND COLUMN_NAME = 'phone');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `branch_users` ADD COLUMN `phone` VARCHAR(30) NULL AFTER `email`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Foreign key branch_users.role_id -> roles.id (added separately so the
-- above ADD COLUMN calls always succeed even on older MySQL/MariaDB
-- versions that reject conditional FK creation inside PREPARE).
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branch_users' AND CONSTRAINT_NAME = 'fk_branch_users_role');
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE `branch_users` ADD CONSTRAINT `fk_branch_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- 5. Seed default roles
-- ------------------------------------------------------------
INSERT INTO `roles` (`name`, `slug`, `description`, `is_system`) VALUES
('Super Admin',   'super-admin',  'Full unrestricted access to every module and setting.', 1),
('Admin',         'admin',        'Full access to all modules. Can manage roles, users and permissions.', 1),
('Manager',       'manager',      'Operational access to inventory, sales, purchase and reports.', 1),
('Counter Boy',   'counter-boy',  'Front counter sales access only.', 1),
('Sales Person',  'sales-person', 'Field/sales access.', 1),
('Store Keeper',  'store-keeper', 'Warehouse and stock management access.', 1),
('Accountant',    'accountant',   'Financial ledger, expenses and reports access.', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ------------------------------------------------------------
-- 6. Seed permission catalogue (16 modules x 6 actions = 96 rows)
-- ------------------------------------------------------------
INSERT INTO `permissions` (`module_key`, `module_label`, `action`) VALUES
('dashboard', 'Dashboard', 'view'),
('dashboard', 'Dashboard', 'create'),
('dashboard', 'Dashboard', 'edit'),
('dashboard', 'Dashboard', 'delete'),
('dashboard', 'Dashboard', 'print'),
('dashboard', 'Dashboard', 'export'),
('products', 'Products', 'view'),
('products', 'Products', 'create'),
('products', 'Products', 'edit'),
('products', 'Products', 'delete'),
('products', 'Products', 'print'),
('products', 'Products', 'export'),
('categories', 'Categories', 'view'),
('categories', 'Categories', 'create'),
('categories', 'Categories', 'edit'),
('categories', 'Categories', 'delete'),
('categories', 'Categories', 'print'),
('categories', 'Categories', 'export'),
('raw_materials', 'Raw Materials', 'view'),
('raw_materials', 'Raw Materials', 'create'),
('raw_materials', 'Raw Materials', 'edit'),
('raw_materials', 'Raw Materials', 'delete'),
('raw_materials', 'Raw Materials', 'print'),
('raw_materials', 'Raw Materials', 'export'),
('suppliers', 'Suppliers', 'view'),
('suppliers', 'Suppliers', 'create'),
('suppliers', 'Suppliers', 'edit'),
('suppliers', 'Suppliers', 'delete'),
('suppliers', 'Suppliers', 'print'),
('suppliers', 'Suppliers', 'export'),
('customers', 'Customers', 'view'),
('customers', 'Customers', 'create'),
('customers', 'Customers', 'edit'),
('customers', 'Customers', 'delete'),
('customers', 'Customers', 'print'),
('customers', 'Customers', 'export'),
('purchase', 'Purchase', 'view'),
('purchase', 'Purchase', 'create'),
('purchase', 'Purchase', 'edit'),
('purchase', 'Purchase', 'delete'),
('purchase', 'Purchase', 'print'),
('purchase', 'Purchase', 'export'),
('sales', 'Sales', 'view'),
('sales', 'Sales', 'create'),
('sales', 'Sales', 'edit'),
('sales', 'Sales', 'delete'),
('sales', 'Sales', 'print'),
('sales', 'Sales', 'export'),
('stock', 'Stock', 'view'),
('stock', 'Stock', 'create'),
('stock', 'Stock', 'edit'),
('stock', 'Stock', 'delete'),
('stock', 'Stock', 'print'),
('stock', 'Stock', 'export'),
('reports', 'Reports', 'view'),
('reports', 'Reports', 'create'),
('reports', 'Reports', 'edit'),
('reports', 'Reports', 'delete'),
('reports', 'Reports', 'print'),
('reports', 'Reports', 'export'),
('ledger', 'Ledger', 'view'),
('ledger', 'Ledger', 'create'),
('ledger', 'Ledger', 'edit'),
('ledger', 'Ledger', 'delete'),
('ledger', 'Ledger', 'print'),
('ledger', 'Ledger', 'export'),
('expenses', 'Expenses', 'view'),
('expenses', 'Expenses', 'create'),
('expenses', 'Expenses', 'edit'),
('expenses', 'Expenses', 'delete'),
('expenses', 'Expenses', 'print'),
('expenses', 'Expenses', 'export'),
('attendance', 'Attendance', 'view'),
('attendance', 'Attendance', 'create'),
('attendance', 'Attendance', 'edit'),
('attendance', 'Attendance', 'delete'),
('attendance', 'Attendance', 'print'),
('attendance', 'Attendance', 'export'),
('employees', 'Employees', 'view'),
('employees', 'Employees', 'create'),
('employees', 'Employees', 'edit'),
('employees', 'Employees', 'delete'),
('employees', 'Employees', 'print'),
('employees', 'Employees', 'export'),
('users', 'Users', 'view'),
('users', 'Users', 'create'),
('users', 'Users', 'edit'),
('users', 'Users', 'delete'),
('users', 'Users', 'print'),
('users', 'Users', 'export'),
('settings', 'Settings', 'view'),
('settings', 'Settings', 'create'),
('settings', 'Settings', 'edit'),
('settings', 'Settings', 'delete'),
('settings', 'Settings', 'print'),
('settings', 'Settings', 'export')
ON DUPLICATE KEY UPDATE `module_label` = VALUES(`module_label`);

-- ------------------------------------------------------------
-- 7. Seed default role -> permission grants
--    (Super Admin is intentionally NOT seeded here: it is treated as an
--     all-access super role directly in code, see config/rbac.php)
-- ------------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='dashboard' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='dashboard' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='dashboard' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='dashboard' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='dashboard' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='dashboard' AND p.action='export' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='products' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='products' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='products' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='products' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='products' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='products' AND p.action='export' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='categories' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='categories' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='categories' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='categories' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='categories' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='categories' AND p.action='export' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='raw_materials' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='raw_materials' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='raw_materials' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='raw_materials' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='raw_materials' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='raw_materials' AND p.action='export' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='suppliers' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='suppliers' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='suppliers' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='suppliers' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='suppliers' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='suppliers' AND p.action='export' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='customers' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='customers' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='customers' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='customers' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='customers' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='customers' AND p.action='export' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='purchase' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='purchase' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='purchase' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='purchase' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='purchase' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='purchase' AND p.action='export' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='export' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='stock' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='stock' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='stock' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='stock' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='stock' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='stock' AND p.action='export' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='reports' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='reports' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='reports' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='reports' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='reports' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='reports' AND p.action='export' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='ledger' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='ledger' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='ledger' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='ledger' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='ledger' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='ledger' AND p.action='export' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='expenses' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='expenses' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='expenses' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='expenses' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='expenses' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='expenses' AND p.action='export' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='attendance' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='attendance' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='attendance' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='attendance' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='attendance' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='attendance' AND p.action='export' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='employees' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='employees' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='employees' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='employees' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='employees' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='employees' AND p.action='export' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='users' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='users' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='users' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='users' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='users' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='users' AND p.action='export' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='settings' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='settings' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='settings' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='settings' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='settings' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='settings' AND p.action='export' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='dashboard' AND p.action='view' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='products' AND p.action='view' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='products' AND p.action='create' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='products' AND p.action='edit' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='categories' AND p.action='view' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='categories' AND p.action='create' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='categories' AND p.action='edit' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='raw_materials' AND p.action='view' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='raw_materials' AND p.action='create' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='raw_materials' AND p.action='edit' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='suppliers' AND p.action='view' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='suppliers' AND p.action='create' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='suppliers' AND p.action='edit' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='customers' AND p.action='view' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='customers' AND p.action='create' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='customers' AND p.action='edit' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='purchase' AND p.action='view' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='purchase' AND p.action='create' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='purchase' AND p.action='edit' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='view' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='create' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='edit' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='stock' AND p.action='view' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='stock' AND p.action='create' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='stock' AND p.action='edit' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='reports' AND p.action='view' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='reports' AND p.action='print' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='reports' AND p.action='export' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='ledger' AND p.action='view' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='expenses' AND p.action='view' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='expenses' AND p.action='create' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='expenses' AND p.action='edit' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='attendance' AND p.action='view' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='employees' AND p.action='view' WHERE r.slug='manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='dashboard' AND p.action='view' WHERE r.slug='counter-boy'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='view' WHERE r.slug='counter-boy'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='create' WHERE r.slug='counter-boy'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='print' WHERE r.slug='counter-boy'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='customers' AND p.action='view' WHERE r.slug='counter-boy'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='customers' AND p.action='create' WHERE r.slug='counter-boy'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='dashboard' AND p.action='view' WHERE r.slug='sales-person'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='view' WHERE r.slug='sales-person'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='create' WHERE r.slug='sales-person'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='print' WHERE r.slug='sales-person'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='customers' AND p.action='view' WHERE r.slug='sales-person'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='dashboard' AND p.action='view' WHERE r.slug='store-keeper'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='products' AND p.action='view' WHERE r.slug='store-keeper'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='products' AND p.action='create' WHERE r.slug='store-keeper'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='products' AND p.action='edit' WHERE r.slug='store-keeper'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='raw_materials' AND p.action='view' WHERE r.slug='store-keeper'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='raw_materials' AND p.action='create' WHERE r.slug='store-keeper'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='raw_materials' AND p.action='edit' WHERE r.slug='store-keeper'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='stock' AND p.action='view' WHERE r.slug='store-keeper'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='stock' AND p.action='create' WHERE r.slug='store-keeper'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='stock' AND p.action='edit' WHERE r.slug='store-keeper'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='stock' AND p.action='print' WHERE r.slug='store-keeper'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='purchase' AND p.action='view' WHERE r.slug='store-keeper'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='purchase' AND p.action='create' WHERE r.slug='store-keeper'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='dashboard' AND p.action='view' WHERE r.slug='accountant'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='ledger' AND p.action='view' WHERE r.slug='accountant'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='ledger' AND p.action='create' WHERE r.slug='accountant'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='ledger' AND p.action='edit' WHERE r.slug='accountant'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='ledger' AND p.action='print' WHERE r.slug='accountant'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='ledger' AND p.action='export' WHERE r.slug='accountant'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='expenses' AND p.action='view' WHERE r.slug='accountant'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='expenses' AND p.action='create' WHERE r.slug='accountant'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='expenses' AND p.action='edit' WHERE r.slug='accountant'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='expenses' AND p.action='print' WHERE r.slug='accountant'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='expenses' AND p.action='export' WHERE r.slug='accountant'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='reports' AND p.action='view' WHERE r.slug='accountant'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='reports' AND p.action='print' WHERE r.slug='accountant'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='reports' AND p.action='export' WHERE r.slug='accountant'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='view' WHERE r.slug='accountant'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='purchase' AND p.action='view' WHERE r.slug='accountant';

-- ------------------------------------------------------------
-- 8. Backfill: any existing branch_users without a role get mapped to
--    a role by their legacy `user_type` value where possible, otherwise
--    default to 'Manager'. Adjust the CASE below to match your real
--    user_type values before running in production.
-- ------------------------------------------------------------
UPDATE `branch_users` bu
LEFT JOIN `roles` r ON r.slug = CASE
    WHEN bu.user_type IN ('admin','administrator') THEN 'admin'
    WHEN bu.user_type IN ('manager') THEN 'manager'
    WHEN bu.user_type IN ('counter','counter_boy','counter-boy') THEN 'counter-boy'
    WHEN bu.user_type IN ('sales','sales_person','sales-person') THEN 'sales-person'
    WHEN bu.user_type IN ('store','store_keeper','store-keeper') THEN 'store-keeper'
    WHEN bu.user_type IN ('accountant') THEN 'accountant'
    ELSE 'manager'
END
SET bu.role_id = r.id
WHERE bu.role_id IS NULL;
