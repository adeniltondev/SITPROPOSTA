-- =============================================================
-- FORMA4 Imobiliária – Sistema de Formulários Dinâmicos
-- Script de criação do banco de dados
-- MySQL 5.7+ | utf8mb4
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------
-- Tabela: users
-- -----------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)     NOT NULL,
  `email`      VARCHAR(150)     NOT NULL,
  `password`   VARCHAR(255)     NOT NULL,
  `role`       ENUM('admin','user') NOT NULL DEFAULT 'admin',
  `is_active`  TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------
-- Tabela: forms
-- -----------------------------------------------
DROP TABLE IF EXISTS `forms`;
CREATE TABLE `forms` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`        VARCHAR(200) NOT NULL,
  `slug`         VARCHAR(200) NOT NULL,
  `description`  TEXT,
  `fields`       LONGTEXT     NOT NULL COMMENT 'JSON array de campos',
  `pdf_template` VARCHAR(50)  NOT NULL DEFAULT 'default' COMMENT 'default | authorization',
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`   INT UNSIGNED,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_forms_slug` (`slug`),
  KEY `fk_forms_created_by` (`created_by`),
  CONSTRAINT `fk_forms_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------
-- Tabela: submissions
-- -----------------------------------------------
DROP TABLE IF EXISTS `submissions`;
CREATE TABLE `submissions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `form_id`    INT UNSIGNED NOT NULL,
  `data`       LONGTEXT     NOT NULL COMMENT 'JSON com os dados preenchidos',
  `pdf_path`   VARCHAR(500) DEFAULT NULL,
  `email_sent` TINYINT(1)   NOT NULL DEFAULT 0,
  `ip_address` VARCHAR(45)  DEFAULT NULL,
  `city`       VARCHAR(100) DEFAULT NULL,
  `state`      VARCHAR(100) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_submissions_form` (`form_id`),
  CONSTRAINT `fk_submissions_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------
-- Tabela: settings
-- -----------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_name`   VARCHAR(100) NOT NULL,
  `value`      TEXT,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------
-- Dados padrão: settings
-- -----------------------------------------------
INSERT INTO `settings` (`key_name`, `value`) VALUES
('app_name',         'Forma4 Imobiliária'),
('app_url',          'http://localhost'),
('primary_color',    '#2563EB'),
('logo_path',        ''),
('email_recipient',  ''),
('smtp_host',        'smtp.hostinger.com'),
('smtp_port',        '465'),
('smtp_user',        ''),
('smtp_pass',        ''),
('smtp_from_name',   'Forma4 Imobiliária'),
('smtp_from_email',  ''),
('smtp_secure',      'ssl');

-- -----------------------------------------------
-- Formulário padrão pré-cadastrado
-- Inserido via install.php após criação do usuário
-- (ver install.php para o INSERT completo com created_by)
-- -----------------------------------------------
