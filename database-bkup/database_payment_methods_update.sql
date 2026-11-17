USE hard_sys_shop;

ALTER TABLE orders
    ADD COLUMN payment_method VARCHAR(32) NOT NULL DEFAULT 'cod' AFTER shipping_address;
