CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `description` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `products` VALUES (1, 'Widget A', 19.99, 'A basic widget'), (2, 'Widget B', 29.99, 'An advanced widget'), (3, 'Widget C', 39.99, 'A premium widget'), (4, 'Gadget X', 49.99, 'A useful gadget'), (5, 'Gadget Y', 59.99, 'A powerful gadget');
CREATE INDEX idx_name ON `products` (`name`);
INSERT INTO `products` VALUES (6, 'Tool Alpha', 99.99, 'Professional tool'), (7, 'Tool Beta', 149.99, 'Expert tool'), (8, 'Tool Gamma', 199.99, 'Master tool');
SELECT COUNT(*) FROM `products`;
INSERT IGNORE INTO `products` VALUES (9, 'Duplicate Test', 9.99, 'Test for ignore'), (10, 'Another Duplicate', 10.99, 'Another test');
UPDATE `products` SET `price` = `price` * 1.1 WHERE `id` > 5;
