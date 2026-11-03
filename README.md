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

# 3. настройте подключение: скопируйте .env.example в .env и заполните
#    (приложение читает .env автоматически; см. также переменные окружения и config/config.php)
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

## Развёртывание на хостинге (production)

### 0. Требования к хостингу

- **PHP 8.2+** с расширениями `pdo_mysql`, `mbstring`, `json`, `openssl`; Apache с `mod_rewrite` **или** nginx; ~64 МБ памяти
- **MySQL 8.0+ / MariaDB 10.6+**; выделенная БД и отдельный пользователь (не root!)
- **HTTPS** (Let's Encrypt) — обязательно: по сети ходят пароли и сессионные cookie
- Веб-сервер должен иметь право писать только в `storage/` (логи, сессии, кеш)

### 1. Общий порядок развёртывания (любой хостинг)

1. Загрузите **весь проект** на сервер (git clone или SFTP) — не только `public/`. Например, в `/var/www/uchet`.
2. Зависимости: `composer install --no-dev --optimize-autoloader` (можно пропустить — есть встроенный fallback-автозагрузчик PSR-4).
3. Создайте БД и пользователя с правами только на неё:
   ```sql
   CREATE DATABASE uchet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'uchet'@'localhost' IDENTIFIED BY 'очень_сложный_пароль';
   GRANT ALL PRIVILEGES ON uchet.* TO 'uchet'@'localhost';
   FLUSH PRIVILEGES;
   ```
4. Наполните схему: `php database/migrate.php` (применит `schema.sql`, `seeds.sql` и все новые файлы из `database/migrations/`; повторный запуск безопасен — применяются только новые миграции).
   Если на хостинге нет CLI (shared-хостинг) — импортируйте `database/schema.sql` и `database/seeds.sql` через phpMyAdmin (вкладка «SQL», большие файлы — частями).
5. Настройте подключение: `cp .env.example .env` и заполните (приложение читает `.env` автоматически):
   ```ini
   APP_DEBUG=false
   APP_URL=https://erp.example.ru
   APP_TIMEZONE=Asia/Krasnoyarsk
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=uchet
   DB_USERNAME=uchet
   DB_PASSWORD=очень_сложный_пароль
   ```
6. **DocumentRoot веб-сервера должен указывать на каталог `public/`** (варианты ниже). Из web доступен только `public/`; `.htaccess` дополнительно запрещает отдачу `.env`, `*.sql`, `composer.json`.
7. Права на файлы:
   ```bash
   sudo chown -R www-data:www-data /var/www/uchet/storage   # логи/сессии/кеш — пишет веб-сервер
   sudo chmod -R 750 /var/www/uchet/storage
   sudo chown -R root:root /var/www/uchet                     # остальное — только чтение
   sudo find /var/www/uchet -type f -exec chmod 644 {} \;
   sudo find /var/www/uchet -type d -exec chmod 755 {} \;
   ```
8. Откройте `https://ваш-домен/login`, войдите и **сразу смените пароли** `admin` / `sklad` / `manager` (Администрирование → Пользователи) или отключите лишних.
9. Проверьте end-to-end: создайте ПН, проведите её, посмотрите «Отчёты → Остатки» и «Печать».

### 2. Вариант A: shared-хостинг (cPanel / Plesk / ISPmanager)

1. Залейте проект целиком в домашнюю директорию, например `~/uchet` (вне `public_html`).
2. В панели домена смените **DocumentRoot** на `~/uchet/public`. Если панель не позволяет:
   - symlink: `ln -s ~/uchet/public ~/public_html` (предварительно очистите `public_html`), **или**
   - залейте содержимое `public/` в `public_html/`, а остаток проекта — в `~/uchet_app`, и в `public_html/index.php` замените `define('ROOT', dirname(__DIR__));` на `define('ROOT', '/home/USER/uchet_app');`.
3. БД создайте в разделе «Базы данных MySQL», пользователя — там же; импортируйте `database/schema.sql` и `database/seeds.sql` через phpMyAdmin.
4. `.env` создайте через файловый менеджер (или задайте переменные через `SetEnv DB_HOST ...` в `.htaccess` — `getenv()` их увидит).
5. `mod_rewrite` на shared-хостинге обычно уже включён; если ЧПУ не работает — включите в панели (правила в `public/.htaccess` уже есть).
6. CLI нет → миграции только через phpMyAdmin (шаг 4 выше).

### 3. Вариант B: VDS/VPS (Ubuntu 24.04 + nginx + PHP-FPM + MariaDB) — рекомендуемый

```bash
# --- пакеты ---
sudo apt update
sudo apt install -y nginx mariadb-server php8.2-fpm php8.2-mysql php8.2-mbstring \
    php8.2-xml php8.2-curl git composer unzip certbot python3-certbot-nginx ufw

# --- база ---
sudo mysql_secure_installation          # задайте пароль root, отключите удалённый root
sudo mysql -u root -p -e "CREATE DATABASE uchet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'uchet'@'localhost' IDENTIFIED BY 'очень_сложный_пароль';
  GRANT ALL PRIVILEGES ON uchet.* TO 'uchet'@'localhost'; FLUSH PRIVILEGES;"

# --- код ---
sudo mkdir -p /var/www && sudo chown $USER /var/www
git clone https://github.com/vlad1983slav-coder/uchet.git /var/www/uchet
cd /var/www/uchet
composer install --no-dev --optimize-autoloader
cp .env.example .env && nano .env        # APP_DEBUG=false, APP_URL=https://erp.example.ru, DB_*...
php database/migrate.php                 # наполнение схемы + сиды
sudo chown -R www-data:www-data storage

# --- nginx ---
sudo tee /etc/nginx/sites-available/uchet >/dev/null <<'EOF'
server {
    listen 80;
    server_name erp.example.ru;
    root /var/www/uchet/public;
    index index.php;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # защита: .env, *.sql, логи, скрытые файлы — не отдавать
    location ~ /\.(?!well-known) { deny all; }
    location ~* \.(env|sql|log|json|lock)$ { deny all; }
}
EOF
sudo ln -sf /etc/nginx/sites-available/uchet /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# --- HTTPS ---
sudo certbot --nginx -d erp.example.ru

# --- firewall: наружу только SSH и веб; 3306 остаётся на localhost ---
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

### 4. Вариант C: Docker (быстрый старт)

В репозитории есть `docker-compose.yml` и `docker/Dockerfile` (PHP 8.2 + Apache, docroot=`public/`, MariaDB 10.6):

```bash
cp .env.example .env       # поправьте: DB_HOST=db, DB_USERNAME=root, DB_PASSWORD=rootsecret
sed -i 's/^APP_DEBUG=.*/APP_DEBUG=true/; s|^APP_URL=.*|APP_URL=http://localhost:8000|' .env
docker compose up -d --build
docker compose exec app php database/migrate.php
# открыть http://localhost:8000  →  admin / admin123
```

### 5. Production-hardening (обязательный чек-лист)

- `APP_DEBUG=false` в `.env` — иначе наружу уйдут трейсы и пути файлов (ошибки при этом пишутся в `storage/logs/error.log`)
- `APP_URL=https://ваш-домен` — для корректных абсолютных ссылок
- HTTPS-редирект — добавьте в `public/.htaccess`:
  ```apache
  RewriteCond %{HTTPS} off
  RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
  ```
- БД слушает только `localhost` (в MariaDB так по умолчанию), firewall закрыт (`ufw`), пользователю БД выданы права **только на `uchet.*`**
- `docroot = public/` + deny-правила в `.htaccess`/nginx (см. выше) — `.env`, `*.sql`, логи не должны отдаваться вебом
- пароли `admin/sklad/manager` сменены сразу после первого входа; неиспользуемые учётки отключены (`is_active=0`)
- `storage/logs/error.log` — настройте ротацию (logrotate), иначе разрастается
- opcache включён (в php-fpm production — по умолчанию)

### 6. Резервное копирование (cron, ежедневно в 03:00)

```bash
sudo mkdir -p /var/backups/uchet
sudo crontab -e
# добавить:
0 3 * * * mysqldump -u uchet -p'пароль' uchet | gzip > /var/backups/uchet/uchet-$(date +\%F).sql.gz
0 4 * * * find /var/backups/uchet -name 'uchet-*.sql.gz' -mtime +14 -delete
```
Восстановление: `gunzip < /var/backups/uchet/uchet-2026-11-03.sql.gz | mysql -u uchet -p uchet`
Проект ведите в git — тогда бэкап кода = `git clone` репозитория.

### 7. Обновление на боевом сервере

```bash
cd /var/www/uchet
git pull
composer install --no-dev --optimize-autoloader
php database/migrate.php             # применятся только новые миграции (идемпотентно)
sudo systemctl reload php8.2-fpm     # сброс opcache
```

### 8. Типичные проблемы и решения

| Симптом | Причина / решение |
|---|---|
| Белый экран, 500 | Смотрите `storage/logs/error.log`; временно `APP_DEBUG=true`. Часто — нет прав на `storage/` → `chown www-data storage` |
| 404 на всех URL, кроме `/index.php` | Не работает ЧПУ: Apache — `a2enmod rewrite` + `AllowOverride All`; nginx — `try_files ... /index.php` |
| «Не удалось подключиться к БД» | Проверьте `.env`/`SetEnv`; `mysql -h 127.0.0.1 -u uchet -p uchet` должно работать; при unix-сокете — `DB_HOST=localhost` |
| Ошибки сессии / CSRF (419) | Нет прав на запись в `storage/sessions` → `chown -R www-data:www-data storage` |
| Кракозябры | Соединение/БД в utf8mb4 — в схеме уже `charset=utf8mb4` |
| Медленно | opcache включён; индексы созданы в `schema.sql`; списки пагинируются (`per_page` до 100) |

---

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
