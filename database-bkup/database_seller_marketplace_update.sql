USE hard_sys_shop;

CREATE TABLE IF NOT EXISTS shops (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL UNIQUE,
    store_name VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(500) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    review_note VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_shops_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET @product_shop_id_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'shop_id'
);

SET @product_shop_id_update = IF(
    @product_shop_id_exists = 0,
    'ALTER TABLE products ADD COLUMN shop_id INT UNSIGNED NULL AFTER id',
    'SELECT ''Product shop ownership column already exists'' AS migration_status'
);

PREPARE product_shop_id_statement FROM @product_shop_id_update;
EXECUTE product_shop_id_statement;
DEALLOCATE PREPARE product_shop_id_statement;

SET @product_approval_status_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'approval_status'
);

SET @product_approval_status_update = IF(
    @product_approval_status_exists = 0,
    'ALTER TABLE products ADD COLUMN approval_status VARCHAR(16) NOT NULL DEFAULT ''approved'' AFTER is_active',
    'SELECT ''Product approval status column already exists'' AS migration_status'
);

PREPARE product_approval_status_statement FROM @product_approval_status_update;
EXECUTE product_approval_status_statement;
DEALLOCATE PREPARE product_approval_status_statement;

SET @product_review_note_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'review_note'
);

SET @product_review_note_update = IF(
    @product_review_note_exists = 0,
    'ALTER TABLE products ADD COLUMN review_note VARCHAR(500) NULL AFTER approval_status',
    'SELECT ''Product review note column already exists'' AS migration_status'
);

PREPARE product_review_note_statement FROM @product_review_note_update;
EXECUTE product_review_note_statement;
DEALLOCATE PREPARE product_review_note_statement;

SET @product_shop_index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND INDEX_NAME = 'idx_products_shop_approval'
);

SET @product_shop_index_update = IF(
    @product_shop_index_exists = 0,
    'ALTER TABLE products ADD INDEX idx_products_shop_approval (shop_id, approval_status)',
    'SELECT ''Product shop/approval index already exists'' AS migration_status'
);

PREPARE product_shop_index_statement FROM @product_shop_index_update;
EXECUTE product_shop_index_statement;
DEALLOCATE PREPARE product_shop_index_statement;

SET @product_shop_fk_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND CONSTRAINT_NAME = 'fk_products_shop'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @product_shop_fk_update = IF(
    @product_shop_fk_exists = 0,
    'ALTER TABLE products ADD CONSTRAINT fk_products_shop FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE RESTRICT',
    'SELECT ''Product shop ownership constraint already exists'' AS migration_status'
);

PREPARE product_shop_fk_statement FROM @product_shop_fk_update;
EXECUTE product_shop_fk_statement;
DEALLOCATE PREPARE product_shop_fk_statement;
