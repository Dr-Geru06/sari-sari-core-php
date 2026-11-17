USE hard_sys_shop;

UPDATE orders SET status = 'pending' WHERE status = 'new';
UPDATE orders SET status = 'ongoing' WHERE status = 'processing';
UPDATE orders SET status = 'delivered' WHERE status = 'fulfilled';

ALTER TABLE orders
    ALTER COLUMN status SET DEFAULT 'pending';
