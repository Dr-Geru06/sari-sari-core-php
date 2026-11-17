USE hard_sys_shop;

CREATE TABLE IF NOT EXISTS product_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO product_categories (name) VALUES
    ('Food'),
    ('Shoes'),
    ('Clothes'),
    ('Everyday Essentials');

SET @category_id_column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'category_id'
);

SET @category_id_update = IF(
    @category_id_column_exists = 0,
    'ALTER TABLE products ADD COLUMN category_id INT UNSIGNED NULL AFTER id',
    'SELECT ''Product classification column already exists'' AS migration_status'
);

PREPARE category_id_statement FROM @category_id_update;
EXECUTE category_id_statement;
DEALLOCATE PREPARE category_id_statement;

UPDATE products
SET category_id = (SELECT id FROM product_categories WHERE name = 'Everyday Essentials' LIMIT 1)
WHERE category_id IS NULL;

ALTER TABLE products MODIFY category_id INT UNSIGNED NOT NULL;

SET @category_index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND INDEX_NAME = 'idx_products_category_name'
);

SET @category_index_update = IF(
    @category_index_exists = 0,
    'ALTER TABLE products ADD INDEX idx_products_category_name (category_id, name)',
    'SELECT ''Product classification index already exists'' AS migration_status'
);

PREPARE category_index_statement FROM @category_index_update;
EXECUTE category_index_statement;
DEALLOCATE PREPARE category_index_statement;

SET @category_foreign_key_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND CONSTRAINT_NAME = 'fk_products_category'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @category_foreign_key_update = IF(
    @category_foreign_key_exists = 0,
    'ALTER TABLE products ADD CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES product_categories (id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT ''Product classification foreign key already exists'' AS migration_status'
);

PREPARE category_foreign_key_statement FROM @category_foreign_key_update;
EXECUTE category_foreign_key_statement;
DEALLOCATE PREPARE category_foreign_key_statement;
