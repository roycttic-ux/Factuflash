# FactuFlash Mobile App

Aplicación móvil complementaria para el sistema de facturación FactuFlash, desarrollada nativamente en Android (Java).
Esta aplicación permite a los usuarios acceder rápidamente a información esencial desde sus dispositivos móviles. (Aviso: Aún en fase de desarrollo).

## 🚀 Instrucciones de Instalación y Ejecución

1. **Requisitos Previos:**
   - Tener instalado [Android Studio](https://developer.android.com/studio).
   - Java Development Kit (JDK 17 recomendado).
   - Un dispositivo físico Android (con modo desarrollador y depuración USB habilitados) o un Emulador virtual configurado (AVD).

2. **Abrir el Proyecto:**
   - Abre Android Studio.
   - Selecciona `File > Open...` (o `Open` en la pantalla de inicio).
   - Navega hasta la carpeta `mobile/` dentro del repositorio de FactuFlash y selecciónala.
   - Espera a que Gradle termine de sincronizar las dependencias y compilar el proyecto inicial (esto puede tardar unos minutos la primera vez, requiere conexión a internet).

3. **Configuración de la API (Backend):**
   - Asegúrate de que el backend de FactuFlash (Laravel) esté corriendo y sea accesible desde la misma red local que el dispositivo móvil o emulador.
   - Si utilizas un emulador local, la IP base suele ser `10.0.2.2`. Si usas un dispositivo físico, debes usar la IP de red local de tu computadora (ej: `192.168.1.X`).
   - Verifica los endpoints configurados en el código de la aplicación.

4. **Correr la App:**
   - Selecciona tu dispositivo físico o emulador en el menú desplegable de la barra superior de Android Studio.
   - Haz clic en el botón **Run** (ícono de triángulo verde) o presiona el atajo `Shift + F10`.
   - Android Studio compilará el APK e instalará automáticamente la aplicación en el dispositivo seleccionado.
