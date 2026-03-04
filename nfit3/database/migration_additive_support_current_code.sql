USE `edua0932_nutremfit`;
SET NAMES utf8mb4;
SET time_zone = '+00:00';

DELIMITER $$

DROP PROCEDURE IF EXISTS nf_add_column_if_missing $$
CREATE PROCEDURE nf_add_column_if_missing(
    IN p_table VARCHAR(128),
    IN p_column VARCHAR(128),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql := CONCAT(
            'ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition
        );
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS nf_add_index_if_missing $$
CREATE PROCEDURE nf_add_index_if_missing(
    IN p_table VARCHAR(128),
    IN p_index VARCHAR(128),
    IN p_ddl TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND INDEX_NAME = p_index
    ) THEN
        SET @sql := CONCAT('ALTER TABLE `', p_table, '` ', p_ddl);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;


CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(160) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `phone_whatsapp` VARCHAR(30) NULL,
  `plan` VARCHAR(50) NOT NULL DEFAULT 'essencial',
  `role` VARCHAR(20) NOT NULL DEFAULT 'student',
  `password_hash` VARCHAR(255) NOT NULL,
  `goal` TEXT NULL,
  `preferences` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plans` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(50) NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `description` TEXT NULL,
  `price_month` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `billing_cycle` VARCHAR(20) NOT NULL DEFAULT 'monthly',
  `features` JSON NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `plan_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `started_at` DATETIME NULL,
  `expires_at` DATETIME NULL,
  `cancelled_at` DATETIME NULL,
  `meta` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subscription_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(30) NOT NULL DEFAULT 'manual',
  `provider_id` VARCHAR(120) NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` CHAR(3) NOT NULL DEFAULT 'BRL',
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `paid_at` DATETIME NULL,
  `payload` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `checkins` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `energy` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `weight` VARCHAR(40) NOT NULL DEFAULT '',
  `routine` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `notes` TEXT NULL,
  `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `internal_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `channel` VARCHAR(20) NOT NULL DEFAULT 'internal',
  `subject` VARCHAR(140) NULL,
  `message` TEXT NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'open',
  `answered_at` DATETIME NULL,
  `answered_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `training_plans` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(160) NOT NULL,
  `instructions` TEXT NULL,
  `exercises` JSON NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `training_completions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `completed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plan_files` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `mime` VARCHAR(120) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL,
  `data` LONGBLOB NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `exercicios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome_exercicio` VARCHAR(255) NOT NULL,
  `link` TEXT NULL,
  `grupo_muscular` VARCHAR(80) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `materials` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(20) NOT NULL DEFAULT 'nutricao',
  `title` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `asset_type` VARCHAR(20) NOT NULL DEFAULT 'pdf',
  `asset_path` VARCHAR(255) NOT NULL,
  `tagline` VARCHAR(120) NULL,
  `is_external` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CALL nf_add_column_if_missing('users', 'name', CONCAT('VARCHAR(120) NOT NULL DEFAULT ', QUOTE('')));
CALL nf_add_column_if_missing('users', 'email', CONCAT('VARCHAR(160) NOT NULL DEFAULT ', QUOTE('')));
CALL nf_add_column_if_missing('users', 'phone', 'VARCHAR(30) NULL');
CALL nf_add_column_if_missing('users', 'phone_whatsapp', 'VARCHAR(30) NULL');
CALL nf_add_column_if_missing('users', 'plan', 'VARCHAR(50) NOT NULL DEFAULT ''essencial''');
CALL nf_add_column_if_missing('users', 'role', 'VARCHAR(20) NOT NULL DEFAULT ''student''');
CALL nf_add_column_if_missing('users', 'password_hash', CONCAT('VARCHAR(255) NOT NULL DEFAULT ', QUOTE('')));
CALL nf_add_column_if_missing('users', 'goal', 'TEXT NULL');
CALL nf_add_column_if_missing('users', 'preferences', 'JSON NULL');
CALL nf_add_column_if_missing('users', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL nf_add_column_if_missing('users', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
CALL nf_add_column_if_missing('users', 'last_login_at', 'DATETIME NULL');

CALL nf_add_column_if_missing('plans', 'slug', CONCAT('VARCHAR(50) NOT NULL DEFAULT ', QUOTE('')));
CALL nf_add_column_if_missing('plans', 'name', CONCAT('VARCHAR(80) NOT NULL DEFAULT ', QUOTE('')));
CALL nf_add_column_if_missing('plans', 'description', 'TEXT NULL');
CALL nf_add_column_if_missing('plans', 'price_month', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
CALL nf_add_column_if_missing('plans', 'billing_cycle', 'VARCHAR(20) NOT NULL DEFAULT ''monthly''');
CALL nf_add_column_if_missing('plans', 'features', 'JSON NULL');
CALL nf_add_column_if_missing('plans', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
CALL nf_add_column_if_missing('plans', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

CALL nf_add_column_if_missing('subscriptions', 'user_id', 'BIGINT UNSIGNED NULL');
CALL nf_add_column_if_missing('subscriptions', 'plan_id', 'BIGINT UNSIGNED NULL');
CALL nf_add_column_if_missing('subscriptions', 'status', 'VARCHAR(20) NOT NULL DEFAULT ''pending''');
CALL nf_add_column_if_missing('subscriptions', 'started_at', 'DATETIME NULL');
CALL nf_add_column_if_missing('subscriptions', 'expires_at', 'DATETIME NULL');
CALL nf_add_column_if_missing('subscriptions', 'cancelled_at', 'DATETIME NULL');
CALL nf_add_column_if_missing('subscriptions', 'meta', 'JSON NULL');
CALL nf_add_column_if_missing('subscriptions', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL nf_add_column_if_missing('subscriptions', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

CALL nf_add_column_if_missing('payment_logs', 'subscription_id', 'BIGINT UNSIGNED NULL');
CALL nf_add_column_if_missing('payment_logs', 'provider', 'VARCHAR(30) NOT NULL DEFAULT ''manual''');
CALL nf_add_column_if_missing('payment_logs', 'provider_id', 'VARCHAR(120) NULL');
CALL nf_add_column_if_missing('payment_logs', 'amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
CALL nf_add_column_if_missing('payment_logs', 'currency', 'CHAR(3) NOT NULL DEFAULT ''BRL''');
CALL nf_add_column_if_missing('payment_logs', 'status', 'VARCHAR(20) NOT NULL DEFAULT ''pending''');
CALL nf_add_column_if_missing('payment_logs', 'paid_at', 'DATETIME NULL');
CALL nf_add_column_if_missing('payment_logs', 'payload', 'JSON NULL');
CALL nf_add_column_if_missing('payment_logs', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

CALL nf_add_column_if_missing('checkins', 'user_id', 'BIGINT UNSIGNED NULL');
CALL nf_add_column_if_missing('checkins', 'energy', 'TINYINT UNSIGNED NOT NULL DEFAULT 0');
CALL nf_add_column_if_missing('checkins', 'weight', CONCAT('VARCHAR(40) NOT NULL DEFAULT ', QUOTE('')));
CALL nf_add_column_if_missing('checkins', 'routine', 'TINYINT UNSIGNED NOT NULL DEFAULT 0');
CALL nf_add_column_if_missing('checkins', 'notes', 'TEXT NULL');
CALL nf_add_column_if_missing('checkins', 'submitted_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

CALL nf_add_column_if_missing('internal_messages', 'user_id', 'BIGINT UNSIGNED NULL');
CALL nf_add_column_if_missing('internal_messages', 'channel', 'VARCHAR(20) NOT NULL DEFAULT ''internal''');
CALL nf_add_column_if_missing('internal_messages', 'subject', 'VARCHAR(140) NULL');
CALL nf_add_column_if_missing('internal_messages', 'message', 'TEXT NULL');
CALL nf_add_column_if_missing('internal_messages', 'status', 'VARCHAR(20) NOT NULL DEFAULT ''open''');
CALL nf_add_column_if_missing('internal_messages', 'answered_at', 'DATETIME NULL');
CALL nf_add_column_if_missing('internal_messages', 'answered_by', 'BIGINT UNSIGNED NULL');
CALL nf_add_column_if_missing('internal_messages', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

CALL nf_add_column_if_missing('training_plans', 'user_id', 'BIGINT UNSIGNED NULL');
CALL nf_add_column_if_missing('training_plans', 'title', 'VARCHAR(160) NOT NULL DEFAULT ''Treino do aluno''');
CALL nf_add_column_if_missing('training_plans', 'instructions', 'TEXT NULL');
CALL nf_add_column_if_missing('training_plans', 'exercises', 'JSON NULL');
CALL nf_add_column_if_missing('training_plans', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL nf_add_column_if_missing('training_plans', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

CALL nf_add_column_if_missing('training_completions', 'user_id', 'BIGINT UNSIGNED NULL');
CALL nf_add_column_if_missing('training_completions', 'completed_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

CALL nf_add_column_if_missing('plan_files', 'user_id', 'BIGINT UNSIGNED NULL');
CALL nf_add_column_if_missing('plan_files', 'filename', CONCAT('VARCHAR(255) NOT NULL DEFAULT ', QUOTE('')));
CALL nf_add_column_if_missing('plan_files', 'mime', 'VARCHAR(120) NOT NULL DEFAULT ''application/pdf''');
CALL nf_add_column_if_missing('plan_files', 'file_size', 'INT UNSIGNED NOT NULL DEFAULT 0');
CALL nf_add_column_if_missing('plan_files', 'data', 'LONGBLOB NULL');
CALL nf_add_column_if_missing('plan_files', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

CALL nf_add_column_if_missing('exercicios', 'nome_exercicio', CONCAT('VARCHAR(255) NOT NULL DEFAULT ', QUOTE('')));
CALL nf_add_column_if_missing('exercicios', 'link', 'TEXT NULL');
CALL nf_add_column_if_missing('exercicios', 'grupo_muscular', 'VARCHAR(80) NULL');

CALL nf_add_column_if_missing('materials', 'category', 'VARCHAR(20) NOT NULL DEFAULT ''nutricao''');
CALL nf_add_column_if_missing('materials', 'title', CONCAT('VARCHAR(160) NOT NULL DEFAULT ', QUOTE('')));
CALL nf_add_column_if_missing('materials', 'description', 'TEXT NULL');
CALL nf_add_column_if_missing('materials', 'asset_type', 'VARCHAR(20) NOT NULL DEFAULT ''pdf''');
CALL nf_add_column_if_missing('materials', 'asset_path', CONCAT('VARCHAR(255) NOT NULL DEFAULT ', QUOTE('')));
CALL nf_add_column_if_missing('materials', 'tagline', 'VARCHAR(120) NULL');
CALL nf_add_column_if_missing('materials', 'is_external', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL nf_add_column_if_missing('materials', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
CALL nf_add_column_if_missing('materials', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL nf_add_column_if_missing('materials', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');


CALL nf_add_index_if_missing('users', 'uniq_users_email', 'ADD UNIQUE KEY `uniq_users_email` (`email`)');

CALL nf_add_index_if_missing('plans', 'uniq_plans_slug', 'ADD UNIQUE KEY `uniq_plans_slug` (`slug`)');

CALL nf_add_index_if_missing('subscriptions', 'fk_subscriptions_user', 'ADD KEY `fk_subscriptions_user` (`user_id`)');
CALL nf_add_index_if_missing('subscriptions', 'fk_subscriptions_plan', 'ADD KEY `fk_subscriptions_plan` (`plan_id`)');

CALL nf_add_index_if_missing('payment_logs', 'fk_payment_subscription', 'ADD KEY `fk_payment_subscription` (`subscription_id`)');

CALL nf_add_index_if_missing('checkins', 'fk_checkins_user', 'ADD KEY `fk_checkins_user` (`user_id`)');

CALL nf_add_index_if_missing('internal_messages', 'fk_messages_user', 'ADD KEY `fk_messages_user` (`user_id`)');

CALL nf_add_index_if_missing('training_plans', 'uniq_training_user', 'ADD UNIQUE KEY `uniq_training_user` (`user_id`)');
CALL nf_add_index_if_missing('training_plans', 'fk_training_plans_user', 'ADD KEY `fk_training_plans_user` (`user_id`)');

CALL nf_add_index_if_missing('training_completions', 'idx_tc_user_date', 'ADD KEY `idx_tc_user_date` (`user_id`,`completed_at`)');

CALL nf_add_index_if_missing('plan_files', 'fk_plan_files_user', 'ADD KEY `fk_plan_files_user` (`user_id`)');

CALL nf_add_index_if_missing('exercicios', 'idx_exercicios_nome', 'ADD KEY `idx_exercicios_nome` (`nome_exercicio`)');

CALL nf_add_index_if_missing('materials', 'idx_materials_category', 'ADD KEY `idx_materials_category` (`category`)');


DROP PROCEDURE IF EXISTS nf_add_column_if_missing;
DROP PROCEDURE IF EXISTS nf_add_index_if_missing;
