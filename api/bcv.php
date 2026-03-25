<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Permiso denegado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
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
        echo json_encode([
            'success' => true, 
            'tasa' => $tasa_float, 
            'message' => 'Tasa BCV obtenida exitosamente.'
        ]);
        exit;
    } else {
        // En caso de que sea fin de semana o falle, intentamos forzar obtener la de Base de Datos
        $stmt = $pdo->query("SELECT usd FROM tasas_bcv ORDER BY fecha DESC LIMIT 1");
        $last_tasa = $stmt->fetchColumn();
        
        if ($last_tasa) {
            echo json_encode([
                'success' => true, 
                'tasa' => floatval($last_tasa), 
                'message' => 'Tasa recuperada de la base de datos (Fin de Semana u Offline).'
            ]);
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Fallo la extracción desde el servidor del BCV.']);
    }
}
