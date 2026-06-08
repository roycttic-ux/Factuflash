#!/bin/bash
# deploy_remote.sh - Se ejecuta EN el VPS, con nohup
set -e
LOG=/tmp/deploy.log
VPSDIR=~/sistemafacturacion
NO_CACHE="${1:-}"

echo "=== [$(date)] Iniciando deploy ===" | tee $LOG

cd "$VPSDIR"

echo "=== Extrayendo archivos ===" | tee -a $LOG
tar -xzf project.tar.gz && rm project.tar.gz

echo "=== Bajando contenedores ===" | tee -a $LOG
docker-compose down 2>&1 | tee -a $LOG

echo "=== Construyendo imágenes ===" | tee -a $LOG
docker-compose build $NO_CACHE 2>&1 | tee -a $LOG

echo "=== Levantando contenedores ===" | tee -a $LOG
docker-compose up -d 2>&1 | tee -a $LOG

echo "=== Esperando que MySQL esté listo (hasta 60s) ===" | tee -a $LOG
for i in $(seq 1 12); do
  if docker-compose exec -T api php artisan db:monitor 2>/dev/null; then
    echo "MySQL OK en intento $i" | tee -a $LOG
    break
  fi
  echo "  Intento $i/12 - esperando 5s..." | tee -a $LOG
  sleep 5
done

echo "=== Ejecutando migraciones ===" | tee -a $LOG
docker-compose exec -T api php artisan migrate --force 2>&1 | tee -a $LOG

echo "=== Limpiando cache de Laravel ===" | tee -a $LOG
docker-compose exec -T api php artisan config:cache 2>&1 | tee -a $LOG
docker-compose exec -T api php artisan route:cache  2>&1 | tee -a $LOG

echo "" | tee -a $LOG
echo "DEPLOY_OK - $(date)" | tee -a $LOG
