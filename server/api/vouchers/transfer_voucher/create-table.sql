CREATE TABLE IF NOT EXISTS transfer_voucher (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id           INT NOT NULL,
    company_id          INT NOT NULL,
    voucher_number      VARCHAR(30) NOT NULL,
    voucher_date        DATE NOT NULL,

    -- FROM party
    from_type           ENUM('customer','supplier') NOT NULL,
    from_customer_id    INT DEFAULT NULL,
    from_supplier_id    INT DEFAULT NULL,
    from_sub_account_id INT DEFAULT NULL,

    -- TO party
    to_type             ENUM('customer','supplier') NOT NULL,
    to_customer_id      INT DEFAULT NULL,
    to_supplier_id      INT DEFAULT NULL,
    to_sub_account_id   INT DEFAULT NULL,

    amount              DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    currency_id         INT NOT NULL,
    payment_method_id   INT NOT NULL,
    bank_account_id     INT DEFAULT NULL,
    cheque_no           VARCHAR(100) DEFAULT NULL,
    cheque_date         DATE DEFAULT NULL,
    description         TEXT DEFAULT NULL,

    created_by          INT DEFAULT NULL,
    updated_by          INT DEFAULT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant    (tenant_id),
    INDEX idx_date      (voucher_date),
    INDEX idx_from_cust (from_customer_id),
    INDEX idx_from_supp (from_supplier_id),
    INDEX idx_to_cust   (to_customer_id),
    INDEX idx_to_supp   (to_supplier_id)
);
