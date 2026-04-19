# 🚀 Guía de Despliegue FactuFlash (VPS)

Documentación actualizada del sistema de despliegue containerizado.

---

## 🏗 Arquitectura

```
Browser → Nginx (puerto 80)
  /          → Landing HTML estática  (compilada en Dockerfile.nginx)
  /app/*     → Vue App (SPA compilada en Dockerfile.nginx)
  /api/*     → FastCGI proxy → api:9000 (PHP-FPM Laravel)
                          ↓
                    MySQL container
```

### Contenedores Docker
| Contenedor | Imagen | Rol |
|---|---|---|
| `factuflash_nginx` | `Dockerfile.nginx` | Sirve Landing + App compilados, proxea `/api/*` a PHP-FPM |
| `factuflash_api` | `api/Dockerfile` | PHP-FPM 8.2, Laravel 11 |
| `factuflash_mysql` | `mysql:8.0` | Base de datos |

### Archivos clave
| Archivo | Propósito |
|---|---|
| `docker-compose.yml` | Orquesta los 3 contenedores |
| `Dockerfile.nginx` | Multi-stage: compila Vue → copia a Nginx |
| `api/Dockerfile` | Imagen PHP-FPM con extensiones necesarias |
| `docker/nginx/conf.d/default.conf` | Routing Nginx (Landing / App / API) |
| `api/.env.production` | Variables de entorno para producción |
| `deploy_vps.ps1` | Script de deploy automático desde PC local |

---

## 🏁 Deploy desde PC Local

Cada vez que hagas un cambio y quieras subirlo al VPS:

```powershell
# Desde la raíz del proyecto en PowerShell:
.\deploy_vps.ps1
```

El script hace automáticamente:
1. Copia `api/.env.production` → `api/.env` y `.env` (para Docker Compose)
2. Empaqueta el proyecto en `project.tar.gz` (excluye `node_modules`, `vendor`, `.git`)
3. Sube el tar al VPS vía `scp`
4. Se conecta por SSH, extrae el tar y ejecuta `docker compose up -d --build`
5. Restaura el `.env` local de desarrollo

> **Nota**: Al subir el tar, `scp` puede tardar 1-3 minutos según el tamaño del proyecto.

---

## 🌐 Direcciones (Producción)

| Servicio | URL |
|---|---|
| Landing Page | http://TU_HOST_VPS/ |
| Sistema (App) | http://TU_HOST_VPS/app/ |
| API (base) | http://TU_HOST_VPS/api/v1/ |
| Diagnósticos | http://TU_HOST_VPS/api/v1/diagnostics |

---

## 🔧 Comandos de Emergencia (SSH al VPS)

```bash
# Conectarse al VPS
ssh ubuntu@TU_HOST_VPS

# Ver estado de los contenedores
sudo docker compose ps

# Ver logs en tiempo real
sudo docker logs -f factuflash_nginx
sudo docker logs -f factuflash_api
sudo docker logs -f factuflash_mysql

# Reiniciar todo
sudo docker compose down
sudo docker compose up -d --build

# Solo reiniciar un contenedor
sudo docker compose restart nginx
sudo docker compose restart api

# Correr migraciones
sudo docker exec -it factuflash_api php artisan migrate --force

# Entrar a la consola del contenedor API
sudo docker exec -it factuflash_api sh

# Ver las rutas de Laravel
sudo docker exec -it factuflash_api php artisan route:list

# Limpiar caché de Laravel
sudo docker exec -it factuflash_api php artisan config:clear
sudo docker exec -it factuflash_api php artisan cache:clear
```

---

## ⚙️ Variables de Entorno

El archivo `api/.env.production` contiene las credenciales de producción.
**Nunca commitear este archivo con credenciales reales** (ya está en `.gitignore`).

El `deploy_vps.ps1` lo copia automáticamente como `.env` antes de empaquetar.

### Variables importantes
```env
APP_URL=http://TU_HOST_VPS
DB_HOST=mysql         # Nombre del contenedor en la red Docker
DB_DATABASE=fy_facturacion
DB_USERNAME=factuuser
DB_PASSWORD=CAMBIAR_EN_LOCAL
```

---

## 🗺 Cómo Funciona el Routing (Nginx → Laravel)

El archivo `docker/nginx/conf.d/default.conf` usa esta lógica:

- Requests a `/` y rutas de la landing → sirve archivos estáticos desde `/var/www/html/landing`
- Requests a `/app` → sirve el SPA Vue desde `/var/www/html/app`  
- Requests a `/api/*` → **FastCGI proxy** al contenedor `api:9000`
  - `SCRIPT_FILENAME` apunta a `/var/www/html/public/index.php` (dentro del contenedor `api`)
  - `REQUEST_URI` pasa la URL completa para que Laravel 11 la resuelva con su router
  - Laravel recibe `/api/v1/...` y lo matchea con `Route::prefix('v1')` en `routes/api.php`

> **Por qué no hay volumen API en Nginx**: Nginx no ejecuta PHP. Solo hace proxy a `api:9000`
> via la red Docker interna. Los archivos PHP solo están en el contenedor `factuflash_api`.

---

## 🛑 Troubleshooting Común

### API devuelve 404
- Verificar que el contenedor `api` esté corriendo: `sudo docker compose ps`
- Verificar logs: `sudo docker logs factuflash_api`
- Correr migraciones si no se corrieron: `sudo docker exec -it factuflash_api php artisan migrate --force`
- Ir a `/api/v1/diagnostics` para ver el estado de la DB

### API devuelve 502 Bad Gateway
- PHP-FPM no está listo todavía. Esperar 10-20 segundos y reintentar
- O reiniciar: `sudo docker compose restart api`

### Error de DB Connection
- Verificar que el contenedor MySQL esté corriendo
- El `DB_HOST` en `.env` debe ser `mysql` (nombre del contenedor), no `localhost`

### Cambios no se reflejan
- Rebuild necesario: `sudo docker compose up -d --build`
- O limpiar caché: `sudo docker exec -it factuflash_api php artisan config:clear`
