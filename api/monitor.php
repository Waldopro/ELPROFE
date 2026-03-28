<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
checkLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? 'resumen';

if ($action === 'resumen') {
    $stmtStock = $pdo->query("
        SELECT
            SUM(CASE WHEN stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
            SUM(CASE WHEN stock_actual > 0 AND stock_actual < 5 THEN 1 ELSE 0 END) AS stock_bajo
        FROM productos
    ");
    $stock = $stmtStock->fetch() ?: ['sin_stock' => 0, 'stock_bajo' => 0];

    $stmtFiados = $pdo->query("
        SELECT COUNT(*) 
        FROM proformas
        WHERE estado IN ('PENDIENTE', 'PARCIAL')
    ");
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

responseJson(['success' => false, 'message' => 'Acción no válida'], 400);

