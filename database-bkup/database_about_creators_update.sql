USE hard_sys_shop;

CREATE TABLE IF NOT EXISTS about_creators (
    slot_key VARCHAR(16) NOT NULL PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL DEFAULT '',
    profile_image_path VARCHAR(255) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO about_creators (slot_key) VALUES
    ('leader'),
    ('member_1'),
    ('member_2'),
    ('member_3'),
    ('member_4'),
    ('member_5');
