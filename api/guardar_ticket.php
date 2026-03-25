<?php
// api/guardar_ticket.php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responseJson(['success' => false, 'message' => 'Método no permitido']);
}

$id = intval($_POST['id'] ?? 0);
$base64 = $_POST['image'] ?? '';
$tipo = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['tipo'] ?? 'ticket');

if ($id <= 0 || empty($base64)) {
    responseJson(['success' => false, 'message' => 'Faltan datos']);
}

// Limpiar base64 estandar de canvas data:image/png;base64,...
if (strpos($base64, ',') !== false) {
    @list($type, $base64) = explode(',', $base64);
}

$decodedData = base64_decode($base64);
if ($decodedData === false) {
    responseJson(['success' => false, 'message' => 'Error al decodificar imagen']);
}

$dir = '../assets/tickets';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$filename = "{$tipo}_{$id}.png";
$filepath = "{$dir}/{$filename}";

if (file_put_contents($filepath, $decodedData) !== false) {
    // Retornamos la URL pública (en base al host actual para ambientes productivos)
    $host = $_SERVER['HTTP_HOST'];
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
    $url = $protocol . $host . "/ELPROFE/assets/tickets/" . $filename;
    
    responseJson(['success' => true, 'url' => $url, 'filename' => $filename]);
} else {
    responseJson(['success' => false, 'message' => 'Error al guardar el archivo en servidor']);
}
