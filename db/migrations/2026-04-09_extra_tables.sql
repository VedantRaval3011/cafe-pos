-- Extra tables to reach the 12-table requirement
-- Apply to database `ns_coffee` in phpMyAdmin / MySQL.

START TRANSACTION;

-- --------------------------------------------------------
-- 8. categories
--    Normalises the free-text `type` column in `products`.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`          int(11)       NOT NULL AUTO_INCREMENT,
  `name`        varchar(100)  NOT NULL,           -- e.g. coffee, drink, dessert, starter, main dish
  `description` text          DEFAULT NULL,
  `image`       varchar(255)  DEFAULT NULL,
  `created_at`  timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed from existing product types
INSERT IGNORE INTO `categories` (`name`) VALUES
  ('coffee'), ('drink'), ('dessert'), ('starter'), ('main dish');

-- --------------------------------------------------------
-- 9. product_reviews
--    Customers leave a star rating + comment on a product.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_reviews` (
  `id`         int(11)       NOT NULL AUTO_INCREMENT,
  `product_id` int(11)       NOT NULL,
  `user_id`    int(11)       NOT NULL,
  `rating`     tinyint(1)    NOT NULL DEFAULT 5,   -- 1-5 stars
  `comment`    text          DEFAULT NULL,
  `created_at` timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_reviews_product_id` (`product_id`),
  KEY `idx_reviews_user_id`    (`user_id`),
  CONSTRAINT `chk_rating` CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 10. coupons
--     Discount / promo codes that can be applied to orders.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `coupons` (
  `id`              int(11)        NOT NULL AUTO_INCREMENT,
  `code`            varchar(50)    NOT NULL,              -- e.g. WELCOME10
  `discount_type`   enum('percent','fixed') NOT NULL DEFAULT 'percent',
  `discount_value`  decimal(10,2)  NOT NULL DEFAULT 0.00, -- % or ₹ amount
  `min_order_value` decimal(10,2)  NOT NULL DEFAULT 0.00,
  `max_uses`        int(11)        DEFAULT NULL,          -- NULL = unlimited
  `used_count`      int(11)        NOT NULL DEFAULT 0,
  `valid_from`      date           DEFAULT NULL,
  `valid_until`     date           DEFAULT NULL,
  `is_active`       tinyint(1)     NOT NULL DEFAULT 1,
  `created_at`      timestamp      NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupons_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sample coupon
INSERT IGNORE INTO `coupons` (`code`, `discount_type`, `discount_value`, `min_order_value`, `valid_until`)
VALUES ('WELCOME10', 'percent', 10.00, 200.00, '2026-12-31');

-- --------------------------------------------------------
-- 11. tables
--     Physical restaurant tables tracked for QR ordering.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tables` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `table_number` int(11)      NOT NULL,
  `capacity`     int(11)      NOT NULL DEFAULT 4,   -- seats
  `location`     varchar(100) DEFAULT NULL,          -- e.g. 'indoor', 'outdoor', 'window'
  `is_available` tinyint(1)   NOT NULL DEFAULT 1,
  `qr_token`     varchar(64)  DEFAULT NULL,          -- unique token embedded in QR code
  `created_at`   timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tables_number` (`table_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed a few tables
INSERT IGNORE INTO `tables` (`table_number`, `capacity`, `location`) VALUES
  (1, 2, 'window'),
  (2, 4, 'indoor'),
  (3, 4, 'indoor'),
  (4, 6, 'outdoor'),
  (5, 2, 'outdoor');

-- --------------------------------------------------------
-- 12. staff
--     Kitchen staff, waitstaff, baristas, etc.
--     Separate from `admins` (who manage the backend).
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `staff` (
  `id`         int(11)       NOT NULL AUTO_INCREMENT,
  `full_name`  varchar(100)  NOT NULL,
  `email`      varchar(100)  NOT NULL,
  `phone`      varchar(20)   DEFAULT NULL,
  `role`       varchar(50)   NOT NULL DEFAULT 'waiter', -- waiter, barista, chef, cashier
  `shift`      enum('morning','afternoon','night') NOT NULL DEFAULT 'morning',
  `salary`     decimal(10,2) DEFAULT NULL,
  `is_active`  tinyint(1)    NOT NULL DEFAULT 1,
  `hired_at`   date          DEFAULT NULL,
  `created_at` timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
