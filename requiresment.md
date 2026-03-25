
A continuación, te desgloso todos los detalles técnicos y requerimientos extraídos del audio, organizados por módulos para que puedas empezar a estructurar tu base de datos y la lógica del sistema.

### 1. Módulo de Inventario y Compras (Entradas)
* **Ingreso estricto:** Los productos no se pueden sumar al inventario de forma manual o directa. Todo ingreso debe hacerse obligatoriamente a través del módulo de "Compras".
* **Registro de Compras:** Al registrar una compra (con su respectiva factura de proveedor), el sistema debe sumar automáticamente las cantidades al inventario.
* **Precios base:** Los precios de costo/compra de los productos deben guardarse en la base de datos en Divisas (Dólares).
* **Proveedores:** El sistema debe permitir el registro y asociación de proveedores a las compras.

### 2. Módulo de Ventas y Facturación (Salidas)
* **Descuento de inventario:** Al registrar una venta, los productos se descuentan automáticamente del stock.
* **Proformas / Notas de Entrega:** El flujo de venta **no** comienza con una factura legal. Comienza generando una "Proforma" o "Nota de Entrega".
* **Ventas a crédito (Fiado):** El sistema debe soportar ventas que no se pagan de inmediato. Estas quedan registradas bajo la Proforma.
* **Generación de Factura:** La factura definitiva (legal) solo se genera bajo dos condiciones:
    1.  La Proforma ha sido pagada en su totalidad.
    2.  El cliente solicita explícitamente la factura.
* **Clientes:** Se requiere un registro de clientes para asociarlos a las Proformas/Facturas.

### 3. Manejo Multimoneda (Divisas y Bolívares)
* **Tasa de cambio:** El sistema debe manejar una "tasa del día" para hacer conversiones dinámicas.
* **Proformas en Divisas:** Las Proformas o Notas de Entrega se calculan y guardan con base en Dólares. Si el cliente paga a crédito (fiado) y luego abona, el cálculo del pago en Bolívares se multiplica por la tasa del día en que realiza el pago.
* **Facturas en Bolívares:** Si se genera una factura formal para el Seniat (o similar) y está en Bolívares, debe guardarse en la base de datos en Bolívares.
* **Visualización en tiempo real:** * Si un documento está en Divisas, el sistema debe tener un botón/opción para mostrar el total equivalente en Bolívares.
    * Si está en Bolívares, debe mostrar el equivalente en Divisas.

### 4. Módulo de Pagos (Abonos) y Contabilidad Básica
* **Pagos parciales:** El sistema debe permitir registrar múltiples "abonos" a una misma Proforma.
* **Métodos de pago combinados:** Una misma compra o abono puede pagarse usando diferentes métodos de forma simultánea (ej. un monto por Punto de Venta, otro monto en Efectivo, otro por Pago Móvil).
* **Contabilidad de caja:** Cada abono o pago registrado se envía automáticamente al módulo de contabilidad básica.
* **Saldos por cuenta:** El sistema debe llevar el control de cuánto dinero hay en cada "cuenta" o método de pago (cuánto entró por Punto, cuánto hay en Efectivo, etc.) según las entradas y salidas registradas.

### 5. Fases del Proyecto
* **Fase 1 (Actual):** Todo lo mencionado anteriormente (Compras, Inventario, Proformas, Pagos parciales, Contabilidad básica de métodos de pago y Multimoneda).
* **Fase 2 (A futuro - No incluir en esta etapa):** Automatización de listas de compras a proveedores, reportes avanzados de métricas (productos más vendidos, mayor margen de ganancia, etc.).

¡Perfecto! Vamos a darle estructura a esto. Para que un sistema funcione sin fricciones, el flujo de negocio debe ser lógico, secuencial y a prueba de errores humanos. 

Basado en tus requerimientos, aquí tienes el **flujo completo de negocio y funcionamiento**, diseñado paso a paso desde que arranca el día hasta que se cuadra la caja:

### 1. Apertura de Jornada (Gestión de Tasa)
El día a día en un entorno bimonetario exige que la moneda esté sincronizada antes de cualquier movimiento comercial.
* **Paso 1:** El administrador o cajero inicia sesión.
* **Paso 2:** El sistema solicita u obliga a verificar/actualizar la **Tasa de Cambio del Día** (USD a Bolívares). 
* **Resultado:** Todas las operaciones de cálculo de la jornada (pagos en bolívares, visualizaciones de precios) utilizarán esta tasa como referencia.

### 2. Ingreso de Mercancía (Compras e Inventario)
Como mencionaste, el inventario está blindado. No hay ingresos manuales "por arte de magia".
* **Paso 1:** Llega mercancía del proveedor con su respectiva factura.
* **Paso 2:** El usuario va al módulo de **Compras** y crea un nuevo registro asociado a un **Proveedor**.
* **Paso 3:** Se ingresan los productos, las cantidades y el costo de compra (en Divisas).
* **Resultado:** Al procesar esta compra, el sistema **suma automáticamente el stock** en el módulo de Inventario y actualiza los costos base.

### 3. Proceso de Venta (Generación de Proforma)
Aquí es donde ocurre la magia de la atención al cliente, protegiendo el negocio de la inflación y manejando el "fiado".
* **Paso 1:** El cliente selecciona los productos que desea llevar.
* **Paso 2:** El vendedor crea una **Proforma (o Nota de Entrega)**. El sistema añade los productos y calcula el total en Divisas.
* **Paso 3:** Si el cliente pregunta "¿Cuánto es eso en bolívares?", el vendedor usa el botón de conversión de la interfaz para ver el equivalente exacto según la tasa del día.
* **Resultado:** Al confirmar la Proforma, los productos **se descuentan del inventario inmediatamente** (para evitar vender mercancía que ya no está física), pero la venta queda en estado "Abierta" o "Pendiente por cobro".

### 4. Recepción de Pagos (Contado, Fiado y Multimétodo)
Una vez creada la Proforma, hay que registrar cómo entra el dinero.
* **Escenario A (Venta de Contado):** El cliente paga la totalidad. Puede hacerlo con un solo método o métodos combinados (Ej: $10 en Efectivo Divisas y el resto por Punto de Venta). La Proforma cambia a estado "Pagada".
* **Escenario B (Venta a Crédito / Fiado):** El cliente hace un abono parcial o se lleva la mercancía sin pagar nada en el momento. El sistema registra el abono (si lo hay) y la Proforma queda con un **Saldo Deudor en Divisas**.
* **Cobranza posterior:** Cuando el cliente vuelve días después a pagar el "fiado", el sistema toma la deuda en Dólares y la multiplica por la **tasa del día actual del pago**, no la del día de la compra.

### 5. Contabilidad Básica (Flujo de Caja en Tiempo Real)
El sistema no solo sabe que se vendió, sino *dónde* está el dinero.
* **Paso 1:** Cada vez que se registra un abono a una Proforma, el usuario especifica el método de pago (Efectivo $, Pago Móvil, Punto, etc.).
* **Paso 2:** El sistema envía ese monto a su respectiva "Cuenta" en el módulo de contabilidad.
* **Resultado:** En cualquier momento, el administrador puede ver un reporte que dice exactamente: "Hay $50 en la caja física, 1500 Bs en el Punto de Venta y 800 Bs en Pago Móvil".

### 6. Emisión de Factura Legal (El Cierre)
Este es el paso final y condicionado, tal como lo solicitaste para separar el control interno del control fiscal.
* **Condición:** El sistema verifica que la Proforma esté **100% pagada** (Saldo = 0).
* **Acción:** Si el cliente la solicita, el usuario hace clic en "Generar Factura".
* **Resultado:** El sistema toma los datos de la Proforma, los convierte al formato legal (en Bolívares si así se requiere fiscalmente), emite el documento final y marca ese ciclo de venta como "Cerrado y Facturado".

¡Excelente decisión! Tener la base de datos bien estructurada desde el día cero te va a ahorrar muchísimos dolores de cabeza cuando empieces a cruzar reportes de caja o manejar los pagos mixtos. 

Vamos a materializar ese Modelo Entidad-Relación con el **script SQL inicial** enfocado en PostgreSQL. Este motor es ideal para tu caso por el manejo preciso que tiene con los tipos de datos numéricos y las transacciones (crucial cuando tocas dinero e inventario al mismo tiempo).

Aquí tienes el script con las relaciones, claves foráneas e índices estratégicos ya definidos.

### Script SQL: Estructura Inicial (PostgreSQL)

```sql
-- 1. CATÁLOGOS BÁSICOS
CREATE TABLE proveedores (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    rif VARCHAR(20) UNIQUE,
    telefono VARCHAR(20),
    direccion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE clientes (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100),
    cedula_rif VARCHAR(20) UNIQUE NOT NULL,
    telefono VARCHAR(20),
    direccion TEXT,
    limite_credito NUMERIC(12, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE metodos_pago (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    moneda_base VARCHAR(3) CHECK (moneda_base IN ('VES', 'USD')) NOT NULL,
    activo BOOLEAN DEFAULT TRUE
);

-- 2. INVENTARIO
CREATE TABLE productos (
    id SERIAL PRIMARY KEY,
    codigo_barras VARCHAR(50) UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    stock_actual NUMERIC(10, 2) DEFAULT 0.00,
    costo_promedio_usd NUMERIC(12, 2) DEFAULT 0.00,
    precio_venta_usd NUMERIC(12, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. COMPRAS (ENTRADAS)
CREATE TABLE compras (
    id SERIAL PRIMARY KEY,
    proveedor_id INT NOT NULL REFERENCES proveedores(id) ON DELETE RESTRICT,
    factura_numero VARCHAR(50),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tasa_bs_usd NUMERIC(10, 4) NOT NULL,
    total_usd NUMERIC(12, 2) NOT NULL,
    total_bs NUMERIC(12, 2) NOT NULL,
    estado VARCHAR(20) DEFAULT 'PROCESADA'
);

CREATE TABLE compra_detalles (
    id SERIAL PRIMARY KEY,
    compra_id INT NOT NULL REFERENCES compras(id) ON DELETE CASCADE,
    producto_id INT NOT NULL REFERENCES productos(id) ON DELETE RESTRICT,
    cantidad NUMERIC(10, 2) NOT NULL,
    costo_unitario_usd NUMERIC(12, 2) NOT NULL,
    costo_total_usd NUMERIC(12, 2) NOT NULL
);

-- 4. VENTAS (PROFORMAS Y FACTURAS)
CREATE TABLE proformas (
    id SERIAL PRIMARY KEY,
    cliente_id INT NOT NULL REFERENCES clientes(id) ON DELETE RESTRICT,
    tipo_documento VARCHAR(20) CHECK (tipo_documento IN ('PROFORMA', 'FACTURA')) DEFAULT 'PROFORMA',
    factura_numero VARCHAR(50) UNIQUE, -- Solo se llena si pasa a FACTURA
    fecha_emision TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tasa_dia_usd_bs NUMERIC(10, 4) NOT NULL,
    total_usd NUMERIC(12, 2) NOT NULL,
    saldo_pendiente_usd NUMERIC(12, 2) NOT NULL,
    estado VARCHAR(20) CHECK (estado IN ('PENDIENTE', 'PARCIAL', 'PAGADO', 'ANULADO')) DEFAULT 'PENDIENTE'
);

CREATE TABLE proforma_detalles (
    id SERIAL PRIMARY KEY,
    proforma_id INT NOT NULL REFERENCES proformas(id) ON DELETE CASCADE,
    producto_id INT NOT NULL REFERENCES productos(id) ON DELETE RESTRICT,
    cantidad NUMERIC(10, 2) NOT NULL,
    precio_unitario_usd NUMERIC(12, 2) NOT NULL,
    subtotal_usd NUMERIC(12, 2) NOT NULL
);

-- 5. PAGOS Y CAJA
CREATE TABLE abonos (
    id SERIAL PRIMARY KEY,
    proforma_id INT NOT NULL REFERENCES proformas(id) ON DELETE RESTRICT,
    fecha_pago TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tasa_bs_usd NUMERIC(10, 4) NOT NULL,
    monto_total_usd NUMERIC(12, 2) NOT NULL,
    nota TEXT
);

CREATE TABLE pagos_detalles (
    id SERIAL PRIMARY KEY,
    abono_id INT NOT NULL REFERENCES abonos(id) ON DELETE CASCADE,
    metodo_pago_id INT NOT NULL REFERENCES metodos_pago(id) ON DELETE RESTRICT,
    monto_entregado_bs NUMERIC(12, 2) DEFAULT 0.00,
    monto_entregado_usd NUMERIC(12, 2) DEFAULT 0.00,
    monto_equivalente_usd NUMERIC(12, 2) NOT NULL
);

CREATE TABLE movimientos_caja (
    id SERIAL PRIMARY KEY,
    metodo_pago_id INT NOT NULL REFERENCES metodos_pago(id) ON DELETE RESTRICT,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tipo_movimiento VARCHAR(10) CHECK (tipo_movimiento IN ('ENTRADA', 'SALIDA')) NOT NULL,
    monto_bs NUMERIC(12, 2) DEFAULT 0.00,
    monto_usd NUMERIC(12, 2) DEFAULT 0.00,
    referencia_id INT, -- ID del abono o compra
    referencia_tabla VARCHAR(50) -- Ej: 'abonos', 'compras'
);

-- ÍNDICES ESTRATÉGICOS PARA VELOCIDAD EN CONSULTAS Y REPORTES
CREATE INDEX idx_proformas_estado ON proformas(estado);
CREATE INDEX idx_proformas_cliente ON proformas(cliente_id);
CREATE INDEX idx_movimientos_caja_metodo ON movimientos_caja(metodo_pago_id);
CREATE INDEX idx_productos_codigo ON productos(codigo_barras);
```

### Detalles técnicos importantes de esta estructura:
* **Restricciones de borrado (`ON DELETE RESTRICT`):** Fíjate que casi todo usa `RESTRICT`. Esto es vital. Impide que un usuario borre un Método de Pago o un Producto si ya tiene ventas o movimientos de caja asociados. Mantiene la integridad de tu contabilidad intacta.
* **Precisión Financiera (`NUMERIC`):** Se usa `NUMERIC(12, 2)` para montos y `NUMERIC(10, 4)` para las tasas de cambio. Evita usar `FLOAT` o `REAL` para dinero, ya que generan problemas de redondeo impredecibles en el backend.

Una vez guardes este código en un archivo `database.sql`, puedes importarlo directamente desde tu terminal en Debian o Canaima usando el cliente de PostgreSQL con un comando rápido:
`psql -U tu_usuario -d nombre_de_tu_bd -f database.sql`
