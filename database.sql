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

-- 4. VENTAS (PROFORMAS Y FACTURAS)
CREATE TABLE proformas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
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

-- Usuario admin por defecto: admin / 123456
INSERT INTO usuarios (username, password, nombre, rol) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'ADMIN');
