# Script de Despliegue FactuFlash (Docker VPS - Version Docker Compose V2)

$vpsUser = "ubuntu"
$vpsIp = "TU_HOST_VPS"
$vpsPath = "/var/www/factuflash"

Write-Host "==============================================" -ForegroundColor Cyan
Write-Host "Iniciando Despliegue a $vpsIp" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan

# 1. Preparar el entorno de produccion temporalmente
if (Test-Path "api/.env.local.bak") { Remove-Item "api/.env.local.bak" -Force }
Copy-Item "api/.env" "api/.env.local.bak"
Copy-Item "api/.env.production" "api/.env"
Copy-Item "api/.env.production" ".env"

# 2. Empaquetar archivos
Write-Host "Empaquetando proyecto..." -ForegroundColor Yellow
tar --exclude="node_modules" --exclude="vendor" --exclude=".git" -czf project.tar.gz api frontend docker docker-compose.yml Dockerfile.nginx Dockerfile.frontend .env

# 3. Restaurar entorno local
Move-Item "api/.env.local.bak" "api/.env" -Force
if (Test-Path ".env") { Remove-Item ".env" }

# 4. Subir al VPS
Write-Host "Subiendo al VPS..." -ForegroundColor Yellow
$destination = $vpsUser + "@" + $vpsIp + ":" + $vpsPath + "/"
scp project.tar.gz $destination

# 5. Extraer y Levantar (Usando 'docker compose' sin guion)
Write-Host "Extrayendo y levantando contenedores en el VPS..." -ForegroundColor Yellow
# Nota: Quitamos el 'systemctl start' para no romper el proceso manual
$commands = "cd $vpsPath; tar -xzf project.tar.gz; rm project.tar.gz; sudo docker compose down; sudo docker compose up -d --build"
$vpsTarget = $vpsUser + "@" + $vpsIp
ssh $vpsTarget $commands

# 6. Limpieza local
if (Test-Path "project.tar.gz") { Remove-Item "project.tar.gz" }

Write-Host "==============================================" -ForegroundColor Green
Write-Host "¡Despliegue finalizado!" -ForegroundColor Green
Write-Host "==============================================" -ForegroundColor Green
