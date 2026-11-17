USE hard_sys_shop;

SET @profile_image_column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'customers'
      AND COLUMN_NAME = 'profile_image_path'
);

SET @profile_image_update = IF(
    @profile_image_column_exists = 0,
    'ALTER TABLE customers ADD COLUMN profile_image_path VARCHAR(255) NULL AFTER email',
    'SELECT ''Customer profile image column already exists'' AS migration_status'
);

PREPARE profile_image_statement FROM @profile_image_update;
EXECUTE profile_image_statement;
DEALLOCATE PREPARE profile_image_statement;
