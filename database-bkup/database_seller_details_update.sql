USE hard_sys_shop;

SET @seller_name_column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'seller_name'
);

SET @seller_name_update = IF(
    @seller_name_column_exists = 0,
    'ALTER TABLE products ADD COLUMN seller_name VARCHAR(120) NOT NULL DEFAULT ''Sari-Sari Store'' AFTER image_path',
    'SELECT ''Product seller name column already exists'' AS migration_status'
);

PREPARE seller_name_statement FROM @seller_name_update;
EXECUTE seller_name_statement;
DEALLOCATE PREPARE seller_name_statement;

SET @seller_details_column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'seller_details'
);

SET @seller_details_update = IF(
    @seller_details_column_exists = 0,
    'ALTER TABLE products ADD COLUMN seller_details VARCHAR(500) NULL AFTER seller_name',
    'SELECT ''Product seller details column already exists'' AS migration_status'
);

PREPARE seller_details_statement FROM @seller_details_update;
EXECUTE seller_details_statement;
DEALLOCATE PREPARE seller_details_statement;
