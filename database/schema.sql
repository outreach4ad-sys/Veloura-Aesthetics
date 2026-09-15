-- =====================================================================
-- Veloura Tec — Database Schema
-- Company: Optical Cargo
-- Engine : MySQL 5.7+ / MariaDB 10.3+  (InnoDB, utf8mb4)
--
-- Import via Hostinger phpMyAdmin:
--   1. Create the database and user in hPanel first.
--   2. Select the database, open the "Import" tab, upload this file.
--
-- This file is idempotent-safe on a fresh database only. Do NOT re-run
-- it on a populated database: it will drop existing data.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- admins
-- Dashboard users. Passwords are stored as password_hash() output only.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(120)  NOT NULL,
  `email`           VARCHAR(190)  NOT NULL,
  `password_hash`   VARCHAR(255)  NOT NULL,
  `role`            ENUM('owner','editor') NOT NULL DEFAULT 'editor',
  `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
  `failed_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`    DATETIME      NULL DEFAULT NULL,
  `last_login_at`   DATETIME      NULL DEFAULT NULL,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- categories
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(160)  NOT NULL,
  `slug`            VARCHAR(180)  NOT NULL,
  `description`     TEXT          NULL,
  `image`           VARCHAR(255)  NULL,
  `sort_order`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_published`    TINYINT(1)    NOT NULL DEFAULT 1,
  `seo_title`       VARCHAR(190)  NULL,
  `seo_description` VARCHAR(320)  NULL,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_visible` (`is_published`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- products
-- `price` is NULLABLE on purpose: quote-only products carry no price.
-- `price_mode` decides whether the storefront renders a price or a
-- "Request a Quote" call to action.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`       INT UNSIGNED  NOT NULL,
  `name`              VARCHAR(190)  NOT NULL,
  `slug`              VARCHAR(200)  NOT NULL,
  `short_description`  VARCHAR(400) NULL,
  `description`       MEDIUMTEXT    NULL,
  `price`             DECIMAL(10,2) NULL DEFAULT NULL,
  `currency`          CHAR(3)       NOT NULL DEFAULT 'USD',
  `price_mode`        ENUM('show','quote') NOT NULL DEFAULT 'quote',
  `main_image`        VARCHAR(255)  NULL,
  `main_image_alt`    VARCHAR(190)  NULL,
  `specs`             MEDIUMTEXT    NULL COMMENT 'JSON array of {label,value} pairs',
  `brand`             VARCHAR(120)  NULL,
  `origin_country`    VARCHAR(120)  NULL,
  `warranty`          VARCHAR(255)  NULL,
  `installation`      VARCHAR(255)  NULL,
  `training`          VARCHAR(255)  NULL,
  `support`           VARCHAR(255)  NULL,
  `is_featured`       TINYINT(1)    NOT NULL DEFAULT 0,
  `status`            ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `seo_title`         VARCHAR(190)  NULL,
  `seo_description`   VARCHAR(320)  NULL,
  `created_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_slug` (`slug`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_listing` (`status`, `is_featured`, `created_at`),
  KEY `idx_products_price` (`price`),
  KEY `idx_products_name` (`name`),
  FULLTEXT KEY `ft_products_search` (`name`, `short_description`, `brand`),
  CONSTRAINT `fk_products_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- product_images  (gallery; the main image lives on products.main_image)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `path`       VARCHAR(255) NOT NULL,
  `alt_text`   VARCHAR(190) NULL,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_images_order` (`product_id`, `sort_order`),
  CONSTRAINT `fk_product_images_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- product_videos
-- `video_type` = youtube | facebook | file
-- `source` holds the external URL, or the uploaded file path for 'file'.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `product_videos`;
CREATE TABLE `product_videos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `video_type` ENUM('youtube','facebook','file') NOT NULL DEFAULT 'youtube',
  `source`     VARCHAR(500) NOT NULL,
  `title`      VARCHAR(190) NULL,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_videos_order` (`product_id`, `sort_order`),
  CONSTRAINT `fk_product_videos_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- product_related  (self-referencing many-to-many)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `product_related`;
CREATE TABLE `product_related` (
  `product_id`         INT UNSIGNED NOT NULL,
  `related_product_id` INT UNSIGNED NOT NULL,
  `sort_order`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`product_id`, `related_product_id`),
  KEY `idx_product_related_target` (`related_product_id`),
  CONSTRAINT `fk_product_related_source`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_related_target`
    FOREIGN KEY (`related_product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- inquiries
-- `reference` is the customer-facing number, e.g. VT-2026-000123.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `inquiries`;
CREATE TABLE `inquiries` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`   VARCHAR(32)  NOT NULL,
  `full_name`   VARCHAR(160) NOT NULL,
  `country`     VARCHAR(120) NOT NULL,
  `city`        VARCHAR(120) NULL,
  `email`       VARCHAR(190) NULL,
  `whatsapp`    VARCHAR(40)  NOT NULL,
  `company`     VARCHAR(190) NULL,
  `notes`       TEXT         NULL,
  `status`      ENUM('new','contacted','quoted','completed','cancelled') NOT NULL DEFAULT 'new',
  `admin_notes` TEXT         NULL,
  `items_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `ip_address`  VARBINARY(16) NULL,
  `user_agent`  VARCHAR(255) NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inquiries_reference` (`reference`),
  KEY `idx_inquiries_status` (`status`, `created_at`),
  KEY `idx_inquiries_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- inquiry_items
-- Product name / URL / price are SNAPSHOTS: an inquiry must stay
-- readable after the catalog changes, so product_id is SET NULL on
-- delete rather than cascading the history away.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `inquiry_items`;
CREATE TABLE `inquiry_items` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inquiry_id`   INT UNSIGNED NOT NULL,
  `product_id`   INT UNSIGNED NULL DEFAULT NULL,
  `product_name` VARCHAR(190) NOT NULL,
  `product_url`  VARCHAR(400) NULL,
  `unit_price`   DECIMAL(10,2) NULL DEFAULT NULL,
  `currency`     CHAR(3)      NULL,
  `quantity`     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inquiry_items_inquiry` (`inquiry_id`),
  KEY `idx_inquiry_items_product` (`product_id`),
  CONSTRAINT `fk_inquiry_items_inquiry`
    FOREIGN KEY (`inquiry_id`) REFERENCES `inquiries` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_inquiry_items_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- site_settings  (key/value; loaded once per request and cached)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `site_settings`;
CREATE TABLE `site_settings` (
  `setting_key`   VARCHAR(80)  NOT NULL,
  `setting_value` TEXT         NULL,
  `setting_group` VARCHAR(40)  NOT NULL DEFAULT 'general',
  `setting_type`  ENUM('text','textarea','number','boolean','email','url','image') NOT NULL DEFAULT 'text',
  `label`         VARCHAR(160) NOT NULL,
  `sort_order`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`),
  KEY `idx_site_settings_group` (`setting_group`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- hero_slides
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `hero_slides`;
CREATE TABLE `hero_slides` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`                VARCHAR(190) NOT NULL,
  `subtitle`             VARCHAR(400) NULL,
  `image`                VARCHAR(255) NULL,
  `image_alt`            VARCHAR(190) NULL,
  `cta_primary_label`    VARCHAR(80)  NULL,
  `cta_primary_url`      VARCHAR(255) NULL,
  `cta_secondary_label`  VARCHAR(80)  NULL,
  `cta_secondary_url`    VARCHAR(255) NULL,
  `sort_order`           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_published`         TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hero_slides_visible` (`is_published`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED DATA
-- Values wrapped in [PLACEHOLDER: ...] are intentionally not real.
-- Replace them from Admin > Settings after deployment.
-- =====================================================================

INSERT INTO `site_settings`
  (`setting_key`, `setting_value`, `setting_group`, `setting_type`, `label`, `sort_order`) VALUES
  ('site_name',           'Veloura Tec',                                    'general',  'text',     'Site name',                 10),
  ('site_tagline',        'Professional Aesthetic Technology & Equipment',  'general',  'text',     'Tagline',                   20),
  ('company_name',        'Optical Cargo',                                  'general',  'text',     'Company name',              30),
  ('site_description',    'Professional aesthetic devices and equipment for clinics, beauty centers and wellness facilities.', 'general', 'textarea', 'Default meta description', 40),
  ('default_currency',    'USD',                                            'commerce', 'text',     'Default currency code',     10),
  ('currency_symbol',     '$',                                              'commerce', 'text',     'Currency symbol',           20),
  ('show_prices',         '1',                                              'commerce', 'boolean',  'Show prices site-wide',     30),
  ('products_per_page',   '12',                                             'commerce', 'number',   'Products per page',         40),
  ('inquiry_prefix',      'VT',                                             'commerce', 'text',     'Inquiry reference prefix',  50),
  ('whatsapp_number',     '[PLACEHOLDER: WhatsApp number, digits only incl. country code]', 'contact', 'text',     'WhatsApp number',      10),
  ('contact_email',       '[PLACEHOLDER: contact email]',                   'contact',  'email',    'Contact email',             20),
  ('contact_phone',       '[PLACEHOLDER: phone number]',                    'contact',  'text',     'Phone number',              30),
  ('contact_address',     '[PLACEHOLDER: business address]',                'contact',  'textarea', 'Address',                   40),
  ('business_hours',      '[PLACEHOLDER: business hours]',                  'contact',  'textarea', 'Business hours',            50),
  ('social_facebook',     '',                                               'social',   'url',      'Facebook URL',              10),
  ('social_instagram',    '',                                               'social',   'url',      'Instagram URL',             20),
  ('social_linkedin',     '',                                               'social',   'url',      'LinkedIn URL',              30),
  ('social_youtube',      '',                                               'social',   'url',      'YouTube URL',               40),
  ('shipping_info',       '[PLACEHOLDER: shipping terms — confirm destinations and lead times before publishing]', 'policy', 'textarea', 'Shipping information', 10),
  ('returns_info',        '[PLACEHOLDER: returns policy]',                  'policy',   'textarea', 'Returns information',       20),
  ('seo_default_og_image','',                                               'seo',      'image',    'Default social share image',10);

INSERT INTO `categories` (`name`, `slug`, `sort_order`, `is_published`) VALUES
  ('Hydrafacial',                       'hydrafacial',                        10, 1),
  ('Hair Removal',                      'hair-removal',                       20, 1),
  ('CO2 Laser',                         'co2-laser',                          30, 1),
  ('Nd:YAG Tattoo Removal',             'ndyag-tattoo-removal',               40, 1),
  ('Facial Products',                   'facial-products',                    50, 1),
  ('EMS Technology',                    'ems-technology',                     60, 1),
  ('Body Contouring & Weight Management','body-contouring-weight-management',  70, 1),
  ('Physiotherapy Equipment',           'physiotherapy-equipment',            80, 1),
  ('Microneedling',                     'microneedling',                      90, 1);

INSERT INTO `hero_slides`
  (`title`, `subtitle`, `cta_primary_label`, `cta_primary_url`, `cta_secondary_label`, `cta_secondary_url`, `sort_order`, `is_published`) VALUES
  ('Advanced Aesthetic Technology. Professional Results.',
   'Explore professional equipment and solutions for aesthetic clinics, beauty centers, and wellness professionals.',
   'Explore Equipment', '/shop.php', 'Request a Quote', '/inquiry.php', 10, 1);

-- Brand assets (logo uploaded to assets/img/logo.png by default)
INSERT INTO `site_settings`
  (`setting_key`, `setting_value`, `setting_group`, `setting_type`, `label`, `sort_order`) VALUES
  ('site_logo',      'assets/img/logo.png', 'general', 'image', 'Site logo',      5),
  ('site_logo_alt',  'Veloura Tec',         'general', 'text',  'Logo alt text',  6);
