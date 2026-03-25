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
    
    // Usar API de PyDolarVenezuela (Súper estable para entornos locales sin firewalls raros)
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://pydolarvenezuela-api.vercel.app/api/v1/dollar?page=bcv",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        echo json_encode(['success' => false, 'message' => 'Fallo de conexión. Revisa tu internet.']);
        exit;
    }
    
    $data = json_decode($response, true);
    
    // El formato de pydolar maneja monitors.bcv.price
    if (isset($data['monitors']['bcv']['price'])) {
        $tasa_float = floatval($data['monitors']['bcv']['price']);
        
        if ($tasa_float > 0) {
            // Actualizar DB
            $stmt = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'tasa_usd_bs'");
            $stmt->execute([$tasa_float]);
            
            // Garantizar que exista la clave tasa_tipo e inyectar fecha de ultima sync
            $stmtInsert = $pdo->prepare("INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES ('tasa_tipo', 'BCV', 'Tipo')");
            $stmtInsert->execute();
            
            $stmtUpdate = $pdo->prepare("UPDATE configuracion SET valor = 'BCV' WHERE clave = 'tasa_tipo'");
            $stmtUpdate->execute();
            
            echo json_encode([
                'success' => true, 
                'tasa' => $tasa_float, 
                'message' => 'Tasa BCV obtenida exitosamente.'
            ]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Falló el parseo JSON de la pasarela BCV. Contacte al programador.']);
}
