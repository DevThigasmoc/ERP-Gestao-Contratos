CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    type ENUM('plan','addon','service','setup') NOT NULL,
    key_slug VARCHAR(60) NOT NULL,
    label VARCHAR(160) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    per_user TINYINT(1) NOT NULL DEFAULT 0,
    recurring TINYINT(1) NOT NULL DEFAULT 0,
    billing_cycle ENUM('monthly','one_time') NOT NULL DEFAULT 'monthly',
    max_discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_products_company_slug (company_id, key_slug),
    INDEX idx_products_company (company_id, type, active),
    CONSTRAINT fk_products_company FOREIGN KEY (company_id) REFERENCES companies(id)
);
