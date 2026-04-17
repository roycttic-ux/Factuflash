# 🚀 Script de Despliegue FactuFlash (Docker VPS)

$vpsUser = "ubuntu"
$vpsIp = "TU_HOST_VPS"
$vpsPath = "/var/www/factuflash"

Write-Host "==============================================" -ForegroundColor Cyan
Write-Host "🚀 Iniciando Despliegue a $vpsIp" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan

# 1. Empaquetar archivos (excluyendo basura)
Write-Host "📦 Empaquetando proyecto..." -ForegroundColor Yellow
$excludeList = @("node_modules", "vendor", ".git", "storage/*.log", "frontend/node_modules")
tar --exclude="node_modules" --exclude="vendor" --exclude=".git" --exclude="frontend/node_modules" -czf project.tar.gz api frontend docker docker-compose.yml Dockerfile.frontend .env

# 2. Subir al VPS
Write-Host "📤 Subiendo al VPS..." -ForegroundColor Yellow
scp project.tar.gz "${vpsUser}@${vpsIp}:${vpsPath}/"

# 3. Extraer y Levantar
Write-Host "🛠️ Extrayendo y reiniciando contenedores..." -ForegroundColor Yellow
$sshCmd = "cd $vpsPath && tar -xzf project.tar.gz && rm project.tar.gz && sudo docker-compose down && sudo docker-compose up -d --build"
ssh "${vpsUser}@${vpsIp}" $sshCmd

# 4. Limpieza local
Remove-Item project.tar.gz

Write-Host "==============================================" -ForegroundColor Green
Write-Host "✅ ¡Despliegue completado!" -ForegroundColor Green
Write-Host "==============================================" -ForegroundColor Green
