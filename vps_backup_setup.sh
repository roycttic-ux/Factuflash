#!/bin/bash
# Script para configurar backups automáticos en el VPS

BACKUP_SCRIPT_PATH="/home/ubuntu/sistemafacturacion/backup_db.sh"
CRON_JOB="0 3 * * * /bin/bash $BACKUP_SCRIPT_PATH >> /home/ubuntu/sistemafacturacion/backup.log 2>&1"

echo "=============================================="
echo "🛡️ CONFIGURANDO BACKUPS AUTOMÁTICOS FACTUFLASH"
echo "=============================================="

# 1. Crear el script de backup
cat << 'EOF' > $BACKUP_SCRIPT_PATH
#!/bin/bash
# Ejecuta el backup de la base de datos de Docker

BACKUP_DIR="/home/ubuntu/backups_bd"
CONTAINER_NAME="factuflash_mysql"
DB_USER="root"
DB_PASS="FactuFlash2026!RootSegura"
DB_NAME="sistemafacturacion"
DATE=$(date +"%Y-%m-%d_%H-%M-%S")
BACKUP_FILE="$BACKUP_DIR/factuflash_${DATE}.sql.gz"

# Crear carpeta si no existe
mkdir -p "$BACKUP_DIR"

echo "[$(date)] Iniciando backup de $DB_NAME..."

# Hacer dump y comprimir al vuelo
docker exec $CONTAINER_NAME /usr/bin/mysqldump -u$DB_USER -p$DB_PASS $DB_NAME | gzip > "$BACKUP_FILE"

if [ ${PIPESTATUS[0]} -eq 0 ]; then
    echo "[$(date)] ✅ Backup exitoso: $BACKUP_FILE"
else
    echo "[$(date)] ❌ Error al hacer el backup."
    rm -f "$BACKUP_FILE"
fi

# Borrar backups de más de 7 días para no llenar el disco
find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +7 -exec rm {} \;
echo "[$(date)] 🧹 Limpieza de backups antiguos lista."
EOF

# Darle permisos de ejecución
chmod +x $BACKUP_SCRIPT_PATH
echo "✅ Script de backup creado en: $BACKUP_SCRIPT_PATH"

# 2. Agregar al Cron (si no existe ya)
(crontab -l 2>/dev/null | grep -v "$BACKUP_SCRIPT_PATH"; echo "$CRON_JOB") | crontab -
echo "✅ Tarea programada (Cron) configurada para las 3:00 AM todos los días."

# 3. Ejecutar el primer backup de prueba
echo "🚀 Ejecutando un backup de prueba AHORA..."
/bin/bash $BACKUP_SCRIPT_PATH

echo "=============================================="
echo "🛡️ ¡SISTEMA BLINDADO! Tus backups están listos."
echo "Puedes verlos con: ls -lh /home/ubuntu/backups_bd/"
echo "=============================================="
