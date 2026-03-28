<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
checkLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? 'resumen';

if ($action === 'resumen') {
    $whereFiados = "estado IN ('PENDIENTE', 'PARCIAL')";
    $paramsFiados = [];
    if (!isAdmin()) {
        $whereFiados .= " AND cajero_id = ?";
        $paramsFiados[] = (int)$_SESSION['user_id'];
    }

    $stmtStock = $pdo->query("
        SELECT
            SUM(CASE WHEN stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
            SUM(CASE WHEN stock_actual > 0 AND stock_actual < 5 THEN 1 ELSE 0 END) AS stock_bajo
        FROM productos
    ");
    $stock = $stmtStock->fetch() ?: ['sin_stock' => 0, 'stock_bajo' => 0];

    $stmtFiados = $pdo->prepare("
        SELECT COUNT(*) 
        FROM proformas
        WHERE $whereFiados
    ");
    $stmtFiados->execute($paramsFiados);
    $fiadosPend = (int)$stmtFiados->fetchColumn();

    responseJson([
        'success' => true,
        'stock' => [
            'sin_stock' => (int)($stock['sin_stock'] ?? 0),
            'stock_bajo' => (int)($stock['stock_bajo'] ?? 0),
        ],
        'fiados_pendientes' => $fiadosPend,
        'server_time' => date('Y-m-d H:i:s')
    ]);
}

if ($action === 'feed') {
    $notifications = [];
    $whereFiados = "p.estado IN ('PENDIENTE', 'PARCIAL')";
    $paramsFiados = [];
    if (!isAdmin()) {
        $whereFiados .= " AND p.cajero_id = ?";
        $paramsFiados[] = (int)$_SESSION['user_id'];
    }

    $stmtStock = $pdo->query("
        SELECT id, nombre, codigo_interno, stock_actual
        FROM productos
        WHERE stock_actual <= 0 OR stock_actual < 5
        ORDER BY stock_actual ASC, nombre ASC
        LIMIT 12
    ");
    $stockRows = $stmtStock->fetchAll() ?: [];
    foreach ($stockRows as $r) {
        $stock = (float)$r['stock_actual'];
        $notifications[] = [
            'id' => 'stock_' . (int)$r['id'],
            'tipo' => 'inventario',
            'nivel' => $stock <= 0 ? 'critico' : 'alerta',
            'titulo' => $stock <= 0 ? 'Producto sin stock' : 'Producto con stock bajo',
            'mensaje' => $r['nombre'] . ' (' . ($r['codigo_interno'] ?: 'S/C') . ') - Stock: ' . number_format($stock, 2),
            'fecha' => date('d/m/Y H:i'),
            'meta' => ['producto_id' => (int)$r['id']]
        ];
    }

    $stmtFiados = $pdo->prepare("
        SELECT p.id, p.fecha_emision, p.saldo_pendiente_usd, c.nombre AS cliente
        FROM proformas p
        JOIN clientes c ON c.id = p.cliente_id
        WHERE $whereFiados
        ORDER BY p.fecha_emision ASC
        LIMIT 8
    ");
    $stmtFiados->execute($paramsFiados);
    $fiados = $stmtFiados->fetchAll() ?: [];
    foreach ($fiados as $f) {
        $notifications[] = [
            'id' => 'fiado_' . (int)$f['id'],
            'tipo' => 'cobranza',
            'nivel' => 'info',
            'titulo' => 'Fiado pendiente de cobro',
            'mensaje' => 'Doc #' . str_pad((string)$f['id'], 6, '0', STR_PAD_LEFT) . ' - ' . $f['cliente'] . ' - $' . number_format((float)$f['saldo_pendiente_usd'], 2),
            'fecha' => date('d/m/Y H:i', strtotime((string)$f['fecha_emision'])),
            'meta' => ['proforma_id' => (int)$f['id']]
        ];
    }

    // Alertas de seguridad solo para administradores:
    // intentos recientes de acceso no autorizado por cajeros.
    if (isAdmin()) {
        $stmtSec = $pdo->query("
            SELECT a.id, a.fecha, a.detalle, a.ip, u.nombre AS usuario_nombre, u.username
            FROM bitacora_acciones a
            LEFT JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.modulo = 'SEGURIDAD'
              AND a.accion = 'ACCESO_DENEGADO'
              AND a.fecha >= DATE_SUB(NOW(), INTERVAL 2 DAY)
            ORDER BY a.fecha DESC
            LIMIT 10
        ");
        $rowsSec = $stmtSec->fetchAll() ?: [];
        foreach ($rowsSec as $s) {
            $txt = (string)($s['detalle'] ?? '');
            $payload = json_decode($txt, true);
            $detalle = is_array($payload) ? (string)($payload['detalle'] ?? $txt) : $txt;
            $moduloIntentado = '';
            if (preg_match("/m[oó]dulo '([^']+)'/u", $detalle, $m)) {
                $moduloIntentado = trim((string)$m[1]);
            }
            if ($moduloIntentado === '') $moduloIntentado = 'restringido';
            $usuario = trim((string)($s['usuario_nombre'] ?? 'Usuario') . ' (' . (string)($s['username'] ?? 'N/A') . ')');

            $notifications[] = [
                'id' => 'seg_' . (int)$s['id'],
                'tipo' => 'seguridad',
                'nivel' => 'alerta',
                'titulo' => 'Intento de acceso restringido',
                'mensaje' => $usuario . ' intentó abrir módulo ' . $moduloIntentado,
                'fecha' => date('d/m/Y H:i', strtotime((string)$s['fecha'])),
                'meta' => ['bitacora_id' => (int)$s['id']]
            ];
        }
    }

    // Orden de severidad: critico > alerta > info
    usort($notifications, static function ($a, $b) {
        $rank = ['critico' => 3, 'alerta' => 2, 'info' => 1];
        return ($rank[$b['nivel']] ?? 0) <=> ($rank[$a['nivel']] ?? 0);
    });

    responseJson([
        'success' => true,
        'count' => count($notifications),
        'items' => array_slice($notifications, 0, 20),
        'server_time' => date('Y-m-d H:i:s')
    ]);
}

responseJson(['success' => false, 'message' => 'Acción no válida'], 400);
