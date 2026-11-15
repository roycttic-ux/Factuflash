# Validación real con ARCA pendiente

La integración de tributos manuales cuenta con pruebas automatizadas que simulan WSFE y verifican `ImpTrib`, el grupo `Tributos`, el total, los reintentos idempotentes y la asociación/reversión en notas de crédito. Las pruebas no prueban credenciales reales ni emiten comprobantes ante ARCA. Los tests llamados `AfipInvoiceTest` también sustituyen el servicio por un mock y usan SQLite.

Antes de declarar validada la integración para producción, queda pendiente emitir y verificar comprobantes reales con el titular y el punto de venta autorizados. La verificación debe confirmar:

- Una factura de prueba autorizada por ARCA con uno o varios tributos manuales, comparando el detalle autorizado, `ImpTrib` y el total con los importes guardados y el PDF.
- Una nota de crédito asociada a esa factura, confirmando el tratamiento de los tributos en la respuesta de ARCA y el total revertido.
- Una consulta posterior del comprobante autorizado para comprobar que ARCA conserva esos importes.

No ejecutar esta validación contra producción sin autorización del titular y sin confirmar el ambiente, CUIT y punto de venta. Hasta documentar el resultado real de esa verificación, el comportamiento fiscal ante ARCA permanece pendiente de validación externa.
