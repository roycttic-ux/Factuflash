# FactuFlash - Proyecto Final de Carrera

Sistema profesional de facturación y control de stock con actualización automática por dólar.

> [!IMPORTANT]  
> **Aviso de separación de repositorios:** El frontend y el backend de este proyecto han sido separados para mejorar la escalabilidad y facilitar los despliegues. Para correr el proyecto completo de manera local, debes clonar y configurar tanto el backend como el frontend por separado, tal como se explica a continuación.

## Repositorios del Proyecto

- **Backend (API):** Laravel 10 (PHP) + MySQL
- **Frontend (Web App):** Vue 3 + Vite + TailwindCSS
- **Mobile App:** Android/Java (En desarrollo)

---

## Cómo instalar y correr en local

> [!WARNING]
> **Importante sobre XAMPP/Apache:** No intentes acceder al sistema entrando a `http://localhost/sistemafacturacion/` en tu navegador. Esto solo mostrará las carpetas del proyecto. Debes levantar un servidor local para la API y otro para el Frontend usando las consolas, y acceder a las URLs que estas te proporcionan (ver pasos a continuación).

Para poder probar el proyecto en tu máquina local, debes seguir estos pasos para iniciar primero la API (Backend) y luego la Web App (Frontend).

> [!IMPORTANT]
> **Ambos servidores deben correr al mismo tiempo:** Necesitas abrir **dos ventanas de terminal diferentes**. En una dejarás corriendo el backend (`php artisan serve`) y en la otra dejarás corriendo el frontend (`npm run dev`). Si falta alguno de los dos, la aplicación no funcionará (arrojará error de conexión al loguearse o registrarse).

### 1. Backend (Laravel API - Servidor Invisible)

El backend requiere PHP 8.1+, Composer y un servidor MySQL en el puerto 3306 (por ejemplo, XAMPP, Laragon, o Docker).

1. **Clona/Descarga el código del backend** y abre una terminal en esa carpeta.
2. Instala las dependencias de PHP:
   ```bash
   composer install
   ```
3. Configura el entorno local:
   ```bash
   cp .env.example .env
   ```
   *Abre el archivo `.env` y asegúrate de que los datos de `DB_DATABASE`, `DB_USERNAME`, etc., coincidan con tu servidor MySQL local.*
4. Genera la clave de la aplicación y corre las migraciones con datos de prueba (Seeders):
   ```bash
   php artisan key:generate
   php artisan migrate --seed
   ```
5. Inicia el servidor de desarrollo local:
   ```bash
   php artisan serve
   ```
   *El backend quedará corriendo en **`http://localhost:8000`**.*
   *(Nota: No necesitas entrar a esta URL en tu navegador, solo debe estar corriendo en la consola para que el frontend pueda conectarse).*

> [!TIP]  
> **¿Prefieres usar Docker para local?**  
> Si tienes Docker instalado y no quieres instalar PHP/MySQL en tu máquina, puedes usar Laravel Sail (si está configurado) o el archivo `docker-compose.local.yml` que se encuentra en la carpeta del backend:
> ```bash
> docker-compose -f docker-compose.local.yml up -d
> ```

---

### 2. Frontend (Vue.js - Lo que usa el usuario)

El frontend requiere Node.js 18+ y npm.

1. **Clona/Descarga el código del frontend** en una carpeta diferente y abre una terminal en ella.
2. Instala las dependencias de Node:
   ```bash
   npm install
   ```
3. Configura el entorno local:
   Crea un archivo `.env` (si no existe, usa `.env.example`) y asegúrate de que apunte a tu backend local:
   ```env
   VITE_API_URL=http://localhost:8000/api
   ```
4. Inicia el entorno de desarrollo:
   ```bash
   npm run dev
   ```
   *Esto levantará el entorno de desarrollo de Vue. **Aquí es donde debes entrar en tu navegador: `http://localhost:5173`***.

---

## Accesos para el Profesor (Evaluación)

Para revisar el proyecto de forma rápida, puedes importar el archivo `base_de_datos.sql` incluido en la raíz de este proyecto directamente en tu phpMyAdmin. Esta base de datos ya incluye datos de prueba y la estructura completa.

**Pasos para importar la base de datos:**
1. Abre tu panel de control de XAMPP e inicia los módulos **Apache** y **MySQL**.
2. Ingresa en tu navegador a `http://localhost/phpmyadmin`.
3. Haz clic en "Nueva" en el menú lateral izquierdo para crear una base de datos y llámala `fy_facturacion`.
4. Selecciona la base de datos recién creada y ve a la pestaña **Importar** en el menú superior.
5. Haz clic en "Seleccionar archivo", elige el archivo `base_de_datos.sql` que se encuentra en la raíz del proyecto y dale al botón "Importar" al final de la página.

Una vez levantado el frontend y el backend (siguiendo los pasos anteriores), puedes ingresar al sistema con el siguiente usuario Administrador que tiene acceso completo a todas las funciones:

- **Usuario:** Profesor
- **Email:** `profe@factuflash.com`
- **Contraseña:** `profe123`

---

## Despliegue a Producción

El proyecto utiliza Docker para los despliegues en producción sobre un servidor VPS. 

> [!NOTE]  
> Si vas a desplegar a producción usando la arquitectura monorepo original, puedes usar los scripts incluidos:

### Acceso al VPS
```bash
ssh ubuntu@TU_HOST_VPS
```
*(Los datos de acceso han sido dejados intencionalmente ya que el repositorio original será privado).*

### Scripts de Despliegue
Puedes ejecutar los siguientes scripts desde PowerShell en Windows:

- **Despliegue Rápido (Actualización):**
  ```powershell
  .\deploy_vps.ps1
  ```
- **Reconstrucción Completa (Lento, sin caché):**
  ```powershell
  .\deploy_vps.ps1 --no-cache
  ```

---

**Desarrollado por Ulises Roig Bonavitta**  
Analista de Sistemas - Escuela Da Vinci
