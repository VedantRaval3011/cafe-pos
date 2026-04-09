-- Update product prices to INR (run once on existing databases)
UPDATE `products` SET `price` = '189' WHERE `id` = 1;
UPDATE `products` SET `price` = '199' WHERE `id` = 2;
UPDATE `products` SET `price` = '149' WHERE `id` = 3;
UPDATE `products` SET `price` = '89' WHERE `id` = 4;
UPDATE `products` SET `price` = '109' WHERE `id` = 5;
UPDATE `products` SET `price` = '139' WHERE `id` = 6;
UPDATE `products` SET `price` = '149' WHERE `id` IN (7, 8);
UPDATE `products` SET `price` = '179' WHERE `id` = 14;
UPDATE `products` SET `price` = '449' WHERE `id` = 15;
UPDATE `products` SET `price` = '529' WHERE `id` = 16;
UPDATE `products` SET `price` = '549' WHERE `id` = 17;
UPDATE `products` SET `price` = '259' WHERE `id` = 18;
UPDATE `products` SET `price` = '329' WHERE `id` = 19;
UPDATE `products` SET `price` = '699' WHERE `id` = 20;
