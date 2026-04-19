#!/bin/bash
set -e

echo "🔧 [Entrypoint] Fijando permisos de storage/..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "⏳ [Entrypoint] Esperando a MySQL..."
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
  sleep 2
done
echo "✅ [Entrypoint] MySQL listo."

echo "🚀 [Entrypoint] Corriendo migraciones..."
php artisan migrate --force

echo "🧹 [Entrypoint] Limpiando caché de config..."
php artisan config:clear
php artisan cache:clear

echo "✅ [Entrypoint] Iniciando PHP-FPM..."
exec "$@"
