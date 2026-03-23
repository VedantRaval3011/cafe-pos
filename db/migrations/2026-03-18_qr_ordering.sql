-- QR-Based Smart Ordering + Email Billing System (migration)
-- Apply this to database `ns_coffee` in phpMyAdmin / MySQL.

START TRANSACTION;

-- 1) Support guest (QR) cart sessions
ALTER TABLE `cart`
  ADD COLUMN `session_token` varchar(64) NULL AFTER `user_id`,
  ADD COLUMN `table_number` int NULL AFTER `session_token`,
  MODIFY `user_id` int(3) NULL;

CREATE INDEX `idx_cart_session_token` ON `cart` (`session_token`);
CREATE INDEX `idx_cart_user_id` ON `cart` (`user_id`);

-- 2) Orders: add table mapping + payment/invoice metadata, allow guest orders
ALTER TABLE `orders`
  ADD COLUMN `session_token` varchar(64) NULL AFTER `user_id`,
  ADD COLUMN `table_number` int NULL AFTER `session_token`,
  ADD COLUMN `payment_provider` varchar(30) NULL AFTER `table_number`,
  ADD COLUMN `payment_status` varchar(30) NOT NULL DEFAULT 'unpaid' AFTER `payment_provider`,
  ADD COLUMN `razorpay_order_id` varchar(100) NULL AFTER `payment_status`,
  ADD COLUMN `razorpay_payment_id` varchar(100) NULL AFTER `razorpay_order_id`,
  ADD COLUMN `razorpay_signature` varchar(255) NULL AFTER `razorpay_payment_id`,
  ADD COLUMN `invoice_number` varchar(50) NULL AFTER `razorpay_signature`,
  ADD COLUMN `invoice_pdf_path` varchar(255) NULL AFTER `invoice_number`,
  ADD COLUMN `paid_at` datetime NULL AFTER `invoice_pdf_path`,
  MODIFY `user_id` int(3) NULL;

CREATE INDEX `idx_orders_session_token` ON `orders` (`session_token`);
CREATE INDEX `idx_orders_user_id` ON `orders` (`user_id`);

-- 3) Order line items (for invoice + kitchen view)
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `image` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `size` varchar(30) NOT NULL,
  `quantity` int(10) NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

