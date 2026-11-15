#!/bin/bash
set -euo pipefail
cd /var/www/html
mkdir -p storage/app/public/{products,logos,temp} storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache
php artisan config:clear
php artisan storage:link --force
ready=0
for i in $(seq 1 30); do
  if php -r 'try {new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: "3306").";dbname=".getenv("DB_DATABASE"),getenv("DB_USERNAME"),getenv("DB_PASSWORD"));} catch (Throwable $e) {exit(1);}'; then ready=1; break; fi
  sleep 2
done
[ "$ready" = 1 ] || { echo "Database unavailable"; exit 1; }
php artisan config:cache
php artisan route:cache
exec "$@"
