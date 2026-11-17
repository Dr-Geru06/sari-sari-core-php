USE hard_sys_shop;

SET @product_is_available_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'is_available'
);

SET @product_is_available_update = IF(
    @product_is_available_exists = 0,
    'ALTER TABLE products ADD COLUMN is_available TINYINT(1) NOT NULL DEFAULT 1 AFTER stock',
    'SELECT ''Product availability column already exists'' AS migration_status'
);

PREPARE product_is_available_statement FROM @product_is_available_update;
EXECUTE product_is_available_statement;
DEALLOCATE PREPARE product_is_available_statement;
