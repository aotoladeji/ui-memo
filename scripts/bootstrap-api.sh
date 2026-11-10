#!/usr/bin/env bash
# Turns ./api (our overlay files) into a complete, runnable Laravel project. Run once.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; API="$ROOT/api"

need() { command -v "$1" >/dev/null 2>&1 || { echo "Missing: $1. $2"; exit 1; }; }
need php "Install PHP 8.2 or newer with extensions: mbstring, xml, curl, mysql (pdo_mysql), zip, fileinfo."
need composer "Install Composer from https://getcomposer.org"
need perl "Perl is used for small config edits (preinstalled on macOS/Linux; Windows: use WSL)."
php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' || { echo "PHP 8.2+ required."; exit 1; }

if [ -f "$API/artisan" ]; then echo "api/ is already a Laravel project. Nothing to do."; exit 0; fi

TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
echo "==> Creating Laravel skeleton"
composer create-project laravel/laravel "$TMP/lv" --no-interaction --prefer-dist

echo "==> Merging skeleton under our files (our files win)"
cp -rn "$TMP/lv/." "$API/"
cd "$API"

echo "==> Installing Sanctum (API tokens)"
php artisan install:api --without-migration-prompt --no-interaction

echo "==> Removing migrations replaced by database/schema/memo_system.sql"
rm -f database/migrations/*_create_users_table.php database/migrations/*_create_personal_access_tokens_table.php
# Laravel's default UserFactory and example tests assume its own users table.
rm -f database/factories/UserFactory.php tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php

setenv() { # key value
  if grep -qE "^#?[[:space:]]*$1=" .env; then perl -pi -e "s|^#?\s*$1=.*|$1=$2|" .env; else echo "$1=$2" >> .env; fi
}
echo "==> Writing .env"
setenv APP_NAME '"UI Memo"'
setenv APP_URL http://localhost:8000
setenv DB_CONNECTION mysql
setenv DB_HOST 127.0.0.1
setenv DB_PORT 3306
setenv DB_DATABASE ui_memo
setenv DB_USERNAME ui_memo
setenv DB_PASSWORD ui_memo_pw
setenv SESSION_DRIVER file
setenv CACHE_STORE file
setenv QUEUE_CONNECTION database

echo "==> Pointing PHPUnit at MySQL (our SQL is MySQL-specific: JSON_CONTAINS, ENUM)"
perl -0pi -e 's/<!--\s*<env name="DB_[A-Z]+"[^>]*>\s*-->//g; s/<env name="DB_(CONNECTION|DATABASE|HOST|PORT|USERNAME|PASSWORD)"[^>]*\/>\s*//g' phpunit.xml
perl -0pi -e 's#</php>#    <env name="DB_CONNECTION" value="mysql" force="true"/>\n        <env name="DB_HOST" value="127.0.0.1" force="true"/>\n        <env name="DB_PORT" value="3306" force="true"/>\n        <env name="DB_DATABASE" value="ui_memo_test" force="true"/>\n        <env name="DB_USERNAME" value="ui_memo" force="true"/>\n        <env name="DB_PASSWORD" value="ui_memo_pw" force="true"/>\n    </php>#' phpunit.xml

echo "==> Done. Next: make mysql && make db   (see docs/LAUNCH.md)"
