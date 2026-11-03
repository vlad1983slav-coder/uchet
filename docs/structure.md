# Структура проекта `uchet`

```
uchet/
├── app/                        # Прикладной код (PSR-4: Uchet\ => app/)
│   ├── Core/                   # Ядро фреймворка
│   │   ├── Database.php        #   Singleton PDO (MySQL, prepared statements, транзакции)
│   │   ├── Router.php          #   Роутер: URI → [Controller, method], {param} в URL
│   │   ├── Request.php         #   Обёртка над $_GET/$_POST/$_SERVER/JSON body
│   │   ├── Response.php        #   Ответ: HTML / JSON / redirect / PDF
│   │   ├── Controller.php      #   Базовый контроллер: view(), json(), проверка прав, CSRF
│   │   ├── Model.php           #   Базовая модель (table, fillable, timestamps, soft delete)
│   │   ├── Repository.php      #   Базовый репозиторий: find/paginate/insert/update/delete
│   │   ├── Validator.php       #   Валидатор входных данных (required, numeric, decimal, date, enum...)
│   │   ├── Auth.php            #   Аутентификация, сессии, RBAC-проверка прав по ролям
│   │   ├── Session.php         #   Сессии, flash-сообщения
│   │   ├── Csrf.php            #   CSRF-токены для всех POST
│   │   ├── View.php            #   Рендер PHP-шаблонов (layout + view, экранирование)
│   │   └── helpers.php         #   e(), money(), qty(), redirect(), ...
│   ├── Controllers/            #   Тонкие контроллеры (роуты → сервисы)
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── NomenclatureController.php
│   │   ├── CategoryController.php
│   │   ├── UnitController.php
│   │   ├── WarehouseController.php
│   │   ├── CounterpartyController.php
│   │   ├── UserController.php
│   │   ├── ReceiptController.php        # ПН
│   │   ├── IssueController.php          # РН
│   │   ├── MoveController.php           # ПМ
│   │   ├── InventoryController.php      # ИНВ
│   │   ├── ReturnController.php         # ВП / ВК
│   │   ├── ReportController.php         # Остатки, движения, обороты, карточка товара
│   │   └── PrintController.php          # Печатные формы (HTML → PDF)
│   ├── Models/                 #   Модели сущностей (по одной на таблицу)
│   ├── Repositories/           #   Слой доступа к данным (по одной на сущность)
│   ├── Services/
│   │   ├── DocumentService.php         # Проведение/распроведение, транзакции, закрытый период
│   │   ├── StockService.php            # Партионный учёт: FIFO / AVG списание, остатки
│   │   ├── NumberingService.php        # Нумерация ПН-000001 через document_counters
│   │   ├── AuditService.php            # Запись в audit_log
│   │   ├── SettlementService.php       # Взаиморасчёты
│   │   ├── ReportService.php           # Данные для отчётов
│   │   └── AuthService.php             # Логин/логаут, password_hash/password_verify
│   └── Middleware/             #   AuthMiddleware, CsrfMiddleware, RightsMiddleware
├── config/
│   ├── config.php              #   Настройки подключения к БД и приложения (или .env)
│   └── routes.php              #   Таблица маршрутов
├── database/
│   ├── schema.sql              #   DDL: все таблицы, FK, индексы (MySQL 8.0 / MariaDB 10.6)
│   ├── seeds.sql               #   Тестовые данные (роли, пользователи, номенклатура, демо-документы)
│   └── migrations/             #   Инкрементальные миграции (001_..., 002_...)
├── public/                     #   Document root (Apache/Nginx смотрят сюда)
│   ├── index.php               #   Front controller (единая точка входа)
│   ├── .htaccess               #   ЧПУ (все запросы → index.php)
│   ├── css/1c-style.css        #   Стили интерфейса в духе 1С
│   ├── js/app.js               #   Vanilla JS: AJAX, динамические строки ТЧ, фильтры, totals
│   └── uploads/                #   Загружаемые файлы
├── resources/views/            #   PHP-шаблоны
│   ├── layouts/main.php        #   Layout: левое меню-навигатор 1С, верхняя панель команд
│   ├── partials/               #   flash-сообщения, пагинация, фильтры, modals
│   ├── auth/login.php
│   ├── dashboard/index.php
│   ├── catalogs/               #   Списки и формы справочников
│   ├── documents/              #   Списки и формы документов (шапка + ТЧ)
│   ├── reports/                #   Остатки, движения, обороты, карточка, взаиморасчёты
│   ├── users/                  #   Пользователи и роли
│   └── print/                  #   Печатные формы накладных
├── storage/
│   ├── logs/                   #   Логи ошибок
│   ├── cache/
│   └── sessions/
├── docs/
│   └── structure.md            #   Этот файл
├── tests/                      #   Модульные тесты сервисов
├── vendor/                     #   Composer-зависимости (в .gitignore)
├── composer.json               #   PSR-4 автозагрузка Uchet\ => app/
├── .gitignore
└── README.md                   #   Установка и руководство
```

## Принципы архитектуры

- **MVC + Service Layer + Repository**: контроллеры принимают HTTP и не содержат бизнес-логики;
  сервисы (`app/Services`) реализуют проведение документов, партионный учёт, нумерацию;
  репозитории (`app/Repositories`) — единственное место, где пишутся SQL (только PDO prepared statements).
- **Один front controller** `public/index.php` + ЧПУ через `config/routes.php`.
- **Все таблицы** имеют `created_at`, `updated_at`, `deleted_at` (soft delete).
- Количества — `DECIMAL(15,3)`, суммы — `DECIMAL(15,2)`, генерируемые колонки считают `amount`/`vat_amount`/`diff` на уровне БД.
