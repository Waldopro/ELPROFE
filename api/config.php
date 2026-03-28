<?php
// api/config.php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
checkLogin();
restrictAdmin();
verifyCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responseJson(['success' => false, 'message' => 'Método no permitido'], 405);
}

$action = $_POST['action'] ?? '';

if ($action === 'update_tasa' && isset($_POST['tasa'])) {
    $tasa = floatval($_POST['tasa']);
    if ($tasa > 0) {
        $stmt = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'tasa_usd_bs'");
        $stmt->execute([$tasa]);
        marcarTasaComoActualizadaHoy($pdo);
        responseJson(['success' => true]);
    } else {
        responseJson(['success' => false, 'message' => 'Tasa inválida']);
    }
}

responseJson(['success' => false, 'message' => 'Acción no encontrada']);
