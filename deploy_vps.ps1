# 🚀 Script de Despliegue FactuFlash (Docker VPS - Optimizado)

$vpsUser = "ubuntu"
$vpsIp = "TU_HOST_VPS"
$vpsPath = "/var/www/factuflash"

Write-Host "==============================================" -ForegroundColor Cyan
Write-Host "🚀 Iniciando Despliegue a $vpsIp" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan

# 1. Preparar el entorno de producción temporalmente
Write-Host "🔧 Preparando configuración de producción..." -ForegroundColor Yellow
Copy-Item "api/.env" "api/.env.local.bak" # Backup local
Copy-Item "api/.env.production" "api/.env"  # Poner el de producción para el tar

# 2. Empaquetar archivos
Write-Host "📦 Empaquetando proyecto (esto puede tardar un momento)..." -ForegroundColor Yellow
tar --exclude="node_modules" --exclude="vendor" --exclude=".git" --exclude="api/storage/logs/*.log" -czf project.tar.gz api frontend docker docker-compose.yml Dockerfile.frontend

# 3. Restaurar entorno local INMEDIATAMENTE
Write-Host "🏠 Restaurando entorno local..." -ForegroundColor Gray
Move-Item "api/.env.local.bak" "api/.env" -Force

# 4. Subir al VPS
Write-Host "📤 Subiendo al VPS..." -ForegroundColor Yellow
scp project.tar.gz "${vpsUser}@${vpsIp}:${vpsPath}/"

# 5. Extraer y Levantar en el Servidor
Write-Host "🛠️ Extrayendo y reiniciando contenedores en el VPS..." -ForegroundColor Yellow
$sshCmd = "cd $vpsPath && tar -xzf project.tar.gz && rm project.tar.gz && sudo docker-compose down && sudo docker-compose up -d --build"
ssh "${vpsUser}@${vpsIp}" $sshCmd

# 6. Limpieza local
if (Test-Path "project.tar.gz") { Remove-Item "project.tar.gz" }

Write-Host "==============================================" -ForegroundColor Green
Write-Host "✅ ¡Despliegue completado! Tu local sigue intacto." -ForegroundColor Green
Write-Host "==============================================" -ForegroundColor Green
