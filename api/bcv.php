<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
checkLogin();
verifyCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

if (!isAdmin()) {
    responseJson(['success' => false, 'message' => 'Permiso denegado.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'bcv_scraper.php';
    
    $tasa_result = actualizarTasaManual($pdo);
    
    if ($tasa_result === true) {
        // Significa que ya está actualizada en la BD para hoy
        $stmt = $pdo->query("SELECT usd FROM tasas_bcv ORDER BY fecha DESC LIMIT 1");
        $tasa_float = (float) $stmt->fetchColumn();
    } elseif (is_numeric($tasa_result) && $tasa_result > 0) {
        $tasa_float = $tasa_result;
    } else {
        $tasa_float = 0;
    }
    
    if ($tasa_float > 0) {
        responseJson([
            'success' => true, 
            'tasa' => $tasa_float, 
            'message' => 'Tasa BCV obtenida exitosamente.'
        ]);
    } else {
        // En caso de que sea fin de semana o falle, intentamos forzar obtener la de Base de Datos
        $stmt = $pdo->query("SELECT usd FROM tasas_bcv ORDER BY fecha DESC LIMIT 1");
        $last_tasa = $stmt->fetchColumn();
        
        if ($last_tasa) {
            responseJson([
                'success' => true, 
                'tasa' => floatval($last_tasa), 
                'message' => 'Tasa recuperada de la base de datos (Fin de Semana u Offline).'
            ]);
        }
        
        responseJson(['success' => false, 'message' => 'Fallo la extracción desde el servidor del BCV.'], 502);
    }
}
responseJson(['success' => false, 'message' => 'Método no permitido'], 405);
