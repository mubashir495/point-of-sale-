-- ============================================================
-- Sale Returns Migration
-- Adds support for returning items from a Counter Sale (POS)
-- invoice: restocks the product and records the return so it can
-- be netted off against daily / overall sale totals.
-- Run this once against your existing database.
-- ============================================================

CREATE TABLE IF NOT EXISTS sale_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_no VARCHAR(50) NOT NULL,
    order_id INT NOT NULL,
    invoice_no VARCHAR(50) NOT NULL,
    customer_id INT NULL,
    return_date DATE NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    remarks VARCHAR(255) NULL,
    branch_id INT NULL,
    created_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sale_returns_return_no (return_no),
    KEY idx_sale_returns_order_id (order_id),
    KEY idx_sale_returns_return_date (return_date),
    CONSTRAINT fk_sale_returns_order FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sale_return_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_id INT NOT NULL,
    order_item_id INT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(12,3) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    KEY idx_sale_return_items_return_id (return_id),
    KEY idx_sale_return_items_order_item_id (order_item_id),
    CONSTRAINT fk_sale_return_items_return FOREIGN KEY (return_id) REFERENCES sale_returns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
