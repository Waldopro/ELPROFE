-- 002: Reservas de stock (tiempo real multicaja)

CREATE TABLE IF NOT EXISTS reservas_carrito (
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

CREATE TABLE IF NOT EXISTS reservas_carrito_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reserva_id INT NOT NULL,
  presentacion_id INT NOT NULL,
  cantidad DECIMAL(10,2) NOT NULL,
  UNIQUE KEY uq_reserva_presentacion (reserva_id, presentacion_id),
  CONSTRAINT fk_reserva_items_reserva FOREIGN KEY (reserva_id) REFERENCES reservas_carrito(id) ON DELETE CASCADE,
  CONSTRAINT fk_reserva_items_presentacion FOREIGN KEY (presentacion_id) REFERENCES presentaciones(id) ON DELETE RESTRICT
);

