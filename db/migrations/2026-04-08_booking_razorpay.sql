-- Table booking: Razorpay + email (safe to re-run on MySQL 8.0.12+ / MariaDB 10.0.2+)

ALTER TABLE `bookings`
  MODIFY `phone` varchar(20) NOT NULL,
  ADD COLUMN IF NOT EXISTS `email` varchar(120) NOT NULL DEFAULT '' AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `amount` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `message`,
  ADD COLUMN IF NOT EXISTS `payment_status` varchar(20) NOT NULL DEFAULT 'unpaid' AFTER `amount`,
  ADD COLUMN IF NOT EXISTS `razorpay_order_id` varchar(100) DEFAULT NULL AFTER `payment_status`,
  ADD COLUMN IF NOT EXISTS `razorpay_payment_id` varchar(100) DEFAULT NULL AFTER `razorpay_order_id`,
  ADD COLUMN IF NOT EXISTS `razorpay_signature` varchar(255) DEFAULT NULL AFTER `razorpay_payment_id`,
  ADD COLUMN IF NOT EXISTS `paid_at` datetime DEFAULT NULL AFTER `razorpay_signature`;

-- Optional: mark very old rows as unpaid legacy (no change needed)
