-- Create Database
CREATE DATABASE IF NOT EXISTS veloo_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE veloo_inventory;

-- Table: stores
CREATE TABLE stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    lat DECIMAL(9,6),
    lng DECIMAL(9,6),
    contact_phone VARCHAR(20),
    contact_email VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- Seed Categories
INSERT INTO categories (name) VALUES 
('Drinks'),
('Toiletries'),
('Snacks'),
('Dairy'),
('Cleaning Supplies'),
('Spices'),
('Canned Goods'),
('Frozen Items');

-- Table: items
CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    category_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    brand VARCHAR(100),
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    markup_price DECIMAL(10,2),
    image_url TEXT,
    in_stock BOOLEAN DEFAULT TRUE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Table: admins (Veloo internal team uploading inventory)
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(150) UNIQUE,
    password_hash TEXT,
    assigned_store_id INT,
    role ENUM('admin', 'staff') DEFAULT 'staff',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (assigned_store_id) REFERENCES stores(id)
);

-- Table: inventory_logs (track item updates for audit)
CREATE TABLE inventory_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT,
    admin_id INT,
    old_price DECIMAL(10,2),
    new_price DECIMAL(10,2),
    old_stock BOOLEAN,
    new_stock BOOLEAN,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (item_id) REFERENCES items(id),
    FOREIGN KEY (admin_id) REFERENCES admins(id)
);
