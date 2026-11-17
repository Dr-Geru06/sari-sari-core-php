USE hard_sys_shop;

SET @admin_column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'customers'
      AND COLUMN_NAME = 'is_admin'
);

SET @admin_column_update = IF(
    @admin_column_exists = 0,
    'ALTER TABLE customers ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash',
    'SELECT ''Admin role column already exists'' AS migration_status'
);

PREPARE admin_column_statement FROM @admin_column_update;
EXECUTE admin_column_statement;
DEALLOCATE PREPARE admin_column_statement;
