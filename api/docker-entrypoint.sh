#!/bin/bash
set -e

echo "🔧 [Entrypoint] Fijando permisos de storage/..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Asegurar estructura de directorios para storage (volumen vacío no tiene mkdir anidado)
mkdir -p /var/www/html/storage/app/public/products
mkdir -p /var/www/html/storage/app/public/logos
mkdir -p /var/www/html/storage/app/public/temp
chown -R www-data:www-data /var/www/html/storage/app
chmod -R 775 /var/www/html/storage/app

echo "🔗 [Entrypoint] Verificando enlace de storage..."
ls -la /var/www/html/public/storage 2>/dev/null || echo "storage link no existe, creando..."
php artisan storage:link --force

echo "⏳ [Entrypoint] Esperando a MySQL ($DB_HOST)..."
while ! php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Exception \$e) { echo \$e->getMessage(); exit(1); }" ; do
  echo "❌ [Entrypoint] MySQL no disponible aún, reintentando..."
  sleep 2
done
echo "✅ [Entrypoint] MySQL listo."

echo "🚀 [Entrypoint] Corriendo migraciones..."
php artisan migrate --force

echo "🧹 [Entrypoint] Limpiando caché de config..."
php artisan config:clear
php artisan cache:clear

echo "📁 [Entrypoint] Estructura final de storage:"
ls -laR /var/www/html/storage/

echo "✅ [Entrypoint] Iniciando PHP-FPM..."
exec "$@"
