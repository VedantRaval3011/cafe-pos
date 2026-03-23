-- Per line item kitchen status (admin can set dessert=preparing while main=ready, etc.)
-- Run on your MySQL database (e.g. ns_coffee).

ALTER TABLE `order_items`
  ADD COLUMN `item_status` varchar(30) NOT NULL DEFAULT 'placed' AFTER `line_total`;

-- Backfill from current order status
UPDATE `order_items` oi
INNER JOIN `orders` o ON oi.order_id = o.id
SET oi.item_status = CASE o.status
  WHEN 'created' THEN 'placed'
  WHEN 'placed' THEN 'placed'
  WHEN 'preparing' THEN 'preparing'
  WHEN 'brewing' THEN 'brewing'
  WHEN 'ready' THEN 'ready'
  WHEN 'delivered' THEN 'delivered'
  WHEN 'cancelled' THEN 'cancelled'
  WHEN 'payment_failed' THEN 'placed'
  ELSE 'placed'
END;
