<?php
// api/config.php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
checkLogin();
restrictAdmin();

$action = $_POST['action'] ?? '';

if ($action === 'update_tasa' && isset($_POST['tasa'])) {
    $tasa = floatval($_POST['tasa']);
    if ($tasa > 0) {
        $stmt = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'tasa_usd_bs'");
        $stmt->execute([$tasa]);
        responseJson(['success' => true]);
    } else {
        responseJson(['success' => false, 'message' => 'Tasa inválida']);
    }
}

responseJson(['success' => false, 'message' => 'Acción no encontrada']);
