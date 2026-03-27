# Flujo de Negocio - ELPROFE POS

El sistema de Punto de Venta (POS) de ELPROFE está diseñado para operar bajo un esquema estricto de auditoría y control de inventario, especialmente adaptado para comercio con operaciones bimonetarias.

## 1. Gestión de Caja (Sesiones Individuales)
- **Requisito Operativo:** Todo cajero (sea usuario normal o Administrador) debe abrir su propia sesión de caja de forma individual.
- **Independencia:** Los movimientos de dinero generados (ventas, abonos) se reportan estrictamente a la caja de quien ejecuta la transacción. No se mezclan cajas.
- **Multicaja en Tiempo Real:** El sistema utiliza reservas (holds) en tiempo real. Si la *Caja 1* está facturando un producto "X" que tiene solo 1 en stock disponible, la *Caja 2* ya verá 0 disponibilidad para evitar duplicidad de ventas del mismo artículo en simultáneo.
- **Apertura y Cierre:** Se exige registrar un monto inicial de apertura. Al final del turno, el cajero cierra declarando lo que tiene físicamente, y el sistema cruza la declaración del cajero con los registros internos (Entradas/Salidas).

## 2. Inventario y Compras
- **Regla Estricta de Stock:** Los niveles de stock **no** son editables manualmente, ni siquiera por el Administrador. 
- **Inventario Inicial / Compras:** Todo ingreso de mercancía ocurre tras crear un Producto Completo (se procesa una compra automática subyacente de "Inventario Inicial") o pasando por el módulo de Compras reales a Proveedores. Esto asegura la trazabilidad contable y el cálculo transparente del **Costo Promedio (AVG)**.
- **Presentaciones y Variantes:** El sistema permite que el "Producto Padre" se venda en unidades individuales, pero a través de "Presentaciones" se le pueden crear factores de conversión (ej. Caja de 12 Unidades). Al comprar o vender una caja, el sistema internamente descuenta las 12 unidades del stock del producto raíz.

## 3. Ventas y Facturación (Modelo Proforma-First)
1. **Carrito/POS:** El cajero escanea productos e incrementa la cuenta. En esta fase las cantidades van quedando *reservadas* globalmente.
2. **Proforma / Nota de Entrega:** Al cobrar, el sistema *siempre* genera primero una *Proforma*. El stock queda inmediatamente descontado. 
3. **Casos de Abono / Fiado:** Si el pago es parcial (crédito), la proforma adopta estado PENDIENTE. Como es un entorno bimonetario, la deuda pendiente se congela en **USD**, y para pagar el saldo restante, el cliente paga la conversión en VES actual, basándose en la tasa del día en la que se ejecuta el pago. No usa la tasa histórica.
4. **Factura Legal / Fiscal:** Solo cuando la proforma está Pagada en su totalidad, se permite "Fiscalizar" y emitir un documento legal.

## 4. Libros Seniat
- **Libro de Compras y Ventas:** Se generan automáticamente en la ruta correspondientes procesando todas las proformas en estado Factura, separando la Base Imponible y el IVA estimado con su exportación a Excel y previsualización en PDF para su impresión directa.
