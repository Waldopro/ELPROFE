-- 1. CATÁLOGOS BÁSICOS
CREATE TABLE proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    rif VARCHAR(20) UNIQUE,
    contacto VARCHAR(100),
    telefono VARCHAR(20),
    email VARCHAR(120),
    direccion TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100),
    cedula_rif VARCHAR(20) UNIQUE NOT NULL,
    telefono VARCHAR(20),
    direccion TEXT,
    ubicacion TEXT,
    limite_credito DECIMAL(12, 2) DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE metodos_pago (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    moneda_base ENUM('VES', 'USD') NOT NULL,
    activo TINYINT(1) DEFAULT 1
);

-- 2. INVENTARIO Y PRESENTACIONES (PADRE-HIJO)
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_interno VARCHAR(50) UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    marca VARCHAR(80),
    descripcion TEXT,
    categoria_id INT DEFAULT NULL,
    foto VARCHAR(255) DEFAULT NULL,
    stock_actual DECIMAL(10, 2) DEFAULT 0.00,
    stock_minimo DECIMAL(10, 2) DEFAULT 0.00,
    costo_promedio_usd DECIMAL(12, 2) DEFAULT 0.00,
    exento_iva TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE presentaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    nombre_presentacion VARCHAR(100) NOT NULL, -- Ej: 'Unidades', 'Caja de 12', etc.
    factor_conversion DECIMAL(10, 2) NOT NULL DEFAULT 1.00,
    precio_venta_usd DECIMAL(12, 2) NOT NULL,
    codigo_barras VARCHAR(50) UNIQUE NOT NULL,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
);

ALTER TABLE productos
  ADD CONSTRAINT fk_producto_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL;

-- 2.5 SESIONES DE CAJA
CREATE TABLE sesiones_caja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha_apertura DATETIME DEFAULT CURRENT_TIMESTAMP,
    monto_inicial_usd DECIMAL(12, 2) DEFAULT 0.00,
    monto_inicial_bs DECIMAL(12, 2) DEFAULT 0.00,
    fecha_cierre DATETIME NULL,
    monto_cierre_usd_declarado DECIMAL(12, 2) DEFAULT NULL,
    monto_cierre_bs_declarado DECIMAL(12, 2) DEFAULT NULL,
    notas_cierre TEXT,
    estado ENUM('ABIERTA', 'CERRADA') DEFAULT 'ABIERTA',
    KEY idx_sesion_estado (estado)
);

-- 3. COMPRAS (ENTRADAS)
CREATE TABLE compras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id INT NOT NULL,
    factura_numero VARCHAR(50),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    tasa_bs_usd DECIMAL(10, 4) NOT NULL,
    total_usd DECIMAL(12, 2) NOT NULL,
    total_bs DECIMAL(12, 2) NOT NULL,
    estado ENUM('PROCESADA', 'ANULADA') DEFAULT 'PROCESADA',
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE RESTRICT
);

CREATE TABLE compra_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compra_id INT NOT NULL,
    presentacion_id INT NOT NULL,
    cantidad DECIMAL(10, 2) NOT NULL,
    costo_unitario_usd DECIMAL(12, 2) NOT NULL,
    costo_total_usd DECIMAL(12, 2) NOT NULL,
    FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
    FOREIGN KEY (presentacion_id) REFERENCES presentaciones(id) ON DELETE RESTRICT
);

-- 4. VENTAS (PROFORMAS Y FACTURAS)
CREATE TABLE proformas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    cajero_id INT NOT NULL,
    tipo_documento ENUM('PROFORMA', 'FACTURA') DEFAULT 'PROFORMA',
    factura_numero VARCHAR(50) UNIQUE,
    fecha_emision DATETIME DEFAULT CURRENT_TIMESTAMP,
    tasa_dia_usd_bs DECIMAL(10, 4) NOT NULL,
    total_usd DECIMAL(12, 2) NOT NULL,
    saldo_pendiente_usd DECIMAL(12, 2) NOT NULL,
    estado ENUM('PENDIENTE', 'PARCIAL', 'PAGADO', 'ANULADO') DEFAULT 'PENDIENTE',
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT
);

CREATE TABLE proforma_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proforma_id INT NOT NULL,
    presentacion_id INT NOT NULL,
    cantidad DECIMAL(10, 2) NOT NULL,
    precio_unitario_usd DECIMAL(12, 2) NOT NULL,
    subtotal_usd DECIMAL(12, 2) NOT NULL,
    FOREIGN KEY (proforma_id) REFERENCES proformas(id) ON DELETE CASCADE,
    FOREIGN KEY (presentacion_id) REFERENCES presentaciones(id) ON DELETE RESTRICT
);

-- 5. PAGOS Y CAJA
CREATE TABLE abonos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proforma_id INT NOT NULL,
    fecha_pago DATETIME DEFAULT CURRENT_TIMESTAMP,
    tasa_bs_usd DECIMAL(10, 4) NOT NULL,
    monto_total_usd DECIMAL(12, 2) NOT NULL,
    nota TEXT,
    FOREIGN KEY (proforma_id) REFERENCES proformas(id) ON DELETE RESTRICT
);

CREATE TABLE pagos_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    abono_id INT NOT NULL,
    metodo_pago_id INT NOT NULL,
    monto_entregado_bs DECIMAL(12, 2) DEFAULT 0.00,
    monto_entregado_usd DECIMAL(12, 2) DEFAULT 0.00,
    monto_equivalente_usd DECIMAL(12, 2) NOT NULL,
    FOREIGN KEY (abono_id) REFERENCES abonos(id) ON DELETE CASCADE,
    FOREIGN KEY (metodo_pago_id) REFERENCES metodos_pago(id) ON DELETE RESTRICT
);

CREATE TABLE movimientos_caja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metodo_pago_id INT NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    tipo_movimiento ENUM('ENTRADA', 'SALIDA') NOT NULL,
    monto_bs DECIMAL(12, 2) DEFAULT 0.00,
    monto_usd DECIMAL(12, 2) DEFAULT 0.00,
    referencia_id INT,
    referencia_tabla VARCHAR(50),
    sesion_caja_id INT NULL,
    FOREIGN KEY (metodo_pago_id) REFERENCES metodos_pago(id) ON DELETE RESTRICT
);

-- 5.5 RESERVAS DE STOCK (TIEMPO REAL MULTICAJA)
CREATE TABLE reservas_carrito (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    device_id VARCHAR(80) NOT NULL,
    estado ENUM('ACTIVE', 'HOLD') DEFAULT 'ACTIVE',
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_reservas_user (usuario_id),
    KEY idx_reservas_exp (expires_at),
    KEY idx_reservas_device (device_id)
);

CREATE TABLE reservas_carrito_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reserva_id INT NOT NULL,
    presentacion_id INT NOT NULL,
    cantidad DECIMAL(10, 2) NOT NULL,
    UNIQUE KEY uq_reserva_presentacion (reserva_id, presentacion_id),
    FOREIGN KEY (reserva_id) REFERENCES reservas_carrito(id) ON DELETE CASCADE,
    FOREIGN KEY (presentacion_id) REFERENCES presentaciones(id) ON DELETE RESTRICT
);

-- 6. CONFIGURACION Y USUARIOS
CREATE TABLE configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) UNIQUE NOT NULL,
    valor VARCHAR(255) NOT NULL,
    descripcion TEXT
);

CREATE TABLE tasas_bcv (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL UNIQUE,
    usd DECIMAL(10, 4),
    eur DECIMAL(10, 4)
);

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    rol ENUM('ADMIN', 'CAJERO') DEFAULT 'CAJERO',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE bitacora_accesos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    username_intento VARCHAR(50),
    ip VARCHAR(45),
    ubicacion VARCHAR(190) NULL,
    dispositivo VARCHAR(255),
    exito TINYINT(1),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE bitacora_acciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    accion VARCHAR(255) NOT NULL,
    detalle TEXT,
    ip VARCHAR(45),
    ubicacion VARCHAR(190) NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Insertar datos iniciales
INSERT INTO configuracion (clave, valor, descripcion) VALUES ('tasa_usd_bs', '36.5', 'Tasa de cambio actual (USD a VES)');
INSERT INTO configuracion (clave, valor, descripcion) VALUES ('tasa_tipo', 'MANUAL', 'Tipo de cálculo de tasa actual');

INSERT INTO metodos_pago (nombre, moneda_base) VALUES 
('Dolar', 'USD'),
('Efectivo', 'VES'),
('Punto de Venta', 'VES'),
('Pago Movil', 'VES'),
('Biopago', 'VES'),
('Transferencia', 'VES'),
('Binance', 'USD');

INSERT INTO usuarios (username, password, nombre, rol) VALUES 
('admin', '$2y$12$w7qwpkTO3fcjLCFg6fGmw.aWmIIrkcpAUGjLGGMpVjqpSj49vC1bC', 'Administrador Global', 'ADMIN'),
('caja01', '$2y$12$w7qwpkTO3fcjLCFg6fGmw.aWmIIrkcpAUGjLGGMpVjqpSj49vC1bC', 'Cajero Principal', 'CAJERO');

ALTER TABLE proformas ADD CONSTRAINT fk_proforma_cajero FOREIGN KEY (cajero_id) REFERENCES usuarios(id) ON DELETE RESTRICT;
ALTER TABLE sesiones_caja ADD CONSTRAINT fk_sesion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT;
ALTER TABLE movimientos_caja ADD CONSTRAINT fk_movimiento_sesion FOREIGN KEY (sesion_caja_id) REFERENCES sesiones_caja(id) ON DELETE RESTRICT;

CREATE INDEX idx_proformas_estado ON proformas(estado);
CREATE INDEX idx_proformas_fecha ON proformas(fecha_emision);
CREATE INDEX idx_proformas_cliente ON proformas(cliente_id);
CREATE INDEX idx_compras_fecha ON compras(fecha);
CREATE INDEX idx_movimientos_fecha ON movimientos_caja(fecha);
CREATE INDEX idx_presentaciones_codigo ON presentaciones(codigo_barras);

-- ==========================================
-- TRIGGERS DE INVENTARIO (BLINDAJE ESTRICTO DESCOMPUESTO)
-- ==========================================
DELIMITER //

CREATE TRIGGER trg_compra_detalle_insert 
AFTER INSERT ON compra_detalles
FOR EACH ROW
BEGIN
    DECLARE v_prod_id INT;
    DECLARE v_factor DECIMAL(10,2);
    
    SELECT producto_id, factor_conversion INTO v_prod_id, v_factor
    FROM presentaciones WHERE id = NEW.presentacion_id;
    
    UPDATE productos 
    SET 
        costo_promedio_usd = ((stock_actual * costo_promedio_usd) + (NEW.cantidad * NEW.costo_unitario_usd)) / (stock_actual + (NEW.cantidad * v_factor)),
        stock_actual = stock_actual + (NEW.cantidad * v_factor)
    WHERE id = v_prod_id;
END; //

CREATE TRIGGER trg_compra_detalle_delete 
AFTER DELETE ON compra_detalles
FOR EACH ROW
BEGIN
    DECLARE v_prod_id INT;
    DECLARE v_factor DECIMAL(10,2);
    SELECT producto_id, factor_conversion INTO v_prod_id, v_factor FROM presentaciones WHERE id = OLD.presentacion_id;
    
    UPDATE productos SET stock_actual = stock_actual - (OLD.cantidad * v_factor) WHERE id = v_prod_id;
END; //

CREATE TRIGGER trg_proforma_detalle_insert 
AFTER INSERT ON proforma_detalles
FOR EACH ROW
BEGIN
    DECLARE v_prod_id INT;
    DECLARE v_factor DECIMAL(10,2);
    SELECT producto_id, factor_conversion INTO v_prod_id, v_factor FROM presentaciones WHERE id = NEW.presentacion_id;
    
    UPDATE productos SET stock_actual = stock_actual - (NEW.cantidad * v_factor) WHERE id = v_prod_id;
END; //

CREATE TRIGGER trg_proforma_detalle_delete 
AFTER DELETE ON proforma_detalles
FOR EACH ROW
BEGIN
    DECLARE v_prod_id INT;
    DECLARE v_factor DECIMAL(10,2);
    SELECT producto_id, factor_conversion INTO v_prod_id, v_factor FROM presentaciones WHERE id = OLD.presentacion_id;
    
    UPDATE productos SET stock_actual = stock_actual + (OLD.cantidad * v_factor) WHERE id = v_prod_id;
END; //

DELIMITER ;
