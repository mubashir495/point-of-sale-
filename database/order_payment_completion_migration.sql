-- ============================================================
-- Order Completion / Payment Sync migration
-- Inventory System - inventory-system
--
-- Supports the "Complete Order" payment-confirmation flow:
--   action/orders/update_status.php + config/accounting_helpers.php
--   (applyOrderPaymentAndSyncInvoice)
--
-- Every statement is additive and checks for the column/index
-- before changing anything, so it is SAFE to re-run and safe to run
-- against a database that already has some of these columns.
--
-- Run once:
--   mysql -u USER -p inventory_db < database/order_payment_completion_migration.sql
-- ============================================================

-- ------------------------------------------------------------
-- 1. orders: make sure payment tracking columns exist and are
--    wide enough for 'Pending' / 'Partial' / 'Paid' (some installs
--    only ever wrote 'Paid' via Counter Sale, so the column may be
--    a narrow ENUM/VARCHAR that would reject the other values).
-- ------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'paid_amount');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `total`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'payment_status');
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE `orders` ADD COLUMN `payment_status` VARCHAR(20) NOT NULL DEFAULT 'Pending' AFTER `paid_amount`",
    "ALTER TABLE `orders` MODIFY COLUMN `payment_status` VARCHAR(20) NOT NULL DEFAULT 'Pending'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'completed_by');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `completed_by` INT UNSIGNED NULL AFTER `payment_status`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'completed_at');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `completed_at` DATETIME NULL AFTER `completed_by`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 2. invoices: one invoice per order. A unique key on order_id lets
--    the completion flow do a safe "find or create" without ever
--    creating two invoices for the same order (idempotency).
--    NULLs are allowed to repeat under a UNIQUE key in MySQL, so
--    invoices created some other way (e.g. from quotations, with no
--    order_id) are unaffected.
-- ------------------------------------------------------------
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND INDEX_NAME = 'uq_invoices_order_id');
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE `invoices` ADD UNIQUE KEY `uq_invoices_order_id` (`order_id`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = 'status');
SET @sql := IF(@col_exists > 0,
    "ALTER TABLE `invoices` MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'Unpaid'",
    "ALTER TABLE `invoices` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'Unpaid'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 3. customer_payments: link each payment back to the order/invoice
--    it was collected for, and to the user who recorded it, so
--    "Payment date/time and responsible user" is auditable per the
--    spec, and so a payment can be traced to one specific invoice
--    instead of only ever being applied by blind FIFO.
-- ------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_payments' AND COLUMN_NAME = 'order_id');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `customer_payments` ADD COLUMN `order_id` INT UNSIGNED NULL AFTER `customer_id`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_payments' AND COLUMN_NAME = 'invoice_id');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `customer_payments` ADD COLUMN `invoice_id` INT UNSIGNED NULL AFTER `order_id`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_payments' AND COLUMN_NAME = 'recorded_by');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `customer_payments` ADD COLUMN `recorded_by` INT UNSIGNED NULL AFTER `invoice_id`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 4. customer_ledger: reference_type is used as an idempotency guard
--    (customerLedgerExists()/partyLedgerExists() style checks) so it
--    must be wide enough for the new 'invoice' / 'order_payment'
--    reference types used by the completion flow.
-- ------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_ledger' AND COLUMN_NAME = 'reference_type');
SET @sql := IF(@col_exists > 0,
    'ALTER TABLE `customer_ledger` MODIFY COLUMN `reference_type` VARCHAR(30) NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
