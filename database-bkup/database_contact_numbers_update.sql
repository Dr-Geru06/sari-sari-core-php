USE hard_sys_shop;

SET @customer_contact_number_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'customers'
      AND COLUMN_NAME = 'contact_number'
);

SET @customer_contact_number_update = IF(
    @customer_contact_number_exists = 0,
    'ALTER TABLE customers ADD COLUMN contact_number VARCHAR(30) NOT NULL DEFAULT '''' AFTER email',
    'SELECT ''Customer contact number column already exists'' AS migration_status'
);

PREPARE customer_contact_number_statement FROM @customer_contact_number_update;
EXECUTE customer_contact_number_statement;
DEALLOCATE PREPARE customer_contact_number_statement;

SET @order_contact_number_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'customer_contact_number'
);

SET @order_contact_number_update = IF(
    @order_contact_number_exists = 0,
    'ALTER TABLE orders ADD COLUMN customer_contact_number VARCHAR(30) NOT NULL DEFAULT '''' AFTER customer_email',
    'SELECT ''Order contact number column already exists'' AS migration_status'
);

PREPARE order_contact_number_statement FROM @order_contact_number_update;
EXECUTE order_contact_number_statement;
DEALLOCATE PREPARE order_contact_number_statement;
