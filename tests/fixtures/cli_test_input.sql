-- CLI Test Input File
-- This file contains a mix of SQL statements for testing CLI optimization

-- Create table statement (should pass through unchanged)
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `description` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Consecutive INSERT statements (should be batched)
INSERT INTO `products` VALUES (1, 'Widget A', 19.99, 'A basic widget');
INSERT INTO `products` VALUES (2, 'Widget B', 29.99, 'An advanced widget');
INSERT INTO `products` VALUES (3, 'Widget C', 39.99, 'A premium widget');
INSERT INTO `products` VALUES (4, 'Gadget X', 49.99, 'A useful gadget');
INSERT INTO `products` VALUES (5, 'Gadget Y', 59.99, 'A powerful gadget');

-- Index creation (should pass through unchanged)
CREATE INDEX idx_name ON `products` (`name`);

-- More INSERT statements (should be batched separately due to interruption)
INSERT INTO `products` VALUES (6, 'Tool Alpha', 99.99, 'Professional tool');
INSERT INTO `products` VALUES (7, 'Tool Beta', 149.99, 'Expert tool');
INSERT INTO `products` VALUES (8, 'Tool Gamma', 199.99, 'Master tool');

-- Select statement (should pass through unchanged)
SELECT COUNT(*) FROM `products`;

-- INSERT IGNORE statements (should be batched together)
INSERT IGNORE INTO `products` VALUES (9, 'Duplicate Test', 9.99, 'Test for ignore');
INSERT IGNORE INTO `products` VALUES (10, 'Another Duplicate', 10.99, 'Another test');

-- Final statement
UPDATE `products` SET `price` = `price` * 1.1 WHERE `id` > 5;
