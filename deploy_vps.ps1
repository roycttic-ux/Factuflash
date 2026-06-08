# ============================================================
# Script de Despliegue FactuFlash - v2
# Uso:
#   .\deploy_vps.ps1              → build incremental (rápido)
#   .\deploy_vps.ps1 --no-cache  → rebuild total (cuando cambiás Dockerfile)
# ============================================================

$vpsUser = "ubuntu"
$vpsIp   = "TU_HOST_VPS"
$vpsPath = "~/sistemafacturacion"
$noCache = $args -contains "--no-cache"

Write-Host "==============================================" -ForegroundColor Cyan
Write-Host "  Iniciando Despliegue a $vpsIp" -ForegroundColor Cyan
if ($noCache) {
    Write-Host "  MODO: Rebuild total (--no-cache)" -ForegroundColor Red
} else {
    Write-Host "  MODO: Build incremental (rápido)" -ForegroundColor Green
}
Write-Host "==============================================" -ForegroundColor Cyan

# ── 1. Swap .env a producción ────────────────────────────────────────────────
if (Test-Path "api/.env.local.bak") { Remove-Item "api/.env.local.bak" -Force }
Copy-Item "api/.env"            "api/.env.local.bak"
Copy-Item "api/.env.production" "api/.env"
Copy-Item "api/.env.production" ".env"

# ── 2. Empaquetar ───────────────────────────────────────────────────────────
Write-Host "`n[1/5] Empaquetando proyecto..." -ForegroundColor Yellow
tar --exclude="*node_modules*" `
    --exclude="*vendor*" `
    --exclude="*.git*" `
    --exclude="*api/public/downloads*" `
    --exclude="*.log" `
    --exclude="*api/storage/logs*" `
    -czf project.tar.gz `
    api frontend docker docker-compose.yml Dockerfile.nginx Dockerfile.frontend .env

$packOk = $LASTEXITCODE -eq 0

# ── 3. Restaurar .env local (siempre) ────────────────────────────────────────
Move-Item "api/.env.local.bak" "api/.env" -Force
if (Test-Path ".env") { Remove-Item ".env" }

if (-not $packOk) {
    Write-Host "ERROR: Falló el empaquetado." -ForegroundColor Red
    exit 1
}

# ── 4. Subir proyecto + script remoto al VPS ─────────────────────────────────
Write-Host "[2/5] Subiendo archivos al VPS..." -ForegroundColor Yellow
scp project.tar.gz deploy_remote.sh "${vpsUser}@${vpsIp}:${vpsPath}/"
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Falló la transferencia SCP." -ForegroundColor Red
    Remove-Item "project.tar.gz" -ErrorAction SilentlyContinue
    exit 1
}
Remove-Item "project.tar.gz" -ErrorAction SilentlyContinue

# ── 5. Lanzar el build en background (nohup) ─────────────────────────────────
Write-Host "[3/5] Lanzando build en background en el VPS..." -ForegroundColor Yellow
$cacheFlag = if ($noCache) { "--no-cache" } else { "" }
# nohup asegura que el proceso siga corriendo aunque SSH se corte
ssh "${vpsUser}@${vpsIp}" "chmod +x ${vpsPath}/deploy_remote.sh && nohup bash ${vpsPath}/deploy_remote.sh $cacheFlag > /tmp/deploy.log 2>&1 & echo `$!"
Write-Host "Build lanzado. Esperando que termine..." -ForegroundColor DarkGray

# ── 6. Polling: mostrar progreso hasta que termine ───────────────────────────
Write-Host "[4/5] Monitoreando progreso..." -ForegroundColor Yellow
$timeout  = 420   # 7 minutos máximo
$elapsed  = 0
$interval = 12    # revisar cada 12s
$done     = $false
$lastShown = ""

while ($elapsed -lt $timeout) {
    Start-Sleep -Seconds $interval
    $elapsed += $interval

    $lastLine = (ssh "${vpsUser}@${vpsIp}" "tail -1 /tmp/deploy.log 2>/dev/null") 2>$null
    
    if ($lastLine -and $lastLine -ne $lastShown) {
        $color = if ($lastLine -match "===") { "Cyan" } elseif ($lastLine -match "error|Error|ERROR|fail") { "Red" } else { "DarkGray" }
        Write-Host "  [$elapsed s] $lastLine" -ForegroundColor $color
        $lastShown = $lastLine
    }

    if ($lastLine -match "DEPLOY_OK") {
        $done = $true
        break
    }
    if ($lastLine -match "\bERROR\b|FAILED|Exception") {
        Write-Host "`nERROR en el deploy remoto. Log completo:" -ForegroundColor Red
        ssh "${vpsUser}@${vpsIp}" "cat /tmp/deploy.log"
        exit 1
    }
}

if (-not $done) {
    Write-Host "`nTIMEOUT ($timeout s). El build todavía puede estar corriendo en el VPS." -ForegroundColor Red
    Write-Host "Revisá el log con:" -ForegroundColor Yellow
    Write-Host "  ssh ${vpsUser}@${vpsIp} 'tail -f /tmp/deploy.log'" -ForegroundColor White
    exit 1
}

# ── 7. Verificación final ─────────────────────────────────────────────────────
Write-Host "[5/5] Verificando que los contenedores estén corriendo..." -ForegroundColor Yellow
ssh "${vpsUser}@${vpsIp}" "cd ${vpsPath} && docker-compose ps"

Write-Host ""
Write-Host "==============================================" -ForegroundColor Green
Write-Host "  Deploy finalizado con exito!" -ForegroundColor Green
Write-Host "==============================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Log: ssh ${vpsUser}@${vpsIp} 'cat /tmp/deploy.log'" -ForegroundColor DarkGray
