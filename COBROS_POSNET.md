# Cobros con posnet y nueva landing

## Qué se corrigió

El flujo anterior enviaba `invoice_id: 0` a Point, guardaba una señal de aprobación en caché durante 60 segundos y no implementaba el webhook de Mercado Pago. Además, emitía todas las facturas con estado `paid`. Eso impedía asociar de forma confiable una operación con su factura y permitía reutilizar una señal vieja.

Ahora las facturas con saldo de tarjeta o Mercado Pago quedan `pending`. Se conservan en la base aunque se cierre el navegador. El stock se reserva al emitirlas; confirmar el cobro no vuelve a descontarlo. Efectivo y transferencias conservan el registro declarado por el operador.

La confirmación registra un pago, su referencia y el estado `paid` en una transacción de base de datos. Se comprueba el importe del saldo y el comercio. Repetir la misma confirmación no crea otro pago, y un comprobante ya utilizado no puede cobrar otra factura del mismo comercio y dispositivo.

También se corrigieron ventas por encima del stock disponible, clientes de otro comercio, el tipo A/B/C mostrado en la impresión, el manejo de errores al cargar una venta y la doble pulsación del botón de emitir.

## Mercado Pago Point

Se utiliza **Orders API**, con una referencia `invoice_ID`, el saldo calculado en el servidor, un identificador de terminal completo y una clave persistente de idempotencia. La ruta interna `/mp/point-intent` se conserva por compatibilidad, pero crea una orden en `/v1/orders`.

1. Aplicar la migración nueva en el backend. No cambia el estado de facturas anteriores:

   ```powershell
   cd C:\xampp\htdocs\sistemafacturacion\api
   C:\xampp\php\php.exe artisan migrate --path=database/migrations/2026_09_17_000001_add_point_intent_to_invoices.php
   ```

2. En Configuración, guardar el Access Token **del comercio** y registrar el ID completo de una terminal compatible, en modo PDV. El número de serie de la etiqueta por sí solo no es el ID de terminal. Para cuentas de terceros, obtener las credenciales mediante el flujo OAuth del proveedor; el alta OAuth no se implementó en este cambio.
3. En la aplicación de Mercado Pago, activar notificaciones **Order (Mercado Pago)** a `https://TU-DOMINIO/api/v1/webhooks/mp`. Debe ser una dirección HTTPS pública; localhost no recibe notificaciones externas.
4. Emitir la factura con tarjeta, abrir su detalle y elegir **Enviar cobro al Point**.
5. Completar el cobro en la terminal. La pantalla consulta su estado cada cinco segundos, durante un máximo de dos minutos. También permite consultar de nuevo desde la factura.

Un webhook es un aviso: el servidor obtiene la orden de la API autenticada del comercio antes de modificar la factura. Verifica su ID, referencia, país, importe, estado `processed` de la orden y pago `processed/accredited`. Una notificación que declara estar aprobada, sin respaldo en esa consulta, no cobra la factura. La consulta desde el detalle recupera un webhook perdido.

Si se pierde la respuesta al enviar una orden, **Recuperar envío al Point** reutiliza la misma clave. Nunca volver a generar una factura para recuperar una respuesta de pago. La recuperación depende de la política de idempotencia del proveedor.

Los estados rechazado, cancelado, vencido o que requieren una acción no marcan la factura cobrada. No hay reemisión automática de órdenes ni devoluciones automáticas. Ante una orden finalizada sin pago, revisar el proveedor antes de iniciar otro cobro; la interfaz todavía no genera una segunda orden para esa factura. Los reembolsos posteriores requieren conciliación y el tratamiento del comprobante correspondiente.

Los pagos mixtos registran la parte de efectivo/transferencia y confirman el saldo electrónico restante como una sola operación. No se implementó conciliación individual de varias tarjetas para una misma factura.

Documentación consultada:

- [Procesamiento de pagos con Point y formato de Orders](https://www.mercadopago.com.ar/developers/es/docs/mp-point/payment-processing)
- [Estados de órdenes y transacciones](https://www.mercadopago.com.ar/developers/es/docs/mp-point/resources/status-order-transaction)
- [Notificaciones de órdenes](https://www.mercadopago.com.ar/developers/es/docs/mp-point/notifications)

## Posnet tradicional, Payway, Fiserv, Getnet, Ualá Bis, Naranja X, Nave y MODO

Para que el producto sea útil en comercios de todo el país, la configuración contempla los proveedores más habituales: Mercado Pago Point, Payway/Prisma, Fiserv Posnet, Clover, Getnet, Ualá Bis, Naranja X, Nave y MODO. La lista es una clasificación de proveedor, no una promesa de compatibilidad automática: cada adquirente tiene contratos, APIs y credenciales distintos.

El registro manual funciona desde el detalle de la factura:

1. Registrar el dispositivo como **Postnet Tradicional** en Configuración.
2. Verificar en el posnet o comprobante la aprobación y el importe.
3. Ingresar una referencia de operación única (agregar lote/fecha si el número se repite).
4. Marcar la confirmación y registrar el cobro.

El registro deja constancia de que la verificación fue manual y del usuario que la hizo. No prueba por sí mismo que el proveedor aprobó el pago. Para automatizar Payway, Fiserv, Getnet, Ualá Bis, Naranja X, Nave, MODO o Clover faltan la empresa exacta, el modelo, el protocolo habilitado y las credenciales o SDK del proveedor. Guardar credenciales no constituye una integración funcional.

Para el producto genérico conviene ofrecer primero Mercado Pago Point como integración automática porque tiene una API pública documentada para crear órdenes, recibir notificaciones y consultar estados; los demás quedan como registro manual hasta que un comercio solicite una integración concreta. La documentación oficial describe Point como una terminal integrada al sistema de ventas y exige terminal configurada en modo PDV y credenciales del comercio ([Mercado Pago Point](https://www.mercadopago.com.ar/developers/es/docs/mp-point/overview)).

Se retiró de la interfaz la descarga del bridge anterior y se bloqueó su webhook: detectar un importe impreso no constituye una aprobación verificable ni identifica una factura. Ese webhook ya no crea facturas genéricas ni solicita comprobantes a ARCA.

## Landing y validación

- Vista local: `http://127.0.0.1:5173/landing/index.html` con `npm run dev` desde `frontend`.
- La entrada principal de la app envía a los visitantes a la landing; las cuentas autenticadas continúan al dashboard.
- La landing se compila con Vite y también funciona como página estática en la raíz del despliegue Nginx existente. Los enlaces de acceso contemplan ambos casos.
- Se reemplazó el CSS dependiente de Tailwind CDN por CSS propio. Incluye menú móvil, navegación con teclado, preguntas desplegables y preferencia de movimiento reducido.
- Frontend verificado con `npm run build`; backend con PHPUnit sobre SQLite en memoria y respuestas de Mercado Pago/ARCA simuladas. Se cubren confirmaciones repetidas, importes incorrectos, permisos, estado pendiente, stock y recuperación por consulta.
- Revisión en Chrome sin interfaz a 1440, 390 y 320 píxeles: sin desbordamiento horizontal; menú, preguntas, enlaces y redirección comprobados. Script reproducible: `node tools/verify_landing.mjs`. Capturas en `artifacts/landing-*.png`.

**Pendiente en este entorno:** MySQL de XAMPP no está iniciado (puerto 3306 rechaza la conexión), por lo que la migración local aún no se aplicó. Las pruebas no modificaron la base del negocio. No se realizaron cargos reales ni pruebas con una terminal física. Antes de usarlo para cobrar hay que aplicar la migración y realizar la prueba de integración con la cuenta y el equipo correspondientes.
