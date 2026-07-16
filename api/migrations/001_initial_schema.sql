-- ================================================================
-- DUHN FRAGRANCES — MySQL Database Schema
-- Run this file once on your Hostinger MySQL database
-- ================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ── Users ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`              VARCHAR(120) NOT NULL,
  `email`             VARCHAR(191) NOT NULL UNIQUE,
  `phone`             VARCHAR(20),
  `password_hash`     VARCHAR(255) NOT NULL,
  `role`              ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Products ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `products` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug`         VARCHAR(191) NOT NULL UNIQUE,
  `name`         VARCHAR(191) NOT NULL,
  `inspired_by`  VARCHAR(255),
  `description`       TEXT,
  `short_description` TEXT DEFAULT NULL,
  `content_blocks`    LONGTEXT DEFAULT NULL,
  `compare_at_price`  DECIMAL(10,2) DEFAULT NULL,
  `top_notes`    TEXT COMMENT 'JSON array of note strings',
  `heart_notes`  TEXT COMMENT 'JSON array of note strings',
  `base_notes`   TEXT COMMENT 'JSON array of note strings',
  `size_ml`      TINYINT UNSIGNED NOT NULL DEFAULT 50,
  `price`        DECIMAL(10,2) NOT NULL DEFAULT 899.00,
  `currency`     VARCHAR(3) NOT NULL DEFAULT 'EGP',
  `stock_qty`    INT UNSIGNED NOT NULL DEFAULT 100,
  `views`        INT UNSIGNED NOT NULL DEFAULT 0,
  `sku`          VARCHAR(50) UNIQUE,
  `is_featured`  TINYINT(1) NOT NULL DEFAULT 0,
  `is_new_drop`  TINYINT(1) NOT NULL DEFAULT 0,
  `avg_rating`   DECIMAL(3,2) DEFAULT 0.00,
  `review_count` INT UNSIGNED DEFAULT 0,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Product Images ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_images` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id`  INT UNSIGNED NOT NULL,
  `image_url`   VARCHAR(500) NOT NULL,
  `sort_order`  TINYINT UNSIGNED DEFAULT 0,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Collections ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `collections` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug`            VARCHAR(191) NOT NULL UNIQUE,
  `name`            VARCHAR(191) NOT NULL,
  `description`     TEXT,
  `cover_image_url` VARCHAR(500),
  `sort_order`      TINYINT UNSIGNED DEFAULT 0,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Product ↔ Collection Pivot ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_collections` (
  `product_id`    INT UNSIGNED NOT NULL,
  `collection_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`product_id`, `collection_id`),
  FOREIGN KEY (`product_id`)    REFERENCES `products`(`id`)    ON DELETE CASCADE,
  FOREIGN KEY (`collection_id`) REFERENCES `collections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Reviews ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `reviews` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id`     INT UNSIGNED NOT NULL,
  `user_id`        INT UNSIGNED NULL,
  `reviewer_name`  VARCHAR(120) NOT NULL,
  `reviewer_email` VARCHAR(191),
  `rating`         TINYINT UNSIGNED NOT NULL,
  `body`           TEXT,
  `is_approved`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `chk_rating` CHECK (`rating` BETWEEN 1 AND 5),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Cart Sessions ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `carts` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT UNSIGNED NULL,
  `session_token` VARCHAR(255),
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Cart Items ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `cart_items` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cart_id`    INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity`   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`cart_id`)    REFERENCES `carts`(`id`)    ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Orders ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `orders` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_number`     VARCHAR(20) NOT NULL UNIQUE,
  `user_id`          INT UNSIGNED NULL,
  `customer_name`    VARCHAR(120) NOT NULL,
  `customer_email`   VARCHAR(191),
  `customer_phone`   VARCHAR(20) NOT NULL,
  `delivery_address` TEXT NOT NULL,
  `governorate`      VARCHAR(60) NOT NULL,
  `subtotal`         DECIMAL(10,2) NOT NULL,
  `delivery_fee`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total`            DECIMAL(10,2) NOT NULL,
  `payment_method`   ENUM('cod','card') NOT NULL DEFAULT 'cod',
  `status`           ENUM('pending','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `notes`            TEXT,
  `gps_lat`          DOUBLE NULL,
  `gps_lng`          DOUBLE NULL,
  `gps_label`        VARCHAR(255) NULL,
  `gps_map_url`      VARCHAR(500) NULL,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Order Items ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id`      INT UNSIGNED NOT NULL,
  `product_id`    INT UNSIGNED NULL,
  `product_name`  VARCHAR(191) NOT NULL,
  `product_price` DECIMAL(10,2) NOT NULL,
  `quantity`      SMALLINT UNSIGNED NOT NULL,
  `line_total`    DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Contact Messages ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(120) NOT NULL,
  `email`      VARCHAR(191) NOT NULL,
  `message`    TEXT NOT NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Newsletter ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email`         VARCHAR(191) NOT NULL UNIQUE,
  `promo_code`    VARCHAR(30)  DEFAULT NULL,
  `email_sent`    TINYINT(1)   NOT NULL DEFAULT 0,
  `subscribed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Password Resets ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `password_resets` (
  `email`      VARCHAR(191) NOT NULL PRIMARY KEY,
  `token`      VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Store Settings ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
  `key`        VARCHAR(100) NOT NULL PRIMARY KEY,
  `value`      TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Login Attempts (rate limiting) ────────────────────────────────
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `email`      VARCHAR(191),
  `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ip` (`ip_address`),
  INDEX `idx_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Promo Codes ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `promo_codes` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`        VARCHAR(50)  NOT NULL UNIQUE,
  `description` VARCHAR(255) DEFAULT NULL,
  `type`        ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `value`       DECIMAL(10,2) NOT NULL,
  `min_order`   DECIMAL(10,2) DEFAULT 0,
  `max_uses`    INT          DEFAULT NULL,
  `used_count`  INT          DEFAULT 0,
  `is_active`   TINYINT(1)   DEFAULT 1,
  `expires_at`  DATE         DEFAULT NULL,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Offers & Deals ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `offers` (
  `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`               VARCHAR(191) NOT NULL,
  `offer_type`         ENUM('bogo','bundle') NOT NULL DEFAULT 'bogo',
  `trigger_product_id` INT UNSIGNED NOT NULL,
  `trigger_qty`        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `free_product_id`    INT UNSIGNED NOT NULL,
  `free_qty`           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `badge_text`         VARCHAR(100) DEFAULT 'BUY 1 GET 1 FREE',
  `is_active`          TINYINT(1) DEFAULT 1,
  `starts_at`          DATETIME DEFAULT NULL,
  `ends_at`            DATETIME DEFAULT NULL,
  `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`trigger_product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`free_product_id`)    REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ================================================================
-- SEED DATA
-- ================================================================

-- Collections
INSERT IGNORE INTO `collections` (`slug`,`name`,`description`,`sort_order`) VALUES
('bestsellers',  'Bestsellers',          'Our top-selling fragrances',          1),
('date-night',   'Date Night',           'Fragrances for romantic evenings',    2),
('for-her',      'For Her',              'Women''s fragrance collection',       3),
('for-him',      'For Him',              'Men''s fragrance collection',         4),
('new-drops',    'NEW DROPS',            'Recently launched products',          5),
('office-fresh', 'Office Fresh',         'Professional, workplace-appropriate', 6),
('starboy',      'Starboy Collection',   'Bold, distinctive fragrances',        7),
('summer',       'Summer Collection',    'Light, seasonal scents',              8),
('universal',    'Universal MUST-HAVEs', 'Essential everyday fragrances',       9);

-- Store Settings
INSERT IGNORE INTO `settings` (`key`,`value`) VALUES
('announcement_text',    'BUY 2 GET 2 FREE'),
('announcement_enabled', '1'),
('delivery_fee',         '0'),
('promo_enabled',        '1'),
('promo_min_items',      '4'),
('store_name',           'DUHN FRAGRANCES'),
('store_email',          'info@duhnfragrances.com'),
('store_phone',          '+201157879622'),
('store_whatsapp',       '+201157879622'),
('nl_popup_enabled',     '1'),
('nl_popup_delay',       '1800'),
('nl_popup_eyebrow',     'SIGNUP FOR EMAILS'),
('nl_popup_title',       'GET 20% DISCOUNT SHIPPED TO YOUR INBOX'),
('nl_popup_desc',        'Subscribe to our newsletter and we will send you a 20% discount code today.'),
('nl_popup_btn_text',    'SUBSCRIBE'),
('delivery_info',        'Estimated delivery: <strong>2–5 business days</strong> across Egypt. <strong>Free delivery</strong> on all orders.'),
('return_info',          'Return within <strong>7 days</strong> of purchase. Items must be unused and in original packaging.'),
('shipping_policy',      'Free shipping on all orders across Egypt.\nOrders are processed within 1–2 business days.\nDelivery takes 2–5 business days depending on your location.\nFor returns: items must be unused, in original packaging, and returned within 7 days of receipt.');

-- Admin User
-- Email: mohamed@quadrocloud.net | Password: Admin123456
-- Change password immediately after first login via Admin → Profile
INSERT IGNORE INTO `users` (`name`,`email`,`password_hash`,`role`) VALUES
('DUHN Admin','mohamed@quadrocloud.net','$2y$12$nGRZGe1CvgY7ZGaXaHYpj.SV7ubfZduBBMU8Yto6sL.a6bWEJ3KmO','admin');

-- Sample Products (add your real products with images via admin panel)
INSERT IGNORE INTO `products` (`slug`,`name`,`inspired_by`,`description`,`top_notes`,`heart_notes`,`base_notes`,`sku`,`is_featured`,`is_new_drop`,`avg_rating`,`review_count`) VALUES
('euphoria','Euphoria','Imagination – Louis Vuitton',
 'A luminous and refined fragrance opening with citrus freshness. The heart delivers neroli and warm spice, while the base grounds everything with black tea and guaiac wood — quiet luxury at its finest.',
 '["Citron","Bergamot","Sicilian Orange"]','["Neroli","Ginger","Cinnamon"]','["Black Tea","Guaiac Wood","Frankincense"]',
 '2010',1,0,4.82,119),

('carnation','Carnation','Aventus – Creed',
 'A legendary and powerful fragrance that opens with a striking combination of pineapple, bergamot, and black currant. A refined heart of birch, jasmine, and patchouli creates iconic complexity. The base of musk, oakmoss, ambergris, and sandalwood leaves a trail of timeless prestige.',
 '["Pineapple","Bergamot","Black Currant"]','["Birch","Jasmine","Patchouli"]','["Musk","Oakmoss","Ambergris","Sandalwood"]',
 '2017',1,0,4.90,71),

('blue-essence','Blue Essence','Dylan Blue – Versace',
 'A bold and captivating fragrance opening with Calabrian bergamot, water notes, grapefruit, and fig leaf. The heart of ambroxan, black pepper, and patchouli builds rich depth. Base of incense, musk, and tonka delivers a warm commanding trail.',
 '["Calabrian Bergamot","Water Notes","Grapefruit","Fig Leaf"]','["Ambroxan","Black Pepper","Patchouli","Violet Leaf","Papyrus"]','["Incense","Musk","Tonka Bean","Saffron"]',
 '2020',1,0,5.00,1),

('chillwave','Chillwave','Pacific Chill – Louis Vuitton',
 'A fresh, breezy composition that captures the laid-back spirit of the Pacific coast. Crisp citrus top notes give way to a cool aquatic heart and a smooth woody base.',
 '["Bergamot","Lemon","Mandarin"]','["Aquatic Notes","Jasmine","Lily"]','["Sandalwood","Musk","Cedarwood"]',
 '2021',1,1,4.75,28),

('citranova','Citranova','Y Eau de Parfum – YSL',
 'A vibrant, modern masculine fragrance. Citrus freshness meets sage and geranium for an energetic heart, anchored by a warm base of ginger, cedar, and amberwood.',
 '["Apple","Bergamot","Ginger"]','["Sage","Geranium","White Tea"]','["Amberwood","Cedar","Vetiver"]',
 '2022',1,0,4.80,45),

('baked-vanilla','Baked Vanilla','Vanilla 28 – Kayali',
 'A warm, gourmand vanilla fragrance that feels like a hug. Rich baked vanilla layered with caramel, musk, and a touch of creamy tonka — irresistibly comforting.',
 '["Caramel","Bergamot","Mandarin"]','["Vanilla","Tonka Bean","Heliotrope"]','["Musk","Sandalwood","Amber"]',
 '2023',0,1,4.90,33),

('desire','Desire','Libre – YSL',
 'An audacious floral signature with lavender essence from Provence and orange blossom absolute from Morocco. A bold and free feminine spirit.',
 '["Lavender","Mandarin","Petitgrain"]','["Orange Blossom","Jasmine","Musk"]','["White Musk","Cedarwood","Sandalwood"]',
 '2024',0,1,4.85,52),

('wild-berry','Wild Berry','(Original)','A vibrant fruity explosion of wild berries, raspberry, and blackcurrant. Light and cheerful — the perfect daytime fragrance.',
 '["Wild Berries","Raspberry","Blackcurrant"]','["Peony","Rose","Lily of the Valley"]','["Musk","White Cedar","Sandalwood"]',
 '2025',0,1,4.70,19),

('obsidian','Obsidian','(Original)','A dark, brooding woody fragrance with deep oud, smoky incense, and black pepper. Intense and mysterious for those who leave a lasting impression.',
 '["Black Pepper","Cardamom","Saffron"]','["Oud","Leather","Iris"]','["Amber","Patchouli","Vetiver","Musk"]',
 '2026',1,0,4.88,64),

('gourmand-cafe','Gourmand Cafe','(Original)','A rich coffee and cocoa fragrance that evokes the warmth of a Parisian café. Sweet, roasted, and utterly addictive.',
 '["Coffee","Dark Cocoa","Cardamom"]','["Vanilla","Tonka Bean","Praline"]','["Sandalwood","Musk","Amber"]',
 '2027',1,0,4.92,87);
