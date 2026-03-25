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
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Formatear montos
function formatMoney($amount) {
    return number_format($amount, 2, '.', ',');
}
