#!/bin/bash
# ============================================================
# Setup de producción — FactuFlash en VPS Ubuntu
# ============================================================

set -e

echo "📦 Verificando dependencias..."

command -v docker >/dev/null 2>&1 || { echo "❌ Docker no instalado. Verificá: curl -fsSL https://get.docker.com | sh"; exit 1; }
command -v docker-compose >/dev/null 2>&1 || { echo "❌ docker-compose no instalado."; exit 1; }

echo "✅ Docker disponible"

# ── 1. Generar APP_KEY si no existe ──
if ! grep -q "APP_KEY=base64:" api/.env 2>/dev/null; then
    echo "🔑 Generando APP_KEY de Laravel..."
    # Se necesita PHP local para esto, si no hay se saltea
    if command -v php >/dev/null 2>&1; then
        cd api && php artisan key:generate --force && cd ..
    else
        echo "⚠️  PHP no disponible en host. Generá la key manualmente:"
        echo "   docker run --rm -v \$(pwd)/api:/var/www/html php:8.2-cli sh -c 'cd /var/www/html && php artisan key:generate'"
    fi
fi

# ── 2. Build de imágenes ──
echo "🔨 Construyendo imágenes Docker..."
docker-compose build --no-cache

# ── 3. Levantar servicios ──
echo "🚀 Levantando servicios..."
docker-compose up -d

# ── 4. Esperar que MySQL esté listo ──
echo "⏳ Esperando MySQL..."
for i in {1..30}; do
    if docker-compose exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; then
        echo "✅ MySQL disponible"
        break
    fi
    echo "   Esperando... ($i/30)"
    sleep 2
done

# ── 5. Migraciones ──
echo "🗄️  Ejecutando migraciones..."
docker-compose exec -T api php artisan migrate --force

echo ""
echo "══════════════════════════════════════════════"
echo "✅ FactuFlash deployado!"
echo ""
echo "   Landing:   http://tu-ip/"
echo "   App:       http://tu-ip/app/"
echo "   API:       http://tu-ip/api/"
echo ""
echo "   Logs:"
echo "   docker-compose logs -f api"
echo "   docker-compose logs -f nginx"
echo "══════════════════════════════════════════════"