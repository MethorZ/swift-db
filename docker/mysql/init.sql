-- MethorZ Database Package - Test Schema
-- This schema is used for integration tests

-- Product table (main test entity)
CREATE TABLE IF NOT EXISTS product (
    product_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_uuid CHAR(36) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_description TEXT,
    product_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    product_stock INT NOT NULL DEFAULT 0,
    product_category_id BIGINT UNSIGNED,
    product_active TINYINT(1) NOT NULL DEFAULT 1,
    product_version INT UNSIGNED NOT NULL DEFAULT 1,
    product_updated TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
    product_created TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
    UNIQUE INDEX idx_uq_uuid (product_uuid),
    INDEX idx_category_id (product_category_id),
    INDEX idx_active (product_active),
    INDEX idx_created (product_created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Category table (for join tests)
CREATE TABLE IF NOT EXISTS category (
    category_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    category_parent_id BIGINT UNSIGNED,
    category_updated TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
    category_created TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
    INDEX idx_parent_id (category_parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User table (for relation tests)
CREATE TABLE IF NOT EXISTS user (
    user_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_uuid CHAR(36) NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    user_name VARCHAR(100) NOT NULL,
    user_active TINYINT(1) NOT NULL DEFAULT 1,
    user_version INT UNSIGNED NOT NULL DEFAULT 1,
    user_updated TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
    user_created TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
    UNIQUE INDEX idx_uq_uuid (user_uuid),
    UNIQUE INDEX idx_uq_email (user_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order table (for transaction tests)
CREATE TABLE IF NOT EXISTS `order` (
    order_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_uuid CHAR(36) NOT NULL,
    order_user_id BIGINT UNSIGNED NOT NULL,
    order_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    order_status ENUM('pending', 'processing', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    order_updated TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
    order_created TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
    UNIQUE INDEX idx_uq_uuid (order_uuid),
    INDEX idx_user_id (order_user_id),
    INDEX idx_status (order_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some seed data for tests
INSERT INTO category (category_name) VALUES
    ('Electronics'),
    ('Clothing'),
    ('Books');

INSERT INTO product (product_uuid, product_name, product_description, product_price, product_stock, product_category_id) VALUES
    ('11111111-1111-1111-1111-111111111111', 'Test Product 1', 'Description 1', 19.99, 100, 1),
    ('22222222-2222-2222-2222-222222222222', 'Test Product 2', 'Description 2', 29.99, 50, 1),
    ('33333333-3333-3333-3333-333333333333', 'Test Product 3', 'Description 3', 39.99, 0, 2);

