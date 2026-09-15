-- =====================================================================
-- Veloura Tec — Migration 001: email subsystem + customer accounts
--
-- Run this ONCE on an existing database (fresh installs get these tables
-- from schema.sql automatically). Import via phpMyAdmin, or the installer
-- will pick them up from schema.sql on a new database.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- customers — website accounts (separate from admins)
-- Accounts verify their email with a 6-digit code before they are active.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(160)  NOT NULL,
  `email`           VARCHAR(190)  NOT NULL,
  `password_hash`   VARCHAR(255)  NOT NULL,
  `phone`           VARCHAR(40)   NULL,
  `country`         VARCHAR(120)  NULL,
  `company`         VARCHAR(190)  NULL,
  `is_verified`     TINYINT(1)    NOT NULL DEFAULT 0,
  `verify_code`     CHAR(6)       NULL DEFAULT NULL,
  `verify_expires`  DATETIME      NULL DEFAULT NULL,
  `verify_sent_at`  DATETIME      NULL DEFAULT NULL,
  `verify_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `failed_logins`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`    DATETIME      NULL DEFAULT NULL,
  `last_login_at`   DATETIME      NULL DEFAULT NULL,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- email_log — a record of every email the site attempts to send
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `to_email`   VARCHAR(190) NOT NULL,
  `subject`    VARCHAR(255) NOT NULL,
  `template`   VARCHAR(60)  NULL,
  `transport`  VARCHAR(20)  NULL,
  `status`     ENUM('sent','failed','logged') NOT NULL DEFAULT 'logged',
  `error`      VARCHAR(255) NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- inquiry_replies — replies an admin sends to a customer by email,
-- logged so the conversation history stays in the dashboard
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inquiry_replies` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inquiry_id` INT UNSIGNED NOT NULL,
  `admin_id`   INT UNSIGNED NULL DEFAULT NULL,
  `subject`    VARCHAR(255) NOT NULL,
  `body`       TEXT         NOT NULL,
  `status`     ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  `error`      VARCHAR(255) NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inquiry_replies_inquiry` (`inquiry_id`),
  CONSTRAINT `fk_inquiry_replies_inquiry`
    FOREIGN KEY (`inquiry_id`) REFERENCES `inquiries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_inquiry_replies_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Email + accounts settings (INSERT IGNORE keeps existing values safe)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `site_settings`
  (`setting_key`, `setting_value`, `setting_group`, `setting_type`, `label`, `sort_order`) VALUES
  ('mail_transport',   'log',                        'mail', 'text',     'Email method: log, smtp or resend', 10),
  ('mail_from_email',  '[PLACEHOLDER: noreply@your-domain]', 'mail', 'email', 'From address (e.g. noreply@your-domain)', 20),
  ('mail_from_name',   'Veloura Tec',                'mail', 'text',     'From name',                          30),
  ('mail_admin_notify','1',                          'mail', 'boolean',  'Email me when a new inquiry arrives', 40),
  ('smtp_host',        '',                           'mail', 'text',     'SMTP host (e.g. smtp.hostinger.com)', 50),
  ('smtp_port',        '465',                        'mail', 'number',   'SMTP port (465 for SSL, 587 for TLS)', 60),
  ('smtp_security',    'ssl',                        'mail', 'text',     'SMTP security: ssl, tls or none',    70),
  ('smtp_user',        '',                           'mail', 'text',     'SMTP username (the full email)',     80),
  ('smtp_pass',        '',                           'mail', 'text',     'SMTP password',                      90),
  ('resend_api_key',   '',                           'mail', 'text',     'Resend API key (if using Resend)',  100),
  ('accounts_enabled', '0',                          'mail', 'boolean',  'Allow visitors to create accounts', 110);
