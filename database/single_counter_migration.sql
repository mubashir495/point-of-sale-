-- Marketing Booster: single-counter database configuration
-- Safe for existing data: does not delete or rewrite historical orders.
-- MySQL 5.7+ / MariaDB. Back up the database before applying.
CREATE TABLE IF NOT EXISTS marketing_booster_counter_config (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  counter_name VARCHAR(100) NOT NULL DEFAULT 'Main Counter',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_mb_single_counter CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO marketing_booster_counter_config (id, counter_name, is_active)
VALUES (1, 'Main Counter', 1)
ON DUPLICATE KEY UPDATE id = id;
-- The application currently identifies counter sales by orders.status='Counter'.
-- No separate counter-1/counter-2 DB entities were found in the shipped SQL.
-- Do NOT drop historical branches, orders, payments or counter sale records.
-- Application must read this singleton configuration to enforce the counter.
