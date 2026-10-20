-- ============================================================
-- Data wipe script
-- Empties (TRUNCATE) every table EXCEPT `raw_materials` and `roles`.
--
-- WARNING: THIS PERMANENTLY DELETES DATA. There is no undo.
-- Take a full backup before running this:
--   mysqldump -u USER -p your_db > backup_before_wipe.sql
--
-- After this runs:
--   - `permissions` and `role_permissions` are also emptied, so every
--     role in `roles` will have ZERO permissions attached. No user
--     (including Admin) will be able to access anything until you
--     re-create permissions and re-assign them to roles from the
--     Roles & Permissions page (or re-seed via
--     database/rbac_schema.sql).
--   - `roles` and `raw_materials` are the only two tables left with
--     their data intact.
--
-- Run:
--   mysql -u USER -p your_db < database/wipe_all_except_raw_materials_and_roles.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE account_heads;
TRUNCATE TABLE areas;
TRUNCATE TABLE attendance;
TRUNCATE TABLE branch_users;
TRUNCATE TABLE brands;
TRUNCATE TABLE categories;
TRUNCATE TABLE chart_of_accounts;
TRUNCATE TABLE cities;
TRUNCATE TABLE customer_ledger;
TRUNCATE TABLE customer_payments;
TRUNCATE TABLE customer_transactions;
TRUNCATE TABLE customers;
TRUNCATE TABLE delivery_schedule;
TRUNCATE TABLE employee_ledger;
TRUNCATE TABLE employees;
TRUNCATE TABLE expenses;
TRUNCATE TABLE invoices;
TRUNCATE TABLE order_items;
TRUNCATE TABLE orders;
TRUNCATE TABLE parties;
TRUNCATE TABLE party_ledger;
TRUNCATE TABLE party_materials;
TRUNCATE TABLE permissions;
TRUNCATE TABLE products;
TRUNCATE TABLE purchase_details;
TRUNCATE TABLE purchase_invoice_items;
TRUNCATE TABLE purchase_invoices;
TRUNCATE TABLE purchase_master;
TRUNCATE TABLE quotations;
TRUNCATE TABLE raw_material_purchase_items;
TRUNCATE TABLE raw_material_purchases;
TRUNCATE TABLE role_permissions;
TRUNCATE TABLE salaries;
TRUNCATE TABLE stock_ledger;
TRUNCATE TABLE user_areas;
TRUNCATE TABLE user_parties;

-- NOT truncated (kept as-is, per instruction):
--   raw_materials
--   roles

SET FOREIGN_KEY_CHECKS = 1;
