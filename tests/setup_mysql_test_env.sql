-- Создание тестового пользователя
DROP USER IF EXISTS 'test_user'@'localhost';
CREATE USER 'test_user'@'localhost' IDENTIFIED BY 'test_password_123';

-- Создание тестовых баз данных
DROP DATABASE IF EXISTS test_database_main;
DROP DATABASE IF EXISTS test_database_secondary;
DROP DATABASE IF EXISTS test_database_transactions;

CREATE DATABASE test_database_main CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE test_database_secondary CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE test_database_transactions CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Предоставление полных прав на тестовые базы данных
GRANT ALL PRIVILEGES ON test_database_main.* TO 'test_user'@'localhost';
GRANT ALL PRIVILEGES ON test_database_secondary.* TO 'test_user'@'localhost';
GRANT ALL PRIVILEGES ON test_database_transactions.* TO 'test_user'@'localhost';

FLUSH PRIVILEGES;

-- Создание тестовых таблиц в test_database_main
USE test_database_main;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL,
    age INT,
    balance DECIMAL(10, 2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock INT DEFAULT 0,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_price (price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_product_id (product_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    level VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    context JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_level (level),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Вставка тестовых данных в users
INSERT INTO users (username, email, age, balance, is_active) VALUES
('john_doe', 'john@example.com', 30, 1000.50, 1),
('jane_smith', 'jane@example.com', 25, 2500.75, 1),
('bob_wilson', 'bob@example.com', 35, 500.00, 1),
('alice_johnson', 'alice@example.com', 28, 3000.00, 1),
('charlie_brown', 'charlie@example.com', 40, 150.25, 0),
('david_lee', 'david@example.com', 33, 750.00, 1),
('emma_davis', 'emma@example.com', 27, 1200.50, 1),
('frank_miller', 'frank@example.com', 45, 400.00, 0),
('grace_taylor', 'grace@example.com', 31, 2800.75, 1),
('henry_anderson', 'henry@example.com', 29, 950.00, 1);

-- Вставка тестовых данных в products
INSERT INTO products (name, description, price, stock, category) VALUES
('Laptop Pro 15', 'High-performance laptop with 16GB RAM', 1299.99, 50, 'Electronics'),
('Wireless Mouse', 'Ergonomic wireless mouse with precision tracking', 29.99, 200, 'Accessories'),
('USB-C Hub', '7-in-1 USB-C hub with HDMI and Ethernet', 49.99, 150, 'Accessories'),
('Mechanical Keyboard', 'RGB mechanical keyboard with Cherry MX switches', 149.99, 75, 'Accessories'),
('Monitor 27"', '4K UHD monitor with HDR support', 399.99, 30, 'Electronics'),
('Webcam HD', '1080p webcam with auto-focus', 79.99, 100, 'Electronics'),
('Laptop Bag', 'Waterproof laptop bag with padded compartment', 39.99, 120, 'Accessories'),
('External SSD 1TB', 'Portable SSD with USB 3.1 Gen 2', 129.99, 80, 'Storage'),
('Power Bank 20000mAh', 'High-capacity power bank with fast charging', 59.99, 90, 'Accessories'),
('Bluetooth Headset', 'Noise-cancelling Bluetooth headset', 89.99, 60, 'Audio');

-- Вставка тестовых данных в orders
INSERT INTO orders (user_id, product_id, quantity, total_price, status) VALUES
(1, 1, 1, 1299.99, 'completed'),
(1, 2, 2, 59.98, 'completed'),
(2, 5, 1, 399.99, 'processing'),
(3, 3, 3, 149.97, 'completed'),
(4, 4, 1, 149.99, 'pending'),
(4, 8, 2, 259.98, 'completed'),
(6, 6, 1, 79.99, 'processing'),
(7, 7, 1, 39.99, 'completed'),
(9, 9, 1, 59.99, 'pending'),
(10, 10, 1, 89.99, 'completed');

-- Вставка тестовых данных в logs
INSERT INTO logs (level, message, context) VALUES
('INFO', 'Application started', '{"version": "1.0.0", "environment": "test"}'),
('DEBUG', 'Database connection established', '{"host": "localhost", "database": "test_database_main"}'),
('WARNING', 'High memory usage detected', '{"memory_usage": "85%", "threshold": "80%"}'),
('ERROR', 'Failed to send email notification', '{"recipient": "user@example.com", "error": "SMTP timeout"}'),
('INFO', 'User login successful', '{"user_id": 1, "username": "john_doe", "ip": "192.168.1.100"}');

-- Создание таблиц для тестирования транзакций
USE test_database_transactions;

CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_number VARCHAR(20) NOT NULL UNIQUE,
    balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    from_account_id INT,
    to_account_id INT,
    amount DECIMAL(10, 2) NOT NULL,
    type ENUM('deposit', 'withdrawal', 'transfer') NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_account_id) REFERENCES accounts(id),
    FOREIGN KEY (to_account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Вставка тестовых счетов
INSERT INTO accounts (account_number, balance) VALUES
('ACC001', 10000.00),
('ACC002', 5000.00),
('ACC003', 7500.00),
('ACC004', 2000.00);

-- Вторичная база данных для тестов
USE test_database_secondary;

CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) NOT NULL UNIQUE,
    key_value TEXT,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (key_name, key_value, description) VALUES
('app_name', 'MySQL Test Application', 'Application name'),
('app_version', '1.0.0', 'Application version'),
('max_connections', '100', 'Maximum number of database connections'),
('enable_debug', 'true', 'Enable debug mode'),
('session_timeout', '3600', 'Session timeout in seconds');
