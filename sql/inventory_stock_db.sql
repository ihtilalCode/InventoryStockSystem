-- Inventory Stock System stock_movements schema
CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firm_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    movement_type ENUM('in','out','transfer','adjust','return') NOT NULL,
    qty_change DECIMAL(14,2) NOT NULL,
    source_location VARCHAR(100) DEFAULT NULL,
    target_location VARCHAR(100) DEFAULT NULL,
    prev_stock DECIMAL(14,2) DEFAULT NULL,
    new_stock DECIMAL(14,2) DEFAULT NULL,
    ref_type VARCHAR(50) DEFAULT NULL,
    ref_id BIGINT UNSIGNED DEFAULT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_firm_created (firm_id, created_at),
    INDEX idx_product_created (product_id, created_at),
    INDEX idx_ref (ref_type, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
