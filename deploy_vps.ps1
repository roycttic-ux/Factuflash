param([switch]$ValidateOnly, [string]$VpsHost = $env:VPS_HOST)
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
$release = Get-Date -Format 'yyyyMMdd-HHmmss'
$archive = "artifacts/release-$release.tar.gz"
New-Item -ItemType Directory -Force artifacts | Out-Null
& tar '--exclude=*/node_modules' '--exclude=*/vendor' '--exclude=*/.git' '--exclude=*/dist' '--exclude=*/.env*' '--exclude=*/.phpunit.result.cache' '--exclude=api/storage' '--exclude=api/bootstrap/cache/*.php' '--exclude=api/database/*.sqlite' '--exclude=api/public/storage' '--exclude=api/public/downloads' '--exclude=*.log' '--exclude=*.sql' -czf $archive api frontend docker/nginx/conf.d/default.conf docker-compose.yml Dockerfile.nginx Dockerfile.frontend .dockerignore deploy_release.sh
if ($LASTEXITCODE -ne 0) { throw 'Falló el empaquetado.' }
$entries = & tar -tzf $archive
if ($entries | Where-Object { $_ -match '(^|/)\.env($|\.)|(^|/)storage/|\.key$|\.pem$|\.crt$' }) { throw 'El paquete contiene archivos privados; no se subirá.' }
Write-Host "Paquete validado: $archive"
if ($ValidateOnly) { return }
if ([string]::IsNullOrWhiteSpace($VpsHost)) { throw 'Define VPS_HOST o proporciona -VpsHost antes de desplegar.' }
$destination = "ubuntu@${VpsHost}:/home/ubuntu/sistemafacturacion/"
& scp -O -l 128 -o IPQoS=none -o ConnectTimeout=15 -o ServerAliveInterval=10 -o BatchMode=yes $archive deploy_release.sh $destination
if ($LASTEXITCODE -ne 0) { throw 'Falló la transferencia.' }
& ssh -o BatchMode=yes "ubuntu@$VpsHost" "cd /home/ubuntu/sistemafacturacion || exit 1; nohup bash deploy_release.sh release-$release.tar.gz > /tmp/factuflash-$release.log 2>&1 < /dev/null &"
if ($LASTEXITCODE -ne 0) { throw 'Falló el inicio del despliegue.' }
Write-Host "Log remoto: /tmp/factuflash-$release.log. Verificar DEPLOY_OK antes de darlo por terminado."
