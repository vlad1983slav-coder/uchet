-- ============================================================================
--  UCHET — тестовые данные (seed)
--  Запуск после schema.sql:  mysql -u root -p uchet < database/seeds.sql
--
--  Учётные записи для входа:
--    admin   / admin123    (администратор)
--    sklad   / sklad123    (кладовщик)
--    manager / manager123  (менеджер)
--  Хеши — настоящий PASSWORD_ARGON2ID (проверяются password_verify()).
-- ============================================================================

USE `uchet`;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Роли
-- ---------------------------------------------------------------------------
INSERT INTO `roles` (`code`, `name`, `permissions`) VALUES
('admin',       'Администратор',
 '{"catalogs":["create","read","update","delete"],"documents":["create","read","update","delete","post","unpost"],
   "reports":["read"],"users":["create","read","update","delete"],"settings":["read","update"]}'),
('storekeeper', 'Кладовщик',
 '{"catalogs":["read"],"documents":["create","read","update","post","unpost"],
   "reports":["read"],"users":[],"settings":["read"]}'),
('manager',     'Менеджер',
 '{"catalogs":["read"],"documents":["create","read","update"],
   "reports":["read"],"users":[],"settings":["read"]}'),
('guest',       'Гость',
 '{"catalogs":["read"],"documents":["read"],"reports":["read"],"users":[],"settings":[]}');

-- ---------------------------------------------------------------------------
-- Пользователи (пароли: admin123 / sklad123 / manager123)
-- ---------------------------------------------------------------------------
INSERT INTO `users` (`login`, `email`, `password_hash`, `full_name`, `role_id`, `is_active`) VALUES
('admin',   'admin@uchet.local',   '$argon2id$v=19$m=65536,t=4,p=1$dWNoZXRzZWVkMTIzNDU2Nw$vlRh8AVK3nmyxpC8EUs+G/SCdgITUSfffm8LkQJWLrc', 'Иванов Иван Иванович', 1, 1),
('sklad',   'sklad@uchet.local',   '$argon2id$v=19$m=65536,t=4,p=1$dWNoZXRzZWVkMTIzNDU2Nw$d3ExWiCRQrlGKYSJyIyzyciPvNbi0b3yzm4X4rXkcNo', 'Петров Пётр Сидорович', 2, 1),
('manager', 'manager@uchet.local', '$argon2id$v=19$m=65536,t=4,p=1$dWNoZXRzZWVkMTIzNDU2Nw$K5MwHeldkOCR+esW0KBCE81jkYLHE8n9lCNXZXZXZLA', 'Сидорова Анна Сергеевна', 3, 1);

-- ---------------------------------------------------------------------------
-- Единицы измерения (коды по ОКЕИ)
-- ---------------------------------------------------------------------------
INSERT INTO `catalogs_units` (`code`, `name`, `coefficient`) VALUES
('796', 'Штука',        1.000000),
('006', 'Килограмм',    1.000000),
('166', 'Метр',         1.000000),
('839', 'Упаковка (10 шт)', 10.000000);

-- ---------------------------------------------------------------------------
-- Категории номенклатуры
-- ---------------------------------------------------------------------------
INSERT INTO `catalogs_categories` (`id`, `parent_id`, `code`, `name`) VALUES
(1, NULL, 'CLOTH',   'Одежда'),
(2, 1,    'OUTER',   'Верхняя одежда'),
(3, NULL, 'SHOES',   'Обувь'),
(4, NULL, 'ACCESS',  'Аксессуары'),
(5, NULL, 'FOOD',    'Продукты'),
(6, NULL, 'SERVICE', 'Услуги');

-- ---------------------------------------------------------------------------
-- Номенклатура
-- ---------------------------------------------------------------------------
INSERT INTO `catalogs_nomenclature`
  (`id`, `article`, `name`, `unit_id`, `category_id`, `vat_rate`, `purchase_price`, `min_stock`, `is_service`, `description`) VALUES
(1, 'FUT-001', 'Футболка «Базовая» белая, р-р M',        1, 1, 20.00,  250.00, 10.000, 0, 'Хлопок 100%'),
(2, 'KUR-001', 'Куртка зимняя «Север» чёрная',           1, 2, 20.00, 1500.00, 2.000, 0, 'Пуховик, до -30°C'),
(3, 'DZH-001', 'Джинсы классические синие',               1, 1, 20.00,  800.00, 5.000, 0, NULL),
(4, 'OB-001',  'Кроссовки «Бег» белые',                   1, 3, 20.00, 2200.00, 3.000, 0, NULL),
(5, 'SH-001',  'Шапка вязаная серая',                     1, 4, 20.00,  300.00, 10.000, 0, NULL),
(6, 'KOF-001', 'Кофе в зёрнах «Arabica», 1 кг',           2, 5, 20.00,  900.00, 5.000, 0, 'Свежая обжарка'),
(7, 'PV-001',  'Пакет фирменный бумажный',                1, 4, 20.00,   15.00, 100.000, 0, NULL),
(8, 'DEL-001', 'Доставка заказа',                         1, 6, 20.00,    0.00, 0.000, 1, 'Услуга, без складского учёта');

-- ---------------------------------------------------------------------------
-- Склады (МОЛ = пользователь)
-- ---------------------------------------------------------------------------
INSERT INTO `catalogs_warehouses` (`id`, `code`, `name`, `address`, `mol_user_id`) VALUES
(1, '01', 'Основной склад',    'г. Krasnoyarsk, ул. Складская, 1', 1),
(2, '02', 'Магазин на Ленина', 'г. Krasnoyarsk, пр. Ленина, 25',   2);

-- ---------------------------------------------------------------------------
-- Контрагенты
-- ---------------------------------------------------------------------------
INSERT INTO `catalogs_counterparties` (`id`, `inn`, `kpp`, `name`, `type`, `phone`, `email`, `address`) VALUES
(1, '5001234567', '500101001', 'ООО «ОптТекстиль»',        'supplier', '+7 (391) 200-00-01', 'opt@textil.example',  'г. Красноярск, ул. Оптовая, 10'),
(2, '5007654321', '500701001', 'ООО «КофеТрейд»',          'supplier', '+7 (391) 200-00-02', 'coffee@example',     'г. Красноярск, ул. Зерновая, 5'),
(3, '7722334455', '772201001', 'ООО «Розничный покупатель»','customer', '+7 (391) 300-00-03', 'buy@example',        'г. Красноярск, ул. Покупательская, 3'),
(4, '7733445566', '773301001', 'ИП Смирнов А.В.',          'customer', '+7 (913) 111-22-33', 'smirnov@example',    'г. Красноярск, ул. ИП, 7'),
(5, '5009876543', '500901001', 'ЗАО «Универсал»',          'both',     '+7 (391) 400-00-04', 'universal@example',  'г. Красноярск, ул. Универсальная, 1');

-- ---------------------------------------------------------------------------
-- Настройки системы
-- ---------------------------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `value`, `description`) VALUES
('org_name',          'ООО «Ромашка»',                 'Наименование организации (для печатных форм)'),
('org_inn',           '2460000000',                   'ИНН организации'),
('currency',          'RUB',                          'Валюта учёта'),
('default_vat',       '20',                           'Ставка НДС по умолчанию, %'),
('stock_method',      'FIFO',                         'Метод списания партий: FIFO | AVG'),
('closed_period_date','2025-12-31',                   'Закрытый период: документы с датой ранее не проводятся/не распроводятся'),
('negative_stock',    'forbid',                       'Отрицательные остатки: forbid | allow'),
('doc_prefix_receipts','ПН',                          'Префикс приходной накладной'),
('doc_prefix_issues', 'РН',                           'Префикс расходной накладной'),
('doc_prefix_moves',  'ПМ',                           'Префикс перемещения'),
('doc_prefix_inv',    'ИНВ',                          'Префикс инвентаризации'),
('doc_prefix_ret_sup','ВП',                           'Префикс возврата поставщику'),
('doc_prefix_ret_cus','ВК',                           'Префикс возврата от покупателя');

-- ---------------------------------------------------------------------------
-- Счётчики нумерации (год 2026)
-- ---------------------------------------------------------------------------
INSERT INTO `document_counters` (`prefix`, `year`, `last_number`) VALUES
('ПН', 2026, 1), ('РН', 2026, 1), ('ПМ', 2026, 0),
('ИНВ', 2026, 0), ('ВП', 2026, 0), ('ВК', 2026, 0);

-- ---------------------------------------------------------------------------
-- Регистр цен (закупочная / розничная, действует с 01.01.2026)
-- ---------------------------------------------------------------------------
INSERT INTO `registers_prices` (`nomenclature_id`, `price_type`, `price`, `date_from`) VALUES
(1,'purchase', 250.00,'2026-01-01 00:00:00'), (1,'retail', 490.00,'2026-01-01 00:00:00'),
(2,'purchase',1500.00,'2026-01-01 00:00:00'), (2,'retail',2990.00,'2026-01-01 00:00:00'),
(3,'purchase', 800.00,'2026-01-01 00:00:00'), (3,'retail',1590.00,'2026-01-01 00:00:00'),
(4,'purchase',2200.00,'2026-01-01 00:00:00'), (4,'retail',4390.00,'2026-01-01 00:00:00'),
(5,'purchase', 300.00,'2026-01-01 00:00:00'), (5,'retail', 650.00,'2026-01-01 00:00:00'),
(6,'purchase', 900.00,'2026-01-01 00:00:00'), (6,'retail',1790.00,'2026-01-01 00:00:00'),
(7,'purchase',  15.00,'2026-01-01 00:00:00'), (7,'retail',  40.00,'2026-01-01 00:00:00'),
(8,'purchase',   0.00,'2026-01-01 00:00:00'), (8,'retail', 350.00,'2026-01-01 00:00:00');

-- ---------------------------------------------------------------------------
-- Начальные остатки (партии source_type='opening') на складе 01
-- ---------------------------------------------------------------------------
INSERT INTO `stock_parties` (`id`, `nomenclature_id`, `source_type`, `source_id`) VALUES
(1, 1, 'opening', NULL),
(2, 2, 'opening', NULL),
(3, 3, 'opening', NULL),
(4, 4, 'opening', NULL),
(5, 5, 'opening', NULL),
(6, 6, 'opening', NULL),
(7, 7, 'opening', NULL);

INSERT INTO `registers_stock` (`warehouse_id`, `nomenclature_id`, `party_id`, `quantity`, `cost`) VALUES
(1, 1, 1,  50.000, 12500.00),
(1, 2, 2,   5.000,  7500.00),
(1, 3, 3,  20.000, 16000.00),
(1, 4, 4,   8.000, 17600.00),
(1, 5, 5,  30.000,  9000.00),
(1, 6, 6,  12.000, 10800.00),
(1, 7, 7, 200.000,  3000.00);

-- ---------------------------------------------------------------------------
-- ДЕМО-ДОКУМЕНТ: проведённая приходная накладная ПН-000001
--  (показывает, как работают партии, остатки, движения, взаиморасчёты, аудит)
-- ---------------------------------------------------------------------------
INSERT INTO `documents_receipts`
  (`id`,`number`,`prefix`,`doc_date`,`warehouse_id`,`counterparty_id`,`status`,
   `total_quantity`,`total_amount`,`comment`,`posted_at`,`posted_by`,`created_by`)
VALUES
 (1,'ПН-000001','ПН','2026-01-10 10:30:00',1,1,'posted',
  120.000, 55000.00, 'Первая поставка januari'.replace(' januari',''), '2026-01-10 10:35:00', 1, 1);

INSERT INTO `documents_receipts_lines`
  (`id`,`document_id`,`line_no`,`nomenclature_id`,`quantity`,`price`,`vat_rate`) VALUES
 (1, 1, 1, 1, 100.000,  250.00, 20.00),
 (2, 1, 2, 2,  20.000, 1500.00, 20.00);

-- Партии, созданные этой ПН
INSERT INTO `stock_parties` (`id`,`nomenclature_id`,`source_type`,`source_id`) VALUES
 (8, 1, 'receipt', 1),
 (9, 2, 'receipt', 1);

-- Остатки после проведения ПН
INSERT INTO `registers_stock` (`warehouse_id`,`nomenclature_id`,`party_id`,`quantity`,`cost`) VALUES
 (1, 1, 8, 100.000, 25000.00),
 (1, 2, 9,  20.000, 30000.00);

-- Движения по ПН
INSERT INTO `registers_movements`
  (`document_type`,`document_id`,`doc_date`,`warehouse_id`,`nomenclature_id`,`party_id`,`quantity_in`,`quantity_out`,`amount_in`,`amount_out`) VALUES
 ('receipts',1,'2026-01-10 10:30:00',1,1,8, 100.000, 0, 25000.00, 0),
 ('receipts',1,'2026-01-10 10:30:00',1,2,9,  20.000, 0, 30000.00, 0);

-- Взаиморасчёты: мы должны поставщику 55 000 (отрицательная сумма)
INSERT INTO `registers_settlements` (`counterparty_id`,`document_type`,`document_id`,`doc_date`,`amount`) VALUES
 (1,'receipts',1,'2026-01-10 10:30:00', -55000.00);

-- Аудит по ПН-000001
INSERT INTO `audit_log` (`user_id`,`entity_type`,`entity_id`,`action`,`old_data`,`new_data`,`ip`,`user_agent`) VALUES
 (1,'documents_receipts',1,'create',NULL,'{"number":"ПН-000001","status":"draft"}','127.0.0.1','seed'),
 (1,'documents_receipts',1,'post','{"status":"draft"}','{"status":"posted","posted_at":"2026-01-10 10:35:00"}','127.0.0.1','seed');

-- ---------------------------------------------------------------------------
-- ДЕМО-ДОКУМЕНТ: черновик расходной накладной РН-000001 (ещё не проведён)
-- ---------------------------------------------------------------------------
INSERT INTO `documents_issues`
  (`id`,`number`,`prefix`,`doc_date`,`warehouse_id`,`counterparty_id`,`status`,
   `total_quantity`,`total_amount`,`comment`,`created_by`)
VALUES
 (1,'РН-000001','РН','2026-11-03 09:00:00',1,3,'draft',
  2.000, 980.00, 'Тестовый черновик отгрузки', 3);

INSERT INTO `documents_issues_lines`
  (`document_id`,`line_no`,`nomenclature_id`,`quantity`,`price`,`vat_rate`) VALUES
 (1, 1, 1, 2.000, 490.00, 20.00);

INSERT INTO `audit_log` (`user_id`,`entity_type`,`entity_id`,`action`,`new_data`,`ip`,`user_agent`) VALUES
 (3,'documents_issues',1,'create','{"number":"РН-000001","status":"draft"}','127.0.0.1','seed');
