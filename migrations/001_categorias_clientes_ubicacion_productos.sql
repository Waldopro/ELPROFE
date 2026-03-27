-- Migración para instalaciones existentes
-- 001: Categorías + Ubicación clientes + Proveedores completos + Atributos producto

ALTER TABLE proveedores
  ADD COLUMN contacto VARCHAR(100) NULL AFTER rif,
  ADD COLUMN email VARCHAR(120) NULL AFTER telefono;

ALTER TABLE clientes
  ADD COLUMN ubicacion TEXT NULL AFTER direccion;

CREATE TABLE IF NOT EXISTS categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL UNIQUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE productos
  ADD COLUMN codigo_interno VARCHAR(50) UNIQUE NULL AFTER id,
  ADD COLUMN marca VARCHAR(80) NULL AFTER nombre,
  ADD COLUMN categoria_id INT NULL AFTER descripcion,
  ADD COLUMN stock_minimo DECIMAL(10,2) DEFAULT 0.00 AFTER stock_actual,
  ADD COLUMN exento_iva TINYINT(1) DEFAULT 0 AFTER costo_promedio_usd;

ALTER TABLE productos
  ADD CONSTRAINT fk_producto_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL;

