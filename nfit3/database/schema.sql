-- ------------------------------------------------------------
-- NutremFit – Estrutura básica do banco de dados
-- Compatível com MySQL 5.7+ (utf8mb4_unicode_ci)
-- ------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `edua0932_nutremfit`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `edua0932_nutremfit`;

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- -----------------
-- Tabela: users
-- -----------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(120)     NOT NULL,
  `email`           VARCHAR(160)     NOT NULL,
  `phone_whatsapp`  VARCHAR(30)      DEFAULT NULL,
  `plan`            ENUM('essencial','performance','vip') NOT NULL DEFAULT 'essencial',
  `role`            ENUM('student','admin') NOT NULL DEFAULT 'student',
  `password_hash`   VARCHAR(255)     NOT NULL,
  `goal`            TEXT             NULL,
  `preferences`     JSON             NULL,
  `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at`   DATETIME         NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users`
(`id`,`name`,`email`,`phone_whatsapp`,`plan`,`role`,`password_hash`,`goal`,`preferences`,`created_at`)
VALUES
(1,'Marina Amancio','admin@nutremfit.com','+55 31 9488-9818','vip','admin',
 '$2y$10$gVe1wX46B/kEsEoeKZTTZuX63x0zDVRX0KW5d8ihbdN2Y9YD0POn6',
 'Liderar o acompanhamento autoral da plataforma.',
 JSON_OBJECT('notify_email', TRUE, 'notify_whatsapp', TRUE),
 '2025-01-01 12:00:00'),
(2,'Aluno Demo','demo@nutremfit.com',NULL,'performance','student',
 '$2y$10$KK.wdpnBjGIXDX3c6dQH8us/BJOdp8fc1wZwix04NjETL.12cK/Ta',
 'Emagrecer 6 kg mantendo rendimento no trabalho.',
 JSON_OBJECT('notify_email', TRUE, 'notify_whatsapp', TRUE),
 '2025-01-05 09:00:00');

-- -----------------
-- Tabela: plans (catálogo)
-- -----------------
DROP TABLE IF EXISTS `plans`;
CREATE TABLE `plans` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`         VARCHAR(50)      NOT NULL,
  `name`         VARCHAR(80)      NOT NULL,
  `description`  TEXT             NULL,
  `price_month`  DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  `billing_cycle`ENUM('monthly','quarterly','semiannual','yearly') NOT NULL DEFAULT 'monthly',
  `features`     JSON             NULL,
  `is_active`    TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_plans_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `plans`
(`slug`,`name`,`description`,`price_month`,`billing_cycle`,`features`)
VALUES
('essencial','Essencial','Plano alimentar autoral com ajustes mensais. Ideal para quem precisa de direcionamento contínuo.','297.00','monthly',
 JSON_ARRAY('Plano alimentar com substituições','Checklist semanal','Suporte por WhatsApp em 24h úteis')),
('performance','Performance','Nutrição + treino com revisões quinzenais filmadas.','397.00','monthly',
 JSON_ARRAY('Plano alimentar + treino','Vídeos de execução gravados pela Marina','Revisões quinzenais','Acesso completo à Área do Aluno')),
('vip','Black Marina','Acompanhamento premium com agendas semanais ao vivo e ajustes concierge.','649.00','monthly',
 JSON_ARRAY('Audio feedback semanal','Treinos custom em 72h','Suporte emergencial','Agendas individuais ao vivo'));

-- -----------------
-- Tabela: subscriptions
-- -----------------
DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `plan_id`       BIGINT UNSIGNED NOT NULL,
  `status`        ENUM('pending','active','paused','cancelled') NOT NULL DEFAULT 'pending',
  `started_at`    DATETIME         NULL,
  `expires_at`    DATETIME         NULL,
  `cancelled_at`  DATETIME         NULL,
  `meta`          JSON             NULL,
  `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_subscriptions_user` (`user_id`),
  KEY `fk_subscriptions_plan` (`plan_id`),
  CONSTRAINT `fk_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `subscriptions`
(`user_id`,`plan_id`,`status`,`started_at`,`expires_at`,`meta`)
VALUES
(2,2,'active','2025-01-05 09:00:00','2025-02-05 09:00:00',JSON_OBJECT('notes','Assinatura demonstrativa criada automaticamente.'));

-- -----------------
-- Tabela: payment_logs
-- -----------------
DROP TABLE IF EXISTS `payment_logs`;
CREATE TABLE `payment_logs` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subscription_id` BIGINT UNSIGNED NOT NULL,
  `provider`        ENUM('manual','mercadopago','paypal','stripe') NOT NULL DEFAULT 'manual',
  `provider_id`     VARCHAR(120)    NULL,
  `amount`          DECIMAL(10,2)   NOT NULL,
  `currency`        CHAR(3)         NOT NULL DEFAULT 'BRL',
  `status`          ENUM('pending','paid','refunded','failed') NOT NULL DEFAULT 'pending',
  `paid_at`         DATETIME        NULL,
  `payload`         JSON            NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_payment_subscription` (`subscription_id`),
  CONSTRAINT `fk_payment_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------
-- Tabela: checkins
-- -----------------
DROP TABLE IF EXISTS `checkins`;
CREATE TABLE `checkins` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `energy`        TINYINT UNSIGNED NOT NULL,
  `weight`        VARCHAR(40)      NOT NULL,
  `routine`       TINYINT UNSIGNED NOT NULL,
  `notes`         TEXT             NULL,
  `submitted_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_checkins_user` (`user_id`),
  CONSTRAINT `fk_checkins_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------
-- Tabela: internal_messages (Área do aluno)
-- -----------------
DROP TABLE IF EXISTS `internal_messages`;
CREATE TABLE `internal_messages` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `channel`      ENUM('internal','whatsapp','email') NOT NULL DEFAULT 'internal',
  `subject`      VARCHAR(140)    DEFAULT NULL,
  `message`      TEXT            NOT NULL,
  `status`       ENUM('open','answered','archived') NOT NULL DEFAULT 'open',
  `answered_at`  DATETIME        NULL,
  `answered_by`  BIGINT UNSIGNED NULL,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_messages_user` (`user_id`),
  CONSTRAINT `fk_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------
-- Tabela: materials (downloads/arquivos da Área)
-- -----------------
DROP TABLE IF EXISTS `materials`;
CREATE TABLE `materials` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category`     ENUM('nutricao','treino','extras') NOT NULL DEFAULT 'nutricao',
  `title`        VARCHAR(160)    NOT NULL,
  `description`  TEXT            NULL,
  `asset_type`   ENUM('pdf','link','video','spreadsheet') NOT NULL DEFAULT 'pdf',
  `asset_path`   VARCHAR(255)    NOT NULL,
  `tagline`      VARCHAR(120)    NULL,
  `is_external`  TINYINT(1)      NOT NULL DEFAULT 0,
  `is_active`    TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `materials`
(`category`,`title`,`description`,`asset_type`,`asset_path`,`tagline`,`is_external`)
VALUES
('nutricao','Plano do ciclo atual','Cardápio com substituições e orientações de horários.','pdf','storage/materials/plano-ciclo.pdf','Atualizado há 2 dias',0),
('treino','Treino semanal (academia)','Planilha com séries, repetições e cargas alvo.','pdf','storage/materials/treino-academia.pdf','Liberado toda segunda-feira',0),
('extras','Playlist de execução perfeita','YouTube com execuções filmadas pela Marina.','link','https://www.youtube.com/playlist?list=PLdemoNutremFit','Atualizado mensalmente',1);

SET foreign_key_checks = 1;
