
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS stock_movements, products, suppliers, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE suppliers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  contact_person VARCHAR(100) NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(120) NULL,
  address VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_supplier_name (name)
) ENGINE=InnoDB;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  sku VARCHAR(60) NOT NULL UNIQUE,
  category_id INT UNSIGNED NOT NULL,
  supplier_id INT UNSIGNED NULL,
  description TEXT NULL,
  purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  quantity INT NOT NULL DEFAULT 0,
  min_stock INT NOT NULL DEFAULT 5,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_product_name (name),
  INDEX idx_product_category (category_id),
  INDEX idx_product_supplier (supplier_id),
  CONSTRAINT fk_product_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_product_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE stock_movements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  type ENUM('in','out','adjustment') NOT NULL,
  quantity INT NOT NULL COMMENT 'units moved (for adjustment: the signed difference)',
  qty_before INT NOT NULL,
  qty_after INT NOT NULL,
  remarks VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_move_product (product_id),
  INDEX idx_move_date (created_at),
  CONSTRAINT fk_move_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_move_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO categories (name, description) VALUES
 ('Beverages','Drinks and refreshments'),
 ('Snacks','Chips, biscuits and candies'),
 ('Household','Cleaning and home supplies'),
 ('School & Office','Paper, pens and other supplies');

INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES
 ('Metro Wholesale Trading','Ana Reyes','0917 123 4567','sales@metrowholesale.example','Iloilo City'),
 ('Sunrise Distributors','Mark Santos','0922 987 6543','orders@sunrise.example','Bacolod City');

INSERT INTO products (name, sku, category_id, supplier_id, description, purchase_price, selling_price, quantity, min_stock, unit) VALUES
 ('Bottled Water 500ml','BEV-001',1,1,'Purified drinking water',8.00,15.00,120,24,'bottle'),
 ('Instant Coffee 3-in-1','BEV-002',1,1,'Sachet coffee mix',5.50,9.00,8,30,'sachet'),
 ('Potato Chips 60g','SNK-001',2,2,'Salted potato chips',22.00,35.00,45,10,'pack'),
 ('Dishwashing Liquid 250ml','HSE-001',3,2,'Lemon scent',38.00,55.00,0,6,'bottle'),
 ('Ballpen Blue','OFC-001',4,1,'0.5mm ballpoint pen',4.00,8.00,200,50,'pcs');

INSERT INTO stock_movements (product_id, type, quantity, qty_before, qty_after, remarks) VALUES
 (1,'in',120,0,120,'Initial stock'),(2,'in',8,0,8,'Initial stock'),(3,'in',45,0,45,'Initial stock'),
 (4,'in',0,0,0,'Initial stock'),(5,'in',200,0,200,'Initial stock');
