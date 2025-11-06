CREATE TABLE IF NOT EXISTS commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    vendor_user_id INT NOT NULL,
    proposal_id INT NOT NULL,
    subscription_id INT NOT NULL,
    invoice_id INT NOT NULL,
    base_amount DECIMAL(10,2) NOT NULL,
    rate DECIMAL(5,4) NOT NULL,
    total_commission DECIMAL(10,2) NOT NULL,
    splits INT NOT NULL DEFAULT 6,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_commissions_company_vendor (company_id, vendor_user_id),
    CONSTRAINT fk_commissions_company FOREIGN KEY (company_id) REFERENCES companies(id)
);

CREATE TABLE IF NOT EXISTS commission_installments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commission_id INT NOT NULL,
    n INT NOT NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('open','paid','canceled') DEFAULT 'open',
    paid_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_commission_installments (commission_id, status),
    CONSTRAINT fk_commission_installments_commission FOREIGN KEY (commission_id) REFERENCES commissions(id)
);
