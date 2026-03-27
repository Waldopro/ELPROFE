# Verificación documentada — ELPROFE POS

Fecha: 2026-03-26  
Proyecto: **ELPROFE** (Punto de venta + inventario + multimoneda + PWA)  
Base de requisitos: `requiresment.md`

## Alcance (fase 1)
- Compras → Inventario (blindaje: no entradas manuales).
- Ventas por **Proforma/Nota de entrega** (en USD base).
- **Fiados** (ventas a crédito) + **abonos parciales**.
- **Pagos mixtos** (varios métodos en una misma venta/abono).
- Multimoneda (USD base + conversión a Bs por **tasa del día**).
- Caja/contabilidad básica por método de pago.
- Emisión de **FACTURA** solo cuando aplica (condición de negocio).
- Impresión/compartir comprobante (ticket y nota entrega) + soporte PWA.

## Criterios de aceptación (resumen)
- **Inventario**: solo aumenta por compras; ventas descuentan stock automáticamente.
- **Proforma**: se crea en USD; si FIADO queda con saldo pendiente.
- **Abonos**: permiten pagos parciales; cada abono usa **tasa del día del pago**.
- **Pagos mixtos**: una venta de contado puede registrar múltiples métodos.
- **Caja**: refleja entradas por método; historial auditable.
- **Factura**: no puede generarse como FIADO (solo cuando está pagado).

---

## 1) Base de datos y reglas de integridad
**Archivo**: `database.sql`

### Verificado
- **Tablas núcleo**: `productos`, `presentaciones`, `compras`, `compra_detalles`, `proformas`, `proforma_detalles`, `abonos`, `pagos_detalles`, `movimientos_caja`, `metodos_pago`, `clientes`, `proveedores`, `usuarios`, `configuracion`, `tasas_bcv`.
- **Blindaje inventario por triggers**:
  - `trg_compra_detalle_insert`: suma stock y recalcula costo promedio.
  - `trg_proforma_detalle_insert`: descuenta stock al vender (por factor de presentación).
- **Integridad**: claves foráneas y restricciones `RESTRICT`/`CASCADE` según corresponda.

### Observaciones
- La estructura está alineada al modelo del requisito (MySQL).

---

## 2) Autenticación, roles y seguridad básica
**Archivos**: `index.php`, `includes/functions.php`, `includes/header.php`

### Verificado
- **Login**: sesión por `$_SESSION['user_id']`.
- **Roles**: `ADMIN` vs `CAJERO` (`restrictAdmin()` limita módulos administrativos).
- **CSRF**:
  - Token generado con `generateCsrfToken()` y `csrfField()`.
  - Validación en endpoints críticos (`verifyCsrfToken()`).

### Riesgos conocidos (controlados)
- Compartir comprobantes como imagen en WhatsApp depende de soporte del navegador (ver sección 8).

---

## 3) Compras → Inventario (entradas estrictas)
**Archivo**: `pages/compras.php`

### Verificado
- Las compras se registran como factura de proveedor y generan:
  - `compras` + `compra_detalles`.
  - El inventario sube por trigger `trg_compra_detalle_insert`.
- No se encontró un endpoint que “sume stock” manualmente desde UI de inventario.

### Prueba manual sugerida
- Crear compra con 1 presentación y confirmar:
  - `productos.stock_actual` incrementa por `cantidad * factor_conversion`.

---

## 4) Ventas → Proformas/Notas → Inventario (salidas)
**Archivos**: `pages/ventas.php`, `assets/js/pos.js`, `api/ventas.php`

### Verificado
- **Búsqueda de producto** por presentación (`presentaciones`).
- Cálculo de total **validado en backend** (evita manipulación del carrito).
- Descuento de inventario por trigger `trg_proforma_detalle_insert`.
- Conversión informativa a Bs en UI con `tasa_usd_bs`.

### Corrección aplicada (negocio)
- Se bloqueó emitir `FACTURA` en FIADO: una venta a crédito no puede nacer como factura.

---

## 5) Fiados y cobranza (abonos parciales)
**Archivo**: `pages/proformas.php`

### Verificado
- Abono:
  - Calcula equivalente USD: \(usd + \frac{bs}{tasa\_hoy}\).
  - Inserta en `abonos` y `pagos_detalles`.
  - Registra movimiento en `movimientos_caja`.
  - Actualiza `proformas.saldo_pendiente_usd` y estado (`PARCIAL`/`PAGADO`).
- **Regla clave cumplida**: usa **tasa del día del pago** (`tasa_usd_bs` actual).

---

## 6) Caja / contabilidad por método
**Archivo**: `pages/caja.php`

### Verificado
- Balance por método:
  - suma entradas/salidas desde `movimientos_caja`.
- Historial:
  - lista últimos movimientos con referencia.

### Nota
- El reporte muestra `monto_usd` como “equivalente” en UI; si se desea “equivalente USD” real para Bs, se recomienda persistir `monto_equivalente_usd` también en `movimientos_caja` (mejora futura).

---

## 7) Multimoneda y tasa (BCV / manual)
**Archivos**: `pages/configuracion.php`, `api/config.php`, `api/bcv.php`, `api/bcv_scraper.php`

### Verificado
- Tasa manual configurable (ADMIN).
- Tasa BCV:
  - Scraper guarda en `tasas_bcv` y actualiza `configuracion.tasa_usd_bs`.
  - Manejo de fin de semana con última tasa disponible.

### Corrección aplicada (seguridad)
- `api/bcv.php` exige sesión + CSRF.

---

## 8) Comprobantes (ticket/nota) y WhatsApp
**Archivos**: `pages/ticket.php`, `pages/nota_entrega.php`, `api/guardar_ticket.php`

### Verificado
- Generación de imagen vía `html2canvas` y guardado en `assets/tickets/`.
- Descarga/impresión disponibles.

### Limitación técnica (importante)
- **WhatsApp Web** no permite adjuntar imágenes automáticamente desde un link tipo `wa.me`.
- En **móvil**, si el navegador soporta `navigator.share({ files })`, se puede compartir la imagen directamente eligiendo WhatsApp.
- En **escritorio**, normalmente se debe **descargar** y adjuntar manualmente en WhatsApp Web.

---

## 9) PWA (manifest + service worker)
**Archivos**: `manifest.json`, `sw.js`, `includes/footer.php`

### Verificado
- `manifest.json` apunta a íconos existentes.
- `sw.js` cachea assets base.
- Registro del service worker activo desde `includes/footer.php`.

---

## Checklist de pruebas de aceptación (paso a paso)
- [ ] Login con `admin`.
- [ ] Crear proveedor y producto/presentación si aplica.
- [ ] **Compra**: registrar compra y confirmar incremento de stock.
- [ ] **Venta contado**: registrar proforma con pago mixto (2 métodos) y confirmar:
  - proforma `PAGADO`
  - movimientos de caja creados
  - stock disminuye
- [ ] **Venta fiado**: registrar proforma FIADO y confirmar saldo pendiente.
- [ ] **Abono**: registrar 2 abonos en días/tasas distintas y confirmar:
  - tasa usada es la del día del abono
  - saldo baja correctamente a 0
- [ ] **Factura**:
  - validar que FIADO no permite emitir FACTURA al crear
  - (si se implementa correlativo) validar que genera `factura_numero`
- [ ] **Ticket/nota**:
  - imprimir
  - generar imagen para compartir
  - en móvil: compartir como archivo
  - en escritorio: descargar y adjuntar en WhatsApp Web

