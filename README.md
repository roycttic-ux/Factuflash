# 🚀 FactuFlash - Proyecto Final de Carrera

Sistema profesional de facturación y control de stock con actualización automática por dólar.

## 📂 Estructura del Proyecto

```text
/
├── api/            # Backend (Laravel PHP)
├── frontend/       # Frontend Web App (Vue.js + TypeScript)
├── mobile/         # Mobile App (Android/Java)
├── docker/         # Configuración para despliegue
```

## 🛠️ Requisitos
- **PHP 8.1+** y **Composer**
- **Node.js 18+** y **npm**
- **MySQL 8.0+**
- **Android Studio** (para la app móvil)

## 📦 Librerías Principales Utilizadas
- **Backend**: Laravel 10, Sanctum (Autenticación API), GuzzleHTTP (para Web Services de AFIP)
- **Frontend**: Vue 3 (Composition API), Vite, TailwindCSS, Axios, Vue Router, Pinia
- **Mobile**: Java, Android SDK, Gradle

## 🏗️ Arquitectura Técnica Resumida
FactuFlash utiliza una arquitectura **Cliente-Servidor (SPA + API Rest + Mobile App)**:
- **Capa de Presentación Web (Frontend)**: Desarrollada como una Single Page Application (SPA) con Vue.js, la cual se comunica exclusivamente a través de la API REST para obtener y enviar datos.
- **Capa de Presentación Móvil (Mobile App)**: Desarrollada de forma nativa en Android con Java (Aún en desarrollo).
- **Capa de Negocio y Datos (Backend)**: Desarrollada en Laravel (PHP). Expone endpoints protegidos por tokens (Sanctum). Se encarga de la lógica de negocio, integración con los Web Services de ARCA/AFIP (WSAA y WSFEv1) y la persistencia en una base de datos MySQL relacional.

## ⚙️ Variables de Entorno Necesarias
Debes configurar las siguientes variables en tus archivos `.env`. Toma como referencia los archivos `.env.example`.

**Backend (`api/.env`):**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=factuflash
DB_USERNAME=root
DB_PASSWORD=CAMBIAR_EN_LOCAL
AFIP_CUIT=tu_cuit_aqui
```

**Frontend (`frontend/.env`):**
```env
VITE_API_URL=http://localhost:8000/api
```

## 🚀 Cómo instalar dependencias y correr en local

### 1. Backend (Laravel)
Abre una terminal en la carpeta `api/`:
```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```
Esto levantará el backend en `http://localhost:8000`.

### 2. Frontend (Vue.js)
Abre otra terminal en la carpeta `frontend/`:
```bash
cd frontend
npm install
npm run dev
```
Esto levantará el entorno de desarrollo de Vue (usualmente en `http://localhost:5173`).

### 3. Base de Datos
Asegúrate de tener un servidor MySQL local (XAMPP, Laragon, etc.) ejecutándose en el puerto 3306 antes de correr las migraciones del backend.

---
**Desarrollado por Ulises Roig Bonavitta**  
Analista de Sistemas - Escuela Da Vinci 🇦🇷
