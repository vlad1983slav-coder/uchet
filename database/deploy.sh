#!/usr/bin/env bash
# Развёртывание БД «с нуля» через mysql-клиент (альтернатива migrate.php).
# Использование:  DB_USER=root DB_PASS=secret ./database/deploy.sh
set -euo pipefail

DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-uchet}"

DIR="$(cd "$(dirname "$0")" && pwd)"

if [ -n "$DB_PASS" ]; then
    MYSQL=(mysql -u "$DB_USER" -p"$DB_PASS")
else
    MYSQL=(mysql -u "$DB_USER")
fi

"${MYSQL[@]}" < "$DIR/schema.sql"
"${MYSQL[@]}" "$DB_NAME" < "$DIR/seeds.sql"

# инкрементальные миграции, если есть
for f in "$DIR"/migrations/*.sql; do
    [ -e "$f" ] || continue
    echo "Применяю $f"
    "${MYSQL[@]}" "$DB_NAME" < "$f"
done

echo "БД $DB_NAME развёрнута. Логины: admin/admin123, sklad/sklad123, manager/manager123"
