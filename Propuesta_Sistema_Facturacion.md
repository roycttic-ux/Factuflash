# INFORME TÉCNICO: SISTEMA INTEGRAL DE GESTIÓN EMPRESARIAL "FACTUFLASH"
**SITUACIÓN: ESPECIFICACIÓN DE REQUISITOS, ARQUITECTURA E INFRAESTRUCTURA**

**RESPONSABLE:** Ulises Roig Bonavitta  
**ROL:** Analista de Sistemas  
**ENTIDAD:** Escuela Da Vinci - Proyecto de Grado  
**FECHA:** 15 de Abril de 2026  
**ESTADO:** Fase de Despliegue en Producción

---

## 1. INTRODUCCIÓN Y JUSTIFICACIÓN DEL PROYECTO

### 1.1. Antecedentes del Problema
En el contexto económico actual de Argentina, las pequeñas y medianas empresas (PyMEs) enfrentan dos desafíos críticos: la obligatoriedad de la facturación electrónica ante ARCA/AFIP y la constante devaluación de la moneda local. Los sistemas de facturación tradicionales operan con precios estáticos, lo que genera una pérdida de rentabilidad debido a la falta de sincronización con la cotización de divisas.

### 1.2. Objetivo General
Diseñar e implementar una plataforma de software escalable que automatice la facturación electrónica bajo normativas fiscales vigentes, integrando un motor de gestión de inventarios con indexación automática de precios basada en variables cambiarias externas bajo el nombre comercial de FactuFlash.

---

## 2. ANÁLISIS DE REQUERIMIENTOS DEL SISTEMA

### 2.1. Requerimientos Funcionales (RF)
| Código | Requerimiento | Descripción Técnica |
| :--- | :--- | :--- |
| **RF-001** | **Gestión Fiscal (WSAA/WSFE)** | Autenticación y solicitud de CAE ante servidores de AFIP mediante protocolos SOAP y certificados X.509. |
| **RF-002** | **Algoritmo Dollar-Linked** | Motor de cálculo que procesa el costo base (USD) contra la cotización spot del día para determinar el precio de venta final. |
| **RF-003** | **Trazabilidad de Inventario** | Registro de movimientos de stock con bitácora de auditoría y niveles de stock de seguridad configurables. |
| **RF-004** | **Pasarela de Cobros** | Integración asíncrona mediante el SDK de Mercado Pago para la recepción de pagos digitales. |

### 2.2. Requerimientos No Funcionales (RNF)
*   **Seguridad:** Implementación de cifrado para el almacenamiento de claves privadas y uso de JWT (Json Web Tokens) para la gestión de sesiones sin estado.
*   **Interoperabilidad:** Desarrollo de una API RESTful que permite el desacoplamiento total entre el núcleo de negocio y el cliente visual.
*   **Performance:** Capacidad instalada para procesar solicitudes concurrentes con latencia inferior a 250ms a nivel de servidor.

---

## 3. ARQUITECTURA TÉCNICA Y STACK TECNOLÓGICO

### 3.1. Backend (Núcleo de Procesamiento)
El servidor opera bajo **Laravel 11** montado sobre **PHP 8.2+**. Se ha seleccionado este framework por su capacidad de abstracción de datos mediante **Eloquent ORM** y su gestión eficiente de colas de procesos (Jobs), crítica para reintentos de facturación ante fallos del organismo fiscal.

### 3.2. Frontend (Interfaz de Gestión)
Se utiliza **Vue.js 3** con el motor de compilación **Vite**. Esta combinación permite una Single Page Application (SPA) que elimina recargas de página innecesarias, optimizando la experiencia de usuario (UX) en procesos de alta carga de datos.

### 3.3. Persistencia de Datos
Se emplea **MySQL 8.0** con un esquema normalizado que incluye:
*   **Segregación de Datos por Usuario:** Multi-tenancy implementado mediante alcances globales (scopes) para garantizar la privacidad de la información.
*   **Cifrado Nativo:** Los campos sensibles (como CUIT o rutas de certificados) se gestionan bajo estándares de cifrado de la aplicación.

---

## 4. INFRAESTRUCTURA Y DESPLIEGUE EN PRODUCCIÓN

El sistema se aloja en una arquitectura de **Servidor Privado Virtual (VPS)** de alta disponibilidad para mitigar riesgos operativos.

### 4.1. Análisis de Recursos de Hardware
*   **CPU:** 4 vCores de arquitectura moderna, optimizados para procesos de cálculo intensivo (cifrado OpenSSL).
*   **Memoria RAM:** 8 GB DDR4 dedicados, permitiendo la ejecución fluida del pool de PHP-FPM y el motor de MySQL.
*   **Almacenamiento:** 75 GB SSD NVMe de alto rendimiento. Se prioriza la baja latencia de disco para operaciones de lectura/escritura de comprobantes.
*   **Ancho de Banda:** 400 Mbit/s simétricos con transferencia ilimitada.

---

## 5. SEGURIDAD Y CONTINUIDAD OPERATIVA (DRP)

### 5.1. Gestión de Certificados y Sesiones Fiscales
El sistema implementa una **Caché de Tokens WSAA** propietaria. Al solicitar un permiso ante AFIP, el token y el firma (Sign) se almacenan de forma segura por un periodo de 12 horas, minimizando las llamadas externas y protegiendo el sistema de caídas del servicio gubernamental.

### 5.2. Plan de Respaldo y Protección
*   **Backups Automatizados:** Respaldo diario integral del sistema y bases de datos fuera del servidor principal.
*   **Protección Anti-DDoS:** Capa de seguridad perimetral para filtrado de tráfico malicioso.
*   **HTTPS Seguro:** Implementación de certificados SSL con cifrado SHA-256.

---

## 6. CONCLUSIÓN DEL REPORTE
El proyecto **FactuFlash** cumple con los estándares rigurosos de ingeniería de software para sistemas de misión crítica. La arquitectura desacoplada, sumada a una infraestructura robusta de 4 núcleos y 8 GB de RAM, garantiza una herramienta estable, potente y preparada para el escalamiento comercial dentro del mercado argentino.

____________________________________________________________________________________________________
**FIN DEL INFORME**
