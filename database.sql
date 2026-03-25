-- 1. CATÁLOGOS BÁSICOS
CREATE TABLE proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    rif VARCHAR(20) UNIQUE,
    telefono VARCHAR(20),
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
    limite_credito DECIMAL(12, 2) DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE metodos_pago (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    moneda_base ENUM('VES', 'USD') NOT NULL,
    activo TINYINT(1) DEFAULT 1
);

-- 2. INVENTARIO
CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_barras VARCHAR(50) UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    foto VARCHAR(255) DEFAULT NULL,
    stock_actual DECIMAL(10, 2) DEFAULT 0.00,
    costo_promedio_usd DECIMAL(12, 2) DEFAULT 0.00,
    precio_venta_usd DECIMAL(12, 2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
    producto_id INT NOT NULL,
    cantidad DECIMAL(10, 2) NOT NULL,
    costo_unitario_usd DECIMAL(12, 2) NOT NULL,
    costo_total_usd DECIMAL(12, 2) NOT NULL,
    FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT
);

-- 2.5 SESIONES DE CAJA (Control estricto de turnos operacionales)
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
    -- FOREIGN KEY (usuario_id) REFERENCES usuarios(id) se asume, la tabla usuarios se crea al final.
    KEY idx_sesion_estado (estado)
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
    -- FOREIGN KEY (cajero_id) REFERENCES usuarios(id)
);

CREATE TABLE proforma_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proforma_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad DECIMAL(10, 2) NOT NULL,
    precio_unitario_usd DECIMAL(12, 2) NOT NULL,
    subtotal_usd DECIMAL(12, 2) NOT NULL,
    FOREIGN KEY (proforma_id) REFERENCES proformas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT
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

-- 6. CONFIGURACION Y USUARIOS
CREATE TABLE configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) UNIQUE NOT NULL,
    valor VARCHAR(255) NOT NULL,
    descripcion TEXT
);

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    rol ENUM('ADMIN', 'CAJERO') DEFAULT 'CAJERO',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insertar datos iniciales
INSERT INTO configuracion (clave, valor, descripcion) VALUES ('tasa_usd_bs', '36.5', 'Tasa de cambio actual (USD a VES)');

INSERT INTO metodos_pago (nombre, moneda_base) VALUES 
('Efectivo USD', 'USD'),
('Efectivo VES', 'VES'),
('Punto de Venta VES', 'VES'),
('Pago Movil VES', 'VES'),
('Zelle USD', 'USD');

-- Usuario admin por defecto y cajero de pruebas: password = 123456
INSERT INTO usuarios (username, password, nombre, rol) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador Global', 'ADMIN'),
('caja01', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cajero Principal', 'CAJERO');

-- Añadir restricciones FK que faltaban por orden de creación de tablas
ALTER TABLE proformas ADD CONSTRAINT fk_proforma_cajero FOREIGN KEY (cajero_id) REFERENCES usuarios(id) ON DELETE RESTRICT;
ALTER TABLE sesiones_caja ADD CONSTRAINT fk_sesion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT;
ALTER TABLE movimientos_caja ADD CONSTRAINT fk_movimiento_sesion FOREIGN KEY (sesion_caja_id) REFERENCES sesiones_caja(id) ON DELETE RESTRICT;

-- ÍNDICES ESTRATÉGICOS PARA VELOCIDAD Y ROBUSTEZ EN CONSULTAS LAAAAAAAARGAS
CREATE INDEX idx_proformas_estado ON proformas(estado);
CREATE INDEX idx_proformas_fecha ON proformas(fecha_emision);
CREATE INDEX idx_proformas_cliente ON proformas(cliente_id);
CREATE INDEX idx_compras_fecha ON compras(fecha);
CREATE INDEX idx_movimientos_fecha ON movimientos_caja(fecha);
CREATE INDEX idx_productos_codigo ON productos(codigo_barras);

-- ==========================================
-- TRIGGERS DE INVENTARIO (BLINDAJE ESTRICTO)
-- ==========================================
DELIMITER //

-- Cuando se registra el detalle de una compra, sumar stock y recalcular costo promedio
CREATE TRIGGER trg_compra_detalle_insert 
AFTER INSERT ON compra_detalles
FOR EACH ROW
BEGIN
    UPDATE productos 
    SET 
        costo_promedio_usd = ((stock_actual * costo_promedio_usd) + (NEW.cantidad * NEW.costo_unitario_usd)) / (stock_actual + NEW.cantidad),
        stock_actual = stock_actual + NEW.cantidad 
    WHERE id = NEW.producto_id;
END; //

-- Cuando se anula o elimina un detalle de compra, revertir stock
CREATE TRIGGER trg_compra_detalle_delete 
AFTER DELETE ON compra_detalles
FOR EACH ROW
BEGIN
    UPDATE productos 
    SET stock_actual = stock_actual - OLD.cantidad 
    WHERE id = OLD.producto_id;
END; //

-- Cuando se inserta un detalle de proforma/venta, restar stock
CREATE TRIGGER trg_proforma_detalle_insert 
AFTER INSERT ON proforma_detalles
FOR EACH ROW
BEGIN
    UPDATE productos 
    SET stock_actual = stock_actual - NEW.cantidad 
    WHERE id = NEW.producto_id;
END; //

-- Cuando se anula o elimina un detalle de proforma, revertir stock (devolución)
CREATE TRIGGER trg_proforma_detalle_delete 
AFTER DELETE ON proforma_detalles
FOR EACH ROW
BEGIN
    UPDATE productos 
    SET stock_actual = stock_actual + OLD.cantidad 
    WHERE id = OLD.producto_id;
END; //

DELIMITER ;

