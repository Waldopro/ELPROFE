<?php
// api/caja.php - endpoints de "tiempo real" para multicaja (polling)
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
checkLogin();
$httpMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($httpMethod !== 'GET') {
    verifyCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'estado_sesion') {
    // Sesión ABIERTA del usuario actual (cajero)
    $stmtSesion = $pdo->prepare("SELECT * FROM sesiones_caja WHERE usuario_id = ? AND estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
    $stmtSesion->execute([$_SESSION['user_id']]);
    $ses = $stmtSesion->fetch();

    if (!$ses) {
        responseJson(['success' => true, 'abierta' => false]);
    }

    // Balance por método en la sesión
    $stmt = $pdo->prepare("
        SELECT mp.id, mp.nombre, mp.moneda_base,
               COALESCE(SUM(CASE WHEN m.tipo_movimiento = 'ENTRADA' THEN m.monto_usd ELSE -m.monto_usd END), 0) AS balance_usd,
               COALESCE(SUM(CASE WHEN m.tipo_movimiento = 'ENTRADA' THEN m.monto_bs ELSE -m.monto_bs END), 0) AS balance_bs
        FROM metodos_pago mp
        LEFT JOIN movimientos_caja m
               ON mp.id = m.metodo_pago_id
              AND m.sesion_caja_id = ?
        GROUP BY mp.id
        ORDER BY mp.id ASC
    ");
    $stmt->execute([(int)$ses['id']]);
    $balances = $stmt->fetchAll();

    // Últimos movimientos de la sesión
    $stmtMov = $pdo->prepare("
        SELECT m.id, m.fecha, m.tipo_movimiento, m.monto_usd, m.monto_bs, m.referencia_id, m.referencia_tabla, mp.nombre AS metodo_nombre
        FROM movimientos_caja m
        JOIN metodos_pago mp ON m.metodo_pago_id = mp.id
        WHERE m.sesion_caja_id = ?
        ORDER BY m.fecha DESC
        LIMIT 25
    ");
    $stmtMov->execute([(int)$ses['id']]);
    $movs = $stmtMov->fetchAll();

    responseJson([
        'success' => true,
        'abierta' => true,
        'sesion' => [
            'id' => (int)$ses['id'],
            'fecha_apertura' => $ses['fecha_apertura'],
            'monto_inicial_usd' => (float)$ses['monto_inicial_usd'],
            'monto_inicial_bs' => (float)$ses['monto_inicial_bs'],
        ],
        'balances' => $balances,
        'movimientos' => $movs,
        'server_time' => date('Y-m-d H:i:s'),
    ]);
}

responseJson(['success' => false, 'message' => 'Acción no válida'], 400);
