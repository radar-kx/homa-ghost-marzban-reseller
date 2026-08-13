#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_DIR"

if [[ ! -f artisan || ! -f composer.json ]]; then
  echo 'ERROR: Run this installer from the extracted project directory.' >&2
  exit 1
fi
command -v php >/dev/null 2>&1 || { echo 'ERROR: PHP CLI is not available.' >&2; exit 1; }
command -v composer >/dev/null 2>&1 || { echo 'ERROR: Composer is not available in cPanel Terminal.' >&2; exit 1; }

PHP_VERSION_ID="$(php -r 'echo PHP_VERSION_ID;')"
if (( PHP_VERSION_ID < 80200 )); then
  echo 'ERROR: PHP 8.2 or newer is required.' >&2
  exit 1
fi
for extension in bcmath ctype curl fileinfo json mbstring openssl pdo pdo_mysql tokenizer xml; do
  php -m | tr '[:upper:]' '[:lower:]' | grep -qx "$extension" || { echo "ERROR: PHP extension $extension is missing." >&2; exit 1; }
done

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo 'A new .env file was created. Set APP_URL and database credentials, then run this installer again.'
  exit 2
fi
if grep -Eq '^(APP_URL=https://panel\.example\.com|DB_PASSWORD=$)' .env; then
  echo 'ERROR: Complete APP_URL and DB settings in .env before installation.' >&2
  exit 2
fi

mkdir -p storage/app/private/receipts storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan key:generate --force
php artisan homa:install
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo 'Homa Ghost installation completed.'
