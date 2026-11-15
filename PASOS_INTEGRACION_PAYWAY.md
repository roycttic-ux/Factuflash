# Checklist: integrar Payway (Prisma) con FactuFlash

Este documento sirve para preparar la segunda integración automática de FactuFlash. El objetivo es que el vendedor envíe el importe desde FactuFlash a una terminal Payway, el cliente pague en el posnet y el resultado aprobado o rechazado vuelva al sistema.

## 1. Confirmar que Payway permite la integración que necesitamos

Contactar a Payway y pedir **POS Integrado presencial / tarjeta presente / integración con sistema de caja**.

No pedir solamente un checkout para tienda online. El checkout web no garantiza que se pueda enviar el importe a una terminal física.

Canales de referencia:

- [Portal técnico de Payway](https://docs.payway.com.ar/)
- [Portal de Ventas Online Payway](https://developers-ventasonline.payway.com.ar/sac/SAC)
- [Información de API y SDK](https://www.payway.com.ar/vender-online)
- [SDK PHP de Payway](https://github.com/payway-ar/sdk-php-ventaonline)

## 2. Mensaje para enviar a Payway

Copiar y enviar este texto a soporte técnico o al área comercial:

> Hola. Somos FactuFlash, un sistema argentino de facturación y punto de venta. Queremos integrar terminales Payway físicas para que el sistema envíe el importe, el cliente pague en el POS y Payway devuelva el resultado aprobado o rechazado.
>
> Necesitamos documentación de POS Integrado para tarjeta presente, modelos de terminal compatibles, SDK/API, ambiente sandbox, credenciales de prueba, requisitos de homologación y condiciones para operar en producción.
>
> También queremos saber si existe OAuth u otro mecanismo para que cada comercio autorice su propia cuenta sin compartir sus claves con nuestra plataforma.

## 3. Preguntas que deben responder

- ¿Existe una API o SDK para enviar una venta a una terminal física?
- ¿Qué modelos de posnet son compatibles?
- ¿La conexión es por USB, Wi-Fi, red local o nube?
- ¿Se puede consultar el estado de una operación?
- ¿Existe webhook o callback para recibir aprobado, rechazado, cancelado y timeout?
- ¿Se puede cancelar una operación enviada a la terminal?
- ¿Existe una clave de idempotencia para evitar cobros duplicados?
- ¿Hay ambiente sandbox?
- ¿Hay terminal de prueba?
- ¿Qué proceso de homologación exige Payway?
- ¿Qué datos identifican al comercio, sucursal, caja y terminal?
- ¿Se puede usar OAuth para varios comercios?
- ¿Qué costo tiene la integración u homologación?
- ¿La habilitación es para cada comercio o para FactuFlash como plataforma/partner?

## 4. Datos que hay que conseguir

Guardar la respuesta de Payway y reunir:

- nombre legal del proveedor y contacto técnico;
- documentación de la integración presencial;
- URL base de sandbox;
- URL base de producción;
- API Key pública, si corresponde;
- API Key privada, si corresponde;
- client ID y client secret, si existe OAuth;
- número de comercio de prueba;
- número de comercio de producción;
- ID de sucursal;
- ID de caja;
- ID o número de terminal;
- modelos compatibles;
- formato de monto y moneda;
- formato de respuesta de aprobado y rechazado;
- documentación de webhook;
- requisitos de seguridad y homologación;
- teléfonos o correos de soporte durante las pruebas.

No guardar claves reales en este archivo, en el repositorio ni en el frontend. Las credenciales deben almacenarse en variables de entorno o en un gestor de secretos del backend.

## 5. Qué debe hacer el comercio

El comercio que pruebe la integración debe:

1. Tener una cuenta Payway activa.
2. Tener habilitados pagos presenciales.
3. Tener número de comercio y terminal compatible.
4. Solicitar a Payway la modalidad POS Integrado.
5. Autorizar a FactuFlash mediante OAuth o entregar credenciales de sandbox.
6. Asociar la terminal a una sucursal y caja.
7. Realizar ventas de prueba aprobadas y rechazadas.
8. Completar la homologación solicitada por Payway.
9. Solicitar credenciales de producción.

## 6. Qué se implementará en FactuFlash

Cuando Payway entregue la documentación, agregar un conector independiente:

- `PaywayProvider` en el backend;
- conexión de credenciales por comercio;
- selección de terminal;
- envío del importe desde la factura pendiente;
- identificador único e idempotencia;
- consulta de estado;
- recepción y validación del webhook;
- confirmación solo cuando Payway informe aprobado;
- registro de autorización, lote, terminal y referencia;
- manejo de rechazado, cancelado, vencido y timeout;
- reintento seguro sin duplicar el cobro;
- pruebas automáticas con respuestas simuladas.

El flujo de usuario será:

```text
Factura pendiente
→ Cobrar con Payway
→ FactuFlash envía el importe
→ Cliente paga en la terminal
→ Payway responde aprobado/rechazado
→ FactuFlash confirma o mantiene pendiente la factura
```

## 7. Pruebas necesarias antes de producción

- Pago aprobado.
- Pago rechazado.
- Pago cancelado desde la terminal.
- Terminal desconectada.
- Timeout de la API.
- Webhook repetido.
- Botón presionado dos veces.
- Mismo número de autorización en dos facturas.
- Importe incorrecto.
- Credencial vencida o inválida.
- Factura de otro comercio.
- Reconsulta de una operación después de cerrar el navegador.
- Reintegro o devolución, si Payway lo expone en la API.

## 8. Criterio para decidir si Payway sirve

Payway es adecuado para esta integración solo si confirma por escrito que ofrece:

1. envío del importe a terminal física;
2. respuesta verificable de aprobado o rechazado;
3. consulta de estado;
4. ambiente de prueba;
5. terminal compatible;
6. homologación para el sistema integrador.

Si solo ofrece API de checkout online, no alcanza para el flujo de supermercado. En ese caso se mantiene el registro manual para Payway y se prioriza Getnet Get POS Integrado.

## 9. Qué hacer el día que se retome

- Abrir este archivo.
- Contactar a Payway con el mensaje de la sección 2.
- Guardar la respuesta y la documentación recibida en una carpeta privada fuera del repositorio.
- Anotar proveedor, modelo y modalidad exacta de la terminal.
- No programar contra producción ni usar credenciales reales hasta tener sandbox.
- Compartir conmigo la documentación técnica, sin enviar claves privadas.

## Estado actual de FactuFlash

- Mercado Pago Point: integración automática implementada en código; requiere cuenta, terminal PDV, credenciales y webhook HTTPS para probar en producción.
- Payway: proveedor seleccionado para la próxima integración; falta confirmar POS presencial, terminales compatibles y acceso sandbox.
- Getnet: alternativa si Payway no habilita una integración conveniente; Getnet publica una solución llamada Get POS Integrado para conectar sistemas de gestión con terminales físicas.
- Otros posnet: registro manual con comprobante y referencia de operación.

La documentación pública de Mercado Pago confirma que Point se integra mediante órdenes, terminal asociada a una caja y modo PDV ([configuración de terminal](https://www.mercadopago.com.ar/developers/es/docs/mp-point/configure-terminal)). Payway publica APIs y SDK, pero hay que confirmar con ellos el acceso a terminales físicas y su homologación.
