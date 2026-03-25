<?php
// includes/db.php
$host = 'localhost';
$db   = 'elprofe_db';
$user = 'root';
$pass = '1704'; // Adjust as necessary
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Para entornos en producción se recomienda loggear el error.
    exit("Database connection failed. Make sure the database exists and credentials are valid.");
}

// Función auxiliar para obtener la configuración global (como la tasa)
function getConfig($key, $pdo) {
    $stmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = ?");
    $stmt->execute([$key]);
    $res = $stmt->fetch();
    return $res ? $res['valor'] : null;
}
