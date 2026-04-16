# 🚀 FactuFlash - Proyecto Final de Carrera

Sistema profesional de facturación y control de stock con actualización automática por dólar.

## 📂 Estructura del Proyecto

```text
/
├── assets/         # Recursos estáticos (CSS, JS, Imágenes)
├── config/         # Configuraciones de Base de Datos y APIs
├── public/         # Punto de entrada (index.php) y archivos públicos
├── src/            # Lógica de negocio (Controladores, Modelos, Servicios)
├── sql/            # Scripts de creación de base de datos
├── views/          # Plantillas de la interfaz de usuario
└── uploads/        # Carpeta para logos y fotos de productos
```

## 🛠️ Requisitos
- **PHP 8.1+**
- **MySQL 8.0+**
- **XAMPP / Laragon**

## 🚀 Instalación Rápida

1.  **Base de Datos**: Importa el archivo `sql/schema.sql` en tu gestor de base de datos (phpMyAdmin).
2.  **Configuración**: Edita el archivo `config/database.php` con tus credenciales locales.
3.  **Servidor**: Asegúrate de que el documento root de tu servidor apunte a la carpeta `public/` o accede vía `localhost/Sistema facturacion/public`.

## ✨ Características Principales
- **Plan Único**: Suscripción mensual sencilla sin límites.
- **Dollar-Sync**: Precios de productos vinculados al dólar para protección inflacionaria.
- **Web & Mobile**: Gestión en escritorio y Dashboards rápidos en App.
- **Seguridad**: Autenticación vía Auth0 e integración con MercadoPago.

---
**Desarrollado por Ulises Roig Bonavitta**  
Analista de Sistemas - Escuela Da Vinci 🇦🇷
