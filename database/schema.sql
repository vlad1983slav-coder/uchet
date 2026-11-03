-- ============================================================================
--  UCHET — система складского учёта в стиле «1С:Предприятие»
--  Схема БД: MySQL 8.0 / MariaDB 10.6+, InnoDB, utf8mb4
--  Запуск:  mysql -u root -p < database/schema.sql
--           mysql -u root -p < database/seeds.sql   (тестовые данные)
-- ============================================================================

DROP DATABASE IF EXISTS `uchet`;
CREATE DATABASE `uchet`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `uchet`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
--  0. СИСТЕМНЫЕ ТАБЛИЦЫ
-- ============================================================================

-- Роли пользователей (admin, кладовщик, менеджер, гость)
CREATE TABLE `roles` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(32)  NOT NULL COMMENT 'admin | storekeeper | manager | guest',
  `name`        VARCHAR(100) NOT NULL,
  `permissions` JSON         NULL     COMMENT '{"documents":["post","unpost",...], "catalogs":["read",...]}',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`  TIMESTAMP    NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Роли пользователей';

-- Пользователи системы
CREATE TABLE `users` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `login`         VARCHAR(64)   NOT NULL,
  `email`         VARCHAR(255)  NULL,
  `password_hash` VARCHAR(255)  NOT NULL COMMENT 'password_hash(..., PASSWORD_ARGON2ID)',
  `full_name`     VARCHAR(255)  NOT NULL,
  `role_id`       TINYINT UNSIGNED NOT NULL,
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `last_login_at` DATETIME      NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`    TIMESTAMP     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_login` (`login`),
  KEY `ix_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Пользователи';

-- Глобальные настройки системы (регистр сведений "Настройки")
CREATE TABLE `settings` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key`  VARCHAR(64)  NOT NULL,
  `value`        VARCHAR(255) NOT NULL DEFAULT '',
  `description`  VARCHAR(255) NULL,
  `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Настройки: stock_method=FIFO|AVG, closed_period_date, org_name, ...';

-- Счётчики нумерации документов (префикс + год)
CREATE TABLE `document_counters` (
  `prefix`      VARCHAR(8)       NOT NULL COMMENT 'ПН, РН, ПМ, ИНВ, ВП, ВК',
  `year`        SMALLINT UNSIGNED NOT NULL,
  `last_number` INT UNSIGNED     NOT NULL DEFAULT 0,
  PRIMARY KEY (`prefix`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Нумерация документов вида ПН-000001';

-- Аудит: история изменений всех документов и справочников
CREATE TABLE `audit_log` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED    NULL,
  `entity_type` VARCHAR(64)     NOT NULL COMMENT 'таблица/тип сущности, напр. documents_receipts',
  `entity_id`   BIGINT UNSIGNED NOT NULL,
  `action`      VARCHAR(32)     NOT NULL COMMENT 'create | update | post | unpost | delete',
  `old_data`    JSON            NULL,
  `new_data`    JSON            NULL,
  `ip`          VARCHAR(45)     NULL,
  `user_agent`  VARCHAR(255)    NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_audit_entity` (`entity_type`, `entity_id`),
  KEY `ix_audit_user` (`user_id`),
  KEY `ix_audit_date` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Журнал аудита изменений';

-- ============================================================================
--  1. СПРАВОЧНИКИ (catalogs_*)
-- ============================================================================

-- Единицы измерения (код по ОКЕИ, напр. 796 = штука)
CREATE TABLE `catalogs_units` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(16)     NOT NULL,
  `name`        VARCHAR(100)    NOT NULL,
  `coefficient` DECIMAL(15,6)   NOT NULL DEFAULT 1.000000 COMMENT 'Коэффициент пересчёта в базовую единицу',
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`  TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_units_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Единицы измерения';

-- Группы (категории) номенклатуры, иерархия
CREATE TABLE `catalogs_categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`  INT UNSIGNED NULL,
  `code`       VARCHAR(32)  NOT NULL,
  `name`       VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_code` (`code`),
  KEY `ix_cat_parent` (`parent_id`),
  CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `catalogs_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Категории номенклатуры';

-- Номенклатура (товары)
CREATE TABLE `catalogs_nomenclature` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article`        VARCHAR(64)     NOT NULL COMMENT 'Артикул',
  `name`           VARCHAR(500)    NOT NULL,
  `unit_id`        INT UNSIGNED    NOT NULL,
  `category_id`    INT UNSIGNED    NULL,
  `vat_rate`       DECIMAL(5,2)    NOT NULL DEFAULT 20.00 COMMENT 'Ставка НДС, %',
  `purchase_price` DECIMAL(15,2)   NOT NULL DEFAULT 0.00 COMMENT 'Учётная (закупочная) цена',
  `min_stock`      DECIMAL(15,3)   NOT NULL DEFAULT 0.000 COMMENT 'Минимальный остаток (для контроля)',
  `is_service`     TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Услуга (без складского учёта)',
  `description`    TEXT            NULL,
  `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nom_article` (`article`),
  KEY `ix_nom_name` (`name`(191)),
  KEY `ix_nom_category` (`category_id`),
  KEY `ix_nom_unit` (`unit_id`),
  CONSTRAINT `fk_nom_unit`     FOREIGN KEY (`unit_id`)     REFERENCES `catalogs_units` (`id`),
  CONSTRAINT `fk_nom_category` FOREIGN KEY (`category_id`) REFERENCES `catalogs_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Номенклатура';

-- Склады
CREATE TABLE `catalogs_warehouses` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(16)  NOT NULL,
  `name`        VARCHAR(255) NOT NULL,
  `address`     VARCHAR(500) NULL,
  `mol_user_id` INT UNSIGNED NULL COMMENT 'МОЛ — материально ответственное лицо',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`  TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wh_code` (`code`),
  KEY `ix_wh_mol` (`mol_user_id`),
  CONSTRAINT `fk_wh_mol` FOREIGN KEY (`mol_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Склады';

-- Контрагенты
CREATE TABLE `catalogs_counterparties` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `inn`        VARCHAR(12)   NOT NULL DEFAULT '',
  `kpp`        VARCHAR(9)    NOT NULL DEFAULT '',
  `name`       VARCHAR(500)  NOT NULL,
  `type`       ENUM('supplier','customer','both') NOT NULL DEFAULT 'both' COMMENT 'поставщик / покупатель',
  `phone`      VARCHAR(64)   NULL,
  `email`      VARCHAR(255)  NULL,
  `address`    VARCHAR(500)  NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_cp_inn` (`inn`),
  KEY `ix_cp_name` (`name`(191)),
  KEY `ix_cp_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Контрагенты';

-- ============================================================================
--  2. ПАРТИИ (основание партионного учёта)
-- ============================================================================

CREATE TABLE `stock_parties` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomenclature_id` BIGINT UNSIGNED NOT NULL,
  `source_type`     ENUM('opening','receipt','return_customer','inventory_surplus')
                    NOT NULL COMMENT 'откуда партия: начальные остатки / ПН / возврат от покупателя / оприходование излишков',
  `source_id`       BIGINT UNSIGNED NULL COMMENT 'id документа-источника',
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_party_nom` (`nomenclature_id`),
  KEY `ix_party_source` (`source_type`, `source_id`),
  CONSTRAINT `fk_party_nom` FOREIGN KEY (`nomenclature_id`) REFERENCES `catalogs_nomenclature` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Партии товаров (документы-основания)';

-- ============================================================================
--  3. ДОКУМЕНТЫ: шапки (documents_*) и табличные части (documents_*_lines)
-- ============================================================================

-- 3.1. Приходная накладная (ПН)
CREATE TABLE `documents_receipts` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `number`          VARCHAR(20)   NOT NULL COMMENT 'ПН-000001',
  `prefix`          VARCHAR(8)    NOT NULL DEFAULT 'ПН',
  `doc_date`        DATETIME      NOT NULL,
  `warehouse_id`    INT UNSIGNED  NOT NULL,
  `counterparty_id` INT UNSIGNED  NOT NULL,
  `status`          ENUM('draft','posted','error') NOT NULL DEFAULT 'draft',
  `total_quantity`  DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  `total_amount`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `comment`         TEXT          NULL,
  `posted_at`       DATETIME      NULL,
  `posted_by`       INT UNSIGNED  NULL,
  `created_by`      INT UNSIGNED  NOT NULL,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      TIMESTAMP     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_receipts_number` (`number`),
  KEY `ix_receipts_date` (`doc_date`),
  KEY `ix_receipts_warehouse` (`warehouse_id`),
  KEY `ix_receipts_counterparty` (`counterparty_id`),
  KEY `ix_receipts_status` (`status`),
  KEY `ix_receipts_posted_by` (`posted_by`),
  KEY `ix_receipts_created_by` (`created_by`),
  CONSTRAINT `fk_receipts_warehouse`    FOREIGN KEY (`warehouse_id`)    REFERENCES `catalogs_warehouses` (`id`),
  CONSTRAINT `fk_receipts_counterparty` FOREIGN KEY (`counterparty_id`) REFERENCES `catalogs_counterparties` (`id`),
  CONSTRAINT `fk_receipts_posted_by`    FOREIGN KEY (`posted_by`)       REFERENCES `users` (`id`),
  CONSTRAINT `fk_receipts_created_by`   FOREIGN KEY (`created_by`)      REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Приходные накладные (шапка)';

CREATE TABLE `documents_receipts_lines` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`     BIGINT UNSIGNED NOT NULL,
  `line_no`         INT UNSIGNED    NOT NULL DEFAULT 1,
  `nomenclature_id` BIGINT UNSIGNED NOT NULL,
  `quantity`        DECIMAL(15,3)   NOT NULL,
  `price`           DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `vat_rate`        DECIMAL(5,2)    NOT NULL DEFAULT 20.00,
  `amount`          DECIMAL(15,2)   GENERATED ALWAYS AS (ROUND(`quantity` * `price`, 2)) STORED,
  `vat_amount`      DECIMAL(15,2)   GENERATED ALWAYS AS (ROUND(`quantity` * `price` * `vat_rate` / 100, 2)) STORED,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_rl_document` (`document_id`),
  KEY `ix_rl_nomenclature` (`nomenclature_id`),
  CONSTRAINT `fk_rl_document`     FOREIGN KEY (`document_id`)     REFERENCES `documents_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rl_nomenclature` FOREIGN KEY (`nomenclature_id`) REFERENCES `catalogs_nomenclature` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Приходные накладные (табличная часть)';

-- 3.2. Расходная накладная (РН)
CREATE TABLE `documents_issues` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `number`          VARCHAR(20)   NOT NULL COMMENT 'РН-000001',
  `prefix`          VARCHAR(8)    NOT NULL DEFAULT 'РН',
  `doc_date`        DATETIME      NOT NULL,
  `warehouse_id`    INT UNSIGNED  NOT NULL,
  `counterparty_id` INT UNSIGNED  NOT NULL,
  `status`          ENUM('draft','posted','error') NOT NULL DEFAULT 'draft',
  `total_quantity`  DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  `total_amount`    DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Сумма по ценам списания (себестоимость) или продажи',
  `comment`         TEXT          NULL,
  `posted_at`       DATETIME      NULL,
  `posted_by`       INT UNSIGNED  NULL,
  `created_by`      INT UNSIGNED  NOT NULL,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      TIMESTAMP     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_issues_number` (`number`),
  KEY `ix_issues_date` (`doc_date`),
  KEY `ix_issues_warehouse` (`warehouse_id`),
  KEY `ix_issues_counterparty` (`counterparty_id`),
  KEY `ix_issues_status` (`status`),
  KEY `ix_issues_posted_by` (`posted_by`),
  KEY `ix_issues_created_by` (`created_by`),
  CONSTRAINT `fk_issues_warehouse`    FOREIGN KEY (`warehouse_id`)    REFERENCES `catalogs_warehouses` (`id`),
  CONSTRAINT `fk_issues_counterparty` FOREIGN KEY (`counterparty_id`) REFERENCES `catalogs_counterparties` (`id`),
  CONSTRAINT `fk_issues_posted_by`    FOREIGN KEY (`posted_by`)       REFERENCES `users` (`id`),
  CONSTRAINT `fk_issues_created_by`   FOREIGN KEY (`created_by`)      REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Расходные накладные (шапка)';

CREATE TABLE `documents_issues_lines` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`     BIGINT UNSIGNED NOT NULL,
  `line_no`         INT UNSIGNED    NOT NULL DEFAULT 1,
  `nomenclature_id` BIGINT UNSIGNED NOT NULL,
  `quantity`        DECIMAL(15,3)   NOT NULL,
  `price`           DECIMAL(15,2)   NOT NULL DEFAULT 0.00 COMMENT 'Цена продажи (или списания)',
  `vat_rate`        DECIMAL(5,2)    NOT NULL DEFAULT 20.00,
  `amount`          DECIMAL(15,2)   GENERATED ALWAYS AS (ROUND(`quantity` * `price`, 2)) STORED,
  `vat_amount`      DECIMAL(15,2)   GENERATED ALWAYS AS (ROUND(`quantity` * `price` * `vat_rate` / 100, 2)) STORED,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_il_document` (`document_id`),
  KEY `ix_il_nomenclature` (`nomenclature_id`),
  CONSTRAINT `fk_il_document`     FOREIGN KEY (`document_id`)     REFERENCES `documents_issues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_il_nomenclature` FOREIGN KEY (`nomenclature_id`) REFERENCES `catalogs_nomenclature` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Расходные накладные (табличная часть)';

-- 3.3. Перемещение между складами (ПМ)
CREATE TABLE `documents_moves` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `number`             VARCHAR(20)   NOT NULL COMMENT 'ПМ-000001',
  `prefix`             VARCHAR(8)    NOT NULL DEFAULT 'ПМ',
  `doc_date`           DATETIME      NOT NULL,
  `warehouse_from_id`  INT UNSIGNED  NOT NULL,
  `warehouse_to_id`    INT UNSIGNED  NOT NULL,
  `status`             ENUM('draft','posted','error') NOT NULL DEFAULT 'draft',
  `total_quantity`     DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  `total_amount`       DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Себестоимость перемещаемого',
  `comment`            TEXT          NULL,
  `posted_at`          DATETIME      NULL,
  `posted_by`          INT UNSIGNED  NULL,
  `created_by`         INT UNSIGNED  NOT NULL,
  `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`         TIMESTAMP     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_moves_number` (`number`),
  KEY `ix_moves_date` (`doc_date`),
  KEY `ix_moves_from` (`warehouse_from_id`),
  KEY `ix_moves_to` (`warehouse_to_id`),
  KEY `ix_moves_status` (`status`),
  KEY `ix_moves_posted_by` (`posted_by`),
  KEY `ix_moves_created_by` (`created_by`),
  CONSTRAINT `fk_moves_from`       FOREIGN KEY (`warehouse_from_id`) REFERENCES `catalogs_warehouses` (`id`),
  CONSTRAINT `fk_moves_to`         FOREIGN KEY (`warehouse_to_id`)   REFERENCES `catalogs_warehouses` (`id`),
  CONSTRAINT `fk_moves_posted_by`  FOREIGN KEY (`posted_by`)          REFERENCES `users` (`id`),
  CONSTRAINT `fk_moves_created_by` FOREIGN KEY (`created_by`)         REFERENCES `users` (`id`),
  CONSTRAINT `chk_moves_wh` CHECK (`warehouse_from_id` <> `warehouse_to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Перемещения между складами (шапка)';

CREATE TABLE `documents_moves_lines` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`     BIGINT UNSIGNED NOT NULL,
  `line_no`         INT UNSIGNED    NOT NULL DEFAULT 1,
  `nomenclature_id` BIGINT UNSIGNED NOT NULL,
  `quantity`        DECIMAL(15,3)   NOT NULL,
  `price`           DECIMAL(15,2)   NOT NULL DEFAULT 0.00 COMMENT 'Себестоимость за единицу (для оценки перемещения)',
  `amount`          DECIMAL(15,2)   GENERATED ALWAYS AS (ROUND(`quantity` * `price`, 2)) STORED,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_ml_document` (`document_id`),
  KEY `ix_ml_nomenclature` (`nomenclature_id`),
  CONSTRAINT `fk_ml_document`     FOREIGN KEY (`document_id`)     REFERENCES `documents_moves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ml_nomenclature` FOREIGN KEY (`nomenclature_id`) REFERENCES `catalogs_nomenclature` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Перемещения (табличная часть)';

-- 3.4. Инвентаризация (ИНВ)
CREATE TABLE `documents_inventories` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `number`       VARCHAR(20)   NOT NULL COMMENT 'ИНВ-000001',
  `prefix`       VARCHAR(8)    NOT NULL DEFAULT 'ИНВ',
  `doc_date`     DATETIME      NOT NULL,
  `warehouse_id` INT UNSIGNED  NOT NULL,
  `status`       ENUM('draft','posted','error') NOT NULL DEFAULT 'draft',
  `comment`      TEXT          NULL,
  `posted_at`    DATETIME      NULL,
  `posted_by`    INT UNSIGNED  NULL,
  `created_by`   INT UNSIGNED  NOT NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`   TIMESTAMP     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_number` (`number`),
  KEY `ix_inv_date` (`doc_date`),
  KEY `ix_inv_warehouse` (`warehouse_id`),
  KEY `ix_inv_status` (`status`),
  KEY `ix_inv_created_by` (`created_by`),
  CONSTRAINT `fk_inv_warehouse`  FOREIGN KEY (`warehouse_id`) REFERENCES `catalogs_warehouses` (`id`),
  CONSTRAINT `fk_inv_posted_by`  FOREIGN KEY (`posted_by`)    REFERENCES `users` (`id`),
  CONSTRAINT `fk_inv_created_by` FOREIGN KEY (`created_by`)   REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Инвентаризации (шапка)';

CREATE TABLE `documents_inventories_lines` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`     BIGINT UNSIGNED NOT NULL,
  `line_no`         INT UNSIGNED    NOT NULL DEFAULT 1,
  `nomenclature_id` BIGINT UNSIGNED NOT NULL,
  `party_id`        BIGINT UNSIGNED NULL COMMENT 'Партия (для детализации), NULL = общий остаток',
  `book_quantity`   DECIMAL(15,3)   NOT NULL DEFAULT 0.000 COMMENT 'Учётное количество (снято при создании)',
  `fact_quantity`   DECIMAL(15,3)   NOT NULL DEFAULT 0.000 COMMENT 'Фактическое количество по пересчёту',
  `diff_quantity`   DECIMAL(15,3)   GENERATED ALWAYS AS (`fact_quantity` - `book_quantity`) STORED,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_invl_document` (`document_id`),
  KEY `ix_invl_nomenclature` (`nomenclature_id`),
  KEY `ix_invl_party` (`party_id`),
  CONSTRAINT `fk_invl_document`     FOREIGN KEY (`document_id`)     REFERENCES `documents_inventories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invl_nomenclature` FOREIGN KEY (`nomenclature_id`) REFERENCES `catalogs_nomenclature` (`id`),
  CONSTRAINT `fk_invl_party`        FOREIGN KEY (`party_id`)        REFERENCES `stock_parties` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Инвентаризации (табличная часть): факт vs учёт';

-- 3.5. Возвраты (ВП — поставщику, ВК — от покупателя)
CREATE TABLE `documents_returns` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `number`          VARCHAR(20)   NOT NULL COMMENT 'ВП-000001 / ВК-000001',
  `prefix`          VARCHAR(8)    NOT NULL COMMENT 'ВП | ВК',
  `direction`       ENUM('to_supplier','from_customer') NOT NULL,
  `doc_date`        DATETIME      NOT NULL,
  `warehouse_id`    INT UNSIGNED  NOT NULL,
  `counterparty_id` INT UNSIGNED  NOT NULL,
  `source_type`     VARCHAR(32)   NULL COMMENT 'связанный документ: issues | receipts',
  `source_id`       BIGINT UNSIGNED NULL,
  `status`          ENUM('draft','posted','error') NOT NULL DEFAULT 'draft',
  `total_quantity`  DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  `total_amount`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `comment`         TEXT          NULL,
  `posted_at`       DATETIME      NULL,
  `posted_by`       INT UNSIGNED  NULL,
  `created_by`      INT UNSIGNED  NOT NULL,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      TIMESTAMP     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_returns_number` (`number`),
  KEY `ix_ret_date` (`doc_date`),
  KEY `ix_ret_direction` (`direction`),
  KEY `ix_ret_warehouse` (`warehouse_id`),
  KEY `ix_ret_counterparty` (`counterparty_id`),
  KEY `ix_ret_source` (`source_type`, `source_id`),
  KEY `ix_ret_status` (`status`),
  KEY `ix_ret_created_by` (`created_by`),
  CONSTRAINT `fk_ret_warehouse`    FOREIGN KEY (`warehouse_id`)    REFERENCES `catalogs_warehouses` (`id`),
  CONSTRAINT `fk_ret_counterparty` FOREIGN KEY (`counterparty_id`) REFERENCES `catalogs_counterparties` (`id`),
  CONSTRAINT `fk_ret_posted_by`    FOREIGN KEY (`posted_by`)       REFERENCES `users` (`id`),
  CONSTRAINT `fk_ret_created_by`   FOREIGN KEY (`created_by`)      REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Возвраты поставщику / от покупателя (шапка)';

CREATE TABLE `documents_returns_lines` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`     BIGINT UNSIGNED NOT NULL,
  `line_no`         INT UNSIGNED    NOT NULL DEFAULT 1,
  `nomenclature_id` BIGINT UNSIGNED NOT NULL,
  `quantity`        DECIMAL(15,3)   NOT NULL,
  `price`           DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `vat_rate`        DECIMAL(5,2)    NOT NULL DEFAULT 20.00,
  `amount`          DECIMAL(15,2)   GENERATED ALWAYS AS (ROUND(`quantity` * `price`, 2)) STORED,
  `vat_amount`      DECIMAL(15,2)   GENERATED ALWAYS AS (ROUND(`quantity` * `price` * `vat_rate` / 100, 2)) STORED,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_retl_document` (`document_id`),
  KEY `ix_retl_nomenclature` (`nomenclature_id`),
  CONSTRAINT `fk_retl_document`     FOREIGN KEY (`document_id`)     REFERENCES `documents_returns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_retl_nomenclature` FOREIGN KEY (`nomenclature_id`) REFERENCES `catalogs_nomenclature` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Возвраты (табличная часть)';

-- ============================================================================
--  4. РЕГИСТРЫ НАКОПЛЕНИЯ
-- ============================================================================

-- 4.1. Регистр остатков (партионный учёт): склад + номенклатура + партия
CREATE TABLE `registers_stock` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `warehouse_id`    INT UNSIGNED    NOT NULL,
  `nomenclature_id` BIGINT UNSIGNED NOT NULL,
  `party_id`        BIGINT UNSIGNED NOT NULL COMMENT 'Партия (строго NOT NULL — нужна для FIFO)',
  `quantity`        DECIMAL(15,3)   NOT NULL DEFAULT 0.000,
  `cost`            DECIMAL(15,2)   NOT NULL DEFAULT 0.00 COMMENT 'Себестоимость остатка партии (всего)',
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stock_party` (`warehouse_id`, `nomenclature_id`, `party_id`),
  KEY `ix_stock_nom` (`nomenclature_id`),
  KEY `ix_stock_wh` (`warehouse_id`),
  KEY `ix_stock_party` (`party_id`),
  CONSTRAINT `fk_stock_wh`   FOREIGN KEY (`warehouse_id`)    REFERENCES `catalogs_warehouses` (`id`),
  CONSTRAINT `fk_stock_nom`  FOREIGN KEY (`nomenclature_id`) REFERENCES `catalogs_nomenclature` (`id`),
  CONSTRAINT `fk_stock_party` FOREIGN KEY (`party_id`)       REFERENCES `stock_parties` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Регистр остатков (партионный учёт)';

-- 4.2. Регистр движений: все приходы/расходы по документам
CREATE TABLE `registers_movements` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_type`   VARCHAR(32)     NOT NULL COMMENT 'receipts | issues | moves | inventories | returns',
  `document_id`     BIGINT UNSIGNED NOT NULL,
  `doc_date`        DATETIME        NOT NULL,
  `warehouse_id`    INT UNSIGNED    NOT NULL,
  `nomenclature_id` BIGINT UNSIGNED NOT NULL,
  `party_id`        BIGINT UNSIGNED NULL,
  `quantity_in`     DECIMAL(15,3)   NOT NULL DEFAULT 0.000,
  `quantity_out`    DECIMAL(15,3)   NOT NULL DEFAULT 0.000,
  `amount_in`       DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `amount_out`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_mv_doc` (`document_type`, `document_id`),
  KEY `ix_mv_nom_date` (`nomenclature_id`, `doc_date`),
  KEY `ix_mv_wh_date` (`warehouse_id`, `doc_date`),
  KEY `ix_mv_date` (`doc_date`),
  KEY `ix_mv_party` (`party_id`),
  CONSTRAINT `fk_mv_wh`   FOREIGN KEY (`warehouse_id`)    REFERENCES `catalogs_warehouses` (`id`),
  CONSTRAINT `fk_mv_nom`  FOREIGN KEY (`nomenclature_id`) REFERENCES `catalogs_nomenclature` (`id`),
  CONSTRAINT `fk_mv_party` FOREIGN KEY (`party_id`)       REFERENCES `stock_parties` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Регистр движений товаров';

-- 4.3. Регистр цен: номенклатура + тип цены + период действия
CREATE TABLE `registers_prices` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomenclature_id` BIGINT UNSIGNED NOT NULL,
  `price_type`      ENUM('purchase','retail') NOT NULL,
  `price`           DECIMAL(15,2)   NOT NULL,
  `date_from`       DATETIME        NOT NULL,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_prices_nom_type_date` (`nomenclature_id`, `price_type`, `date_from`),
  CONSTRAINT `fk_prices_nom` FOREIGN KEY (`nomenclature_id`) REFERENCES `catalogs_nomenclature` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Регистр цен (закупочные / розничные)';

-- 4.4. Взаиморасчёты с контрагентами
CREATE TABLE `registers_settlements` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `counterparty_id` INT UNSIGNED    NOT NULL,
  `document_type`   VARCHAR(32)     NOT NULL,
  `document_id`     BIGINT UNSIGNED NOT NULL,
  `doc_date`        DATETIME        NOT NULL,
  `amount`          DECIMAL(15,2)   NOT NULL COMMENT '+ долг покупателя нам (РН), − наш долг поставщику (ПН)',
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_set_cp_date` (`counterparty_id`, `doc_date`),
  KEY `ix_set_doc` (`document_type`, `document_id`),
  CONSTRAINT `fk_set_cp` FOREIGN KEY (`counterparty_id`) REFERENCES `catalogs_counterparties` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Регистр взаиморасчётов с контрагентами';
