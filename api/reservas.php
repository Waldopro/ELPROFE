<?php
// api/reservas.php - reservas de stock para multicaja (polling/actualización)
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
checkLogin();
verifyCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

// Bootstrap tablas si no existen (para ambientes sin migración)
$pdo->exec("
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
    )
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS reservas_carrito_items (
      id INT AUTO_INCREMENT PRIMARY KEY,
      reserva_id INT NOT NULL,
      presentacion_id INT NOT NULL,
      cantidad DECIMAL(10,2) NOT NULL,
      UNIQUE KEY uq_reserva_presentacion (reserva_id, presentacion_id),
      FOREIGN KEY (reserva_id) REFERENCES reservas_carrito(id) ON DELETE CASCADE,
      FOREIGN KEY (presentacion_id) REFERENCES presentaciones(id) ON DELETE RESTRICT
    )
");

// Limpieza rápida de reservas expiradas
$pdo->exec("DELETE FROM reservas_carrito WHERE expires_at < NOW()");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'upsert') {
    $reservationId = intval($_POST['reservation_id'] ?? 0);
    $deviceId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['device_id'] ?? ''));
    $estado = ($_POST['estado'] ?? 'ACTIVE') === 'HOLD' ? 'HOLD' : 'ACTIVE';
    $ttl = intval($_POST['ttl_seconds'] ?? 180);
    if ($ttl < 60) $ttl = 60;
    if ($ttl > 1800) $ttl = 1800;

    $items = json_decode($_POST['items'] ?? '[]', true);
    if (!is_array($items)) $items = [];

    if ($deviceId === '') {
        responseJson(['success' => false, 'message' => 'device_id requerido'], 400);
    }

    try {
        $pdo->beginTransaction();

        if ($reservationId > 0) {
            $stmt = $pdo->prepare("SELECT id FROM reservas_carrito WHERE id = ? AND usuario_id = ? AND device_id = ? LIMIT 1");
            $stmt->execute([$reservationId, $_SESSION['user_id'], $deviceId]);
            $exists = $stmt->fetchColumn();
            if (!$exists) $reservationId = 0;
        }

        if ($reservationId === 0) {
            $stmt = $pdo->prepare("INSERT INTO reservas_carrito (usuario_id, device_id, estado, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))");
            $stmt->execute([$_SESSION['user_id'], $deviceId, $estado, $ttl]);
            $reservationId = (int)$pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare("UPDATE reservas_carrito SET estado = ?, expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ? AND usuario_id = ? AND device_id = ?");
            $stmt->execute([$estado, $ttl, $reservationId, $_SESSION['user_id'], $deviceId]);
            $pdo->prepare("DELETE FROM reservas_carrito_items WHERE reserva_id = ?")->execute([$reservationId]);
        }

        if (count($items) > 0) {
            $stmtIt = $pdo->prepare("INSERT INTO reservas_carrito_items (reserva_id, presentacion_id, cantidad) VALUES (?, ?, ?)");
            foreach ($items as $it) {
                $pid = intval($it['presentacion_id'] ?? 0);
                $cant = floatval($it['cantidad'] ?? 0);
                if ($pid > 0 && $cant > 0) {
                    $stmtIt->execute([$reservationId, $pid, $cant]);
                }
            }
        }

        $pdo->commit();
        responseJson(['success' => true, 'reservation_id' => $reservationId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        responseJson(['success' => false, 'message' => 'Error actualizando reserva'], 500);
    }
}

if ($action === 'delete') {
    $reservationId = intval($_POST['reservation_id'] ?? 0);
    $deviceId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['device_id'] ?? ''));
    if ($reservationId <= 0 || $deviceId === '') {
        responseJson(['success' => false, 'message' => 'Parámetros inválidos'], 400);
    }
    $stmt = $pdo->prepare("DELETE FROM reservas_carrito WHERE id = ? AND usuario_id = ? AND device_id = ?");
    $stmt->execute([$reservationId, $_SESSION['user_id'], $deviceId]);
    responseJson(['success' => true]);
}

if ($action === 'check_ids') {
    $deviceId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['device_id'] ?? $_GET['device_id'] ?? ''));
    $idsRaw = $_POST['ids'] ?? $_GET['ids'] ?? '[]';
    $ids = json_decode((string)$idsRaw, true);
    if (!is_array($ids)) $ids = [];
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($v) => $v > 0)));
    if ($deviceId === '' || count($ids) === 0) {
        responseJson(['success' => true, 'valid_ids' => []]);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "
        SELECT id
        FROM reservas_carrito
        WHERE id IN ($placeholders)
          AND usuario_id = ?
          AND device_id = ?
          AND estado = 'HOLD'
          AND expires_at > NOW()
    ";
    $params = array_merge($ids, [(int)$_SESSION['user_id'], $deviceId]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $valid = array_map('intval', array_column($stmt->fetchAll(), 'id'));

    responseJson(['success' => true, 'valid_ids' => $valid]);
}

responseJson(['success' => false, 'message' => 'Acción no válida'], 400);
