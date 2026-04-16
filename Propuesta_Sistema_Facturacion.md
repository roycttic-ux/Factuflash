# 🚀 FacturaYa: Sistema de Facturación y Stock Pro
**Propuesta de Proyecto - Trabajo Final de Carrera (Analista de Sistemas)**
*Versión 2.0 (Edición Optimizada)*

---

## 📄 Resumen Ejecutivo
**FacturaYa** es una solución integral diseñada para pequeños y medianos emprendimientos que buscan simplificar su gestión diaria. A diferencia de los sistemas tradicionales basados en múltiples planes complejos y confusos, FacturaYa ofrece un **Plan Único de Suscripción**, brindando acceso a todas las funcionalidades premium por una tarifa plana mensual. El sistema integra el control de inventario con una actualización automática de precios en tiempo real basada en la cotización del dólar, protegiendo al comerciante de la inflación y fluctuaciones cambiarias.

---

## 💎 Propuesta de Valor (Plan Único & Transparente)

> [!IMPORTANT]
> **Sin Sorpresas, Sin Planes "Bronze" o "Platinum":** Un solo precio mensual para tener todo el poder del sistema, sin límites de facturas o usuarios.

1.  **Plan Único & Transparente:** Sin límites y con todas las funciones Pro desde el día uno.
2.  **Identidad de Marca Premium:** Facturas diseñadas estéticamente con el logotipo de la empresa, elevando el nivel profesional del PYME ante sus clientes.
3.  **Protección Cambiaria:** Precios de stock vinculados en tiempo real a la API del dólar.
3.  **Multicanalidad:** Gestión total desde la web y consulta rápida de métricas desde la App Mobile.

---

## 🚀 Mejoras Sugeridas & Diferenciales (Próximas Versiones)

Para elevar el valor comercial del producto y facilitar la venta, se sugieren las siguientes implementaciones:

*   **🛒 Envío Directo por WhatsApp:** Al generar la factura, enviarla automáticamente al cliente mediante un enlace o PDF por WhatsApp Web.
*   **💵 Suscripción Dollar-Linked:** Para mantener la rentabilidad frente a la inflación, la tarifa mensual del software puede estar anclada al valor del dólar oficial/blue en el día del pago.
*   **🔔 Alertas de Reposición Inteligentes:** Notificaciones al móvil cuando el stock caiga por debajo de un umbral crítico definido por el usuario.
*   **📅 Reporte de Cierre de Mes Automatizado:** Envío de un PDF resumen con las ganancias y el estado de stock directamente al correo del dueño.
*   **🆓 Período de Prueba (Free Trial):** Ofrecer 7 días de acceso completo sin costo para reducir la fricción en la primera venta.

---

## ⚙️ Stack Tecnológico (Modern & Scalable)

| Capa | Tecnología | Función |
| :--- | :--- | :--- |
| **Backend** | ![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white) | Lógica de negocio robusta. |
| **Frontend Web** | ![Tailwind](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=flat-square&logo=tailwind-css&logoColor=white) | Interfaz moderna y ultra-sensible. |
| **Mobile** | ![Kotlin](https://img.shields.io/badge/Kotlin-0095D5?style=flat-square&logo=kotlin&logoColor=white) | Rendimiento nativo para reportes. |
| **Base de Datos** | ![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white) | Almacenamiento seguro y optimizado. |
| **Autenticación** | ![Auth0](https://img.shields.io/badge/Auth0-EB5424?style=flat-square&logo=auth0&logoColor=white) | Seguridad nivel empresarial para usuarios. |
| **Pagos & APIs** | MercadoPago, API Dólar, Google Captcha | Integración con el Plan Único de suscripción. |

---

## 📊 Alcance del Proyecto

### 🌐 Plataforma Web (Gestión Administrativa)
*   **Plan Único Todo Incluido:** Sin límites de carga de productos o facturas.
*   **ABM de Productos:** Carga masiva y edición de stock.
*   **Facturación Inteligente:** Generación instantánea de comprobantes.
*   **Dashboards:** Estadísticas avanzadas y métricas de rentabilidad.
*   **Pasarela de Pago:** Suscripciones recurrentes automáticas con MercadoPago.

### 📱 Aplicación Mobile (Consulta en Campo)
*   **Historial de Ventas:** Acceso rápido a facturas emitidas.
*   **Monitor de Precios:** Consulta de precios recomendados según el dólar actual.
*   **Alertas de Stock:** Notificaciones push de falta de existencias.

---

## ⚔️ Análisis Comparativo de Mercado

| Competidor | Modelo | Ventaja FacturaYa |
| :--- | :--- | :--- |
| **Colppy** | Planes Complejos (Graduales) | **Simplicidad:** Un solo plan con todas las funciones. |
| **Contagram** | Sin App Mobile | **Movilidad:** Acceso total desde la app propia. |
| **Xero** | Interfaz Compleja (Inglés) | **Simplicidad:** Totalmente en español y fácil de usar. |

---

## 🛤️ Roadmap de Implementación

```mermaid
graph LR
    A[Fase 1: Core] --> B[Fase 2: APIs]
    B --> C[Fase 3: Mobile]
    C --> D[Lanzamiento]
    
    style A fill:#f9f,stroke:#333,stroke-width:2px
    style D fill:#bbf,stroke:#333,stroke-width:4px
```

1.  **Fundamentos:** Modelado de base de datos y lógica de stock en PHP.
2.  **Conectividad:** Integración de MercadoPago y API Dólar.
3.  **Movilidad:** Desarrollo de la vista de reportes en Kotlin.
4.  **Polish:** Pruebas de seguridad y despliegue final.

---

> [!TIP]
> **Siguiente Paso:** Implementación de un ORM (como *Eloquent*) para agilizar la manipulación de datos y mejorar la mantenibilidad del código.
