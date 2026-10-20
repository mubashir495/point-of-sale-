-- ============================================================
-- Supplier Portal migration
-- Inventory System - inventory-system
--
-- Adds:
--   - a new permission module `supplier_portal` (My Deliveries page)
--   - a new role `Supplier` (slug: supplier) with view+edit on it
--   - grants full supplier_portal access to the existing `admin` role
--     (Super Admin already sees everything by design, no row needed)
--
-- Run once against your existing `inventory_db` database:
--   mysql -u USER -p inventory_db < database/supplier_portal_migration.sql
-- Safe to re-run - every insert uses ON DUPLICATE KEY UPDATE / IGNORE.
-- ============================================================

-- ------------------------------------------------------------
-- 1. Permission catalogue rows for the new module
-- ------------------------------------------------------------
INSERT INTO `permissions` (`module_key`, `module_label`, `action`) VALUES
('supplier_portal', 'Supplier Portal (My Deliveries)', 'view'),
('supplier_portal', 'Supplier Portal (My Deliveries)', 'create'),
('supplier_portal', 'Supplier Portal (My Deliveries)', 'edit'),
('supplier_portal', 'Supplier Portal (My Deliveries)', 'delete'),
('supplier_portal', 'Supplier Portal (My Deliveries)', 'print'),
('supplier_portal', 'Supplier Portal (My Deliveries)', 'export')
ON DUPLICATE KEY UPDATE `module_label` = VALUES(`module_label`);

-- ------------------------------------------------------------
-- 2. New role: Supplier
--    (This is the login role for employees whose `designation` in the
--    `employees` table is 'Supplier' - i.e. delivery agents. Give each
--    such employee a Branch User account with employee_id = their
--    employees.id and role_id = this role.)
-- ------------------------------------------------------------
INSERT INTO `roles` (`name`, `slug`, `description`, `is_system`) VALUES
('Supplier', 'supplier', 'Delivery agent access: can view and update the delivery status of only their own assigned orders.', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ------------------------------------------------------------
-- 3. Grant supplier role -> supplier_portal (view + edit only;
--    they only need to see and update their own delivery orders)
-- ------------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='supplier_portal' AND p.action='view' WHERE r.slug='supplier'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='supplier_portal' AND p.action='edit' WHERE r.slug='supplier'
ON DUPLICATE KEY UPDATE `role_id` = VALUES(`role_id`);

-- ------------------------------------------------------------
-- 4. Grant the existing Admin role full access to the new module too,
--    matching how every other module was granted to admin in
--    rbac_schema.sql. (Super Admin bypasses permission checks entirely
--    in code, so it needs no row here.)
-- ------------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='supplier_portal' AND p.action='view' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='supplier_portal' AND p.action='create' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='supplier_portal' AND p.action='edit' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='supplier_portal' AND p.action='delete' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='supplier_portal' AND p.action='print' WHERE r.slug='admin'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='supplier_portal' AND p.action='export' WHERE r.slug='admin'
ON DUPLICATE KEY UPDATE `role_id` = VALUES(`role_id`);

-- ------------------------------------------------------------
-- 5. New role: Branch Manager
--    Handles only the customers ("party") specifically assigned to
--    them via the existing "Linked Parties" picker on the Branch
--    Users page (table `user_parties`). See config/rbac.php's
--    rbac_scope_customer_ids() for how this is enforced.
-- ------------------------------------------------------------
INSERT INTO `roles` (`name`, `slug`, `description`, `is_system`) VALUES
('Branch Manager', 'branch-manager', 'Manages only the customers/parties assigned to them for their branch.', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='dashboard' AND p.action='view' WHERE r.slug='branch-manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='customers' AND p.action='view' WHERE r.slug='branch-manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='customers' AND p.action='create' WHERE r.slug='branch-manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='customers' AND p.action='edit' WHERE r.slug='branch-manager'
UNION ALL
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.module_key='sales' AND p.action='view' WHERE r.slug='branch-manager'
ON DUPLICATE KEY UPDATE `role_id` = VALUES(`role_id`);

