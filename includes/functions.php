<?php
// includes/functions.php
session_start();

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

// Retorna JSON y termina la ejecución
function responseJson($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Formatear montos
function formatMoney($amount) {
    return number_format($amount, 2, '.', ',');
}

// ==========================================
// SEGURIDAD: XSS y CSRF
// ==========================================

// Escapar salidas para prevenir XSS
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Generar token CSRF si no existe
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Renderizar input oculto con CSRF
function csrfField() {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" id="csrf_token" value="' . e($token) . '">';
}

// Verificar token CSRF (Para peticiones POST)
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die("Acción no permitida (Error CSRF).");
    }
}
