<?php
// logout.php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    registrarAccion($pdo, 'SISTEMA', 'LOGOUT', 'Usuario cerró sesión manualmente.');
}

session_unset();
session_destroy();
header("Location: /ELPROFE/login");
exit;
