# UCHET — система складского учёта в стиле «1С:Предприятие»

Полноценная ERP-lite на **PHP 8.2+ / MySQL 8.0 (MariaDB 10.6)**: справочники, документы с
проведением, партионный учёт (FIFO / средняя), регистры остатков и движений, взаиморасчёты,
отчёты, печатные формы, аудит, роли и права. Архитектура **MVC + Service Layer + Repository**,
весь SQL — через **PDO prepared statements**, пароли — **PASSWORD_ARGON2ID**, все POST защищены
**CSRF**, вывод экранируется `htmlspecialchars`.

## Стек

- PHP 8.2+ (ООП, PSR-12, `strict_types`), Composer PSR-4 (`Uchet\` → `app/`)
- MySQL 8.0 / MariaDB 10.6, InnoDB, utf8mb4
- Frontend: Bootstrap 5 + vanilla JS + AJAX (без тяжёлых фреймворков)
- PDF печатных форм — через `dompdf/dompdf` (опционально, `composer require dompdf/dompdf`); без него формы открываются как HTML для печати

## Установка

```bash
# 1. зависимости (нужен только PHP; автозагрузка работает и без composer — есть fallback)
composer install            # или можно пропустить: public/index.php сам поднимет PSR-4

# 2. база данных (один из вариантов)
php database/migrate.php    # миграционный раннер: schema.sql + seeds.sql + migrations/*.sql
# или
DB_USER=root DB_PASS=secret ./database/deploy.sh
# или вручную:
mysql -u root -p < database/schema.sql
mysql -u root -p uchet < database/seeds.sql

# 3. настройте подключение (переменные окружения или config/config.php)
cp .env.example .env        # DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_URL...

# 4. запуск
# встроенный сервер для разработки:
composer serve              # = php -S 0.0.0.0:8000 -t public public/index.php
# или откройте http://localhost:8000

# production: DocumentRoot → public/, Apache с mod_rewrite (см. public/.htaccess)
# или nginx:  location / { try_files $uri $uri/ /index.php?$query_string; }
```

**Тестовые входы** (хеши — настоящий Argon2id):

| Логин   | Пароль      | Роль         |
|---------|-------------|--------------|
| admin   | admin123    | admin        |
| sklad   | sklad123    | кладовщик    |
| manager | manager123  | менеджер     |

## Пример работы (сценарий)

1. Войдите как `admin`. Слева — навигатор разделов, как в 1С.
2. **Справочники → Номенклатура** — создайте товар (артикул, ед.изм., НДС, учётная цена, мин. остаток).
3. **Документы → Приходные накладные → Создать**: дата, склад, поставщик; в табличной части введите **артикул** — подставятся наименование, ед.изм., НДС и закупочная цена; суммы и итоги считаются **в реальном времени**. «Сохранить» → черновик (серый бейдж), номер вида `ПН-000001` присвоен автоматически.
4. На форме документа кнопка **«Провести»** (зелёный бейдж): в одной транзакции создаются партии, растут остатки (`registers_stock`), пишутся движения (`registers_movements`) и взаиморасчёт (нам должны / мы должны — `registers_settlements`), фиксируется запись в `audit_log`. Кнопка **«Распровести»** выполняет обратную операцию.
5. **Расходная накладная**: при проведении списывает остатки **по FIFO** (или по средней — `settings.stock_method = AVG`); если товара не хватает и `negative_stock = forbid`, проведение падает с ошибкой 422 (документ краснеет, статус `error`, причина — в аудите).
6. **Перемещение** — товар уезжает со склада на склад **с сохранением партии**. **Инвентаризация** — создайте документ на склад: строки подставятся с учётными остатками, введите факт, проведите — излишки оприходуются новой партией, недостача спишется. **Возвраты** ВП/ВК.
7. **Отчёты**: остатки на дату (пересчёт назад из движений), движения за период, обороты по складам (начало/приход/расход/конец), карточка товара, взаиморасчёты.
8. **Печать** на форме документа — накладная (HTML→PDF, если стоит dompdf).
9. Документы с датой до `settings.closed_period_date` — **закрытый период**: их нельзя провести, распровести или изменить.

## Права (RBAC)

Роли в таблице `roles`, права — JSON вида `{"documents": ["create","post",...], "catalogs": ["read"], "*": ["*"]}`.
Проверка: `can('documents.post')` / `$this->authorize('documents.post')` в контроллере → 403.

- **admin** — всё; **кладовщик** — документы (создание/проведение, без удаления), чтение отчётов;
  **менеджер** — создание/просмотр документов без проведения; **гость** — только чтение.

## Структура проекта

См. `docs/structure.md`. Кратко: `app/Core` (Database, Router, Request/Response, Controller, Model,
Repository, Validator, Auth, Session, Csrf, View), `app/Controllers`, `app/Services`
(`DocumentService` — проведение, `StockService` — FIFO/AVG, `ReportService`, `NumberingService` —
нумерация `ПН-000001`, `AuditService`, `SettingsService`), `app/Repositories`, `app/Models`,
`config/routes.php`, `public/index.php` (front controller), `resources/views`, `database`.

## База данных

`database/schema.sql` — полный DDL: `roles`, `users`, `settings`, `document_counters`, `audit_log`;
справочники `catalogs_*`; документы `documents_*` + `documents_*_lines` (статусы `draft/posted/error`,
суммы — генерируемые колонки); регистры `stock_parties`, `registers_stock` (партионный остаток,
UNIQUE склад+номенклатура+партия), `registers_movements`, `registers_prices`, `registers_settlements`.
Количества `DECIMAL(15,3)`, суммы `DECIMAL(15,2)`, везде `created_at/updated_at/deleted_at` (soft delete),
все связи — внешние ключи InnoDB. `database/seeds.sql` — демо-данные (включая проведённую ПН-000001).

## Безопасность

- пароли: `password_hash(..., PASSWORD_ARGON2ID)` + `password_verify` + авто-рехеш;
- CSRF-токен на сессии для всех POST (`_csrf` поле / `X-CSRF-Token` заголовок), `hash_equals`;
- сессии: HttpOnly, SameSite=Lax, strict mode, `session_regenerate_id(true)` при входе;
- SQL — только prepared statements, идентификаторы квотируются и валидируются (`quoteIdentifier`);
- весь вывод — `e()` (htmlspecialchars); права — на уровне контроллеров; аудит всех изменений.

## Маршруты (основные)

`GET /` — панель; `/login`, `/logout`; `/catalogs/{nomenclature|warehouses|counterparties|units|categories}`;
`/documents/{receipts|issues|moves|inventories|returns}` (+ `/{id}/post|unpost|delete|print|edit`);
`/reports/{stock|movements|turnover|card/{id}|settlements}`; `/users`; AJAX: `/ajax/nomenclature`, `/ajax/counterparties`.
Полная таблица — `config/routes.php`.
