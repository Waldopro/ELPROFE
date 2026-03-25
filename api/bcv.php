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
    
    // Obtener HTML del BCV
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://www.bcv.org.ve/",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false, // Evitar problemas con certificados locales en entornos de prueba
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36'
    ]);
    
    $html = curl_exec($curl);
    
    if (curl_errno($curl)) {
        echo json_encode(['success' => false, 'message' => 'El portal del BCV no responde o tu servidor no tiene salida a internet.']);
        exit;
    }
    curl_close($curl);
    
    // Scrapping por Regex amigable
    if (preg_match('/<div id="dolar".*?>.*?<strong>\s*(.*?)\s*<\/strong>.*?<\/div>/is', $html, $matches)) {
        $tasa_bruta = trim($matches[1]);
        $tasa_limpia = str_replace(',', '.', $tasa_bruta);
        $tasa_float = floatval($tasa_limpia);
        
        if ($tasa_float > 0) {
            // Actualizar DB
            $stmt = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'tasa_usd_bs'");
            $stmt->execute([$tasa_float]);
            
            // Garantizar que exista la clave tasa_tipo
            $stmtInsert = $pdo->prepare("INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES ('tasa_tipo', 'BCV', 'Tipo')");
            $stmtInsert->execute();
            
            $stmtUpdate = $pdo->prepare("UPDATE configuracion SET valor = 'BCV' WHERE clave = 'tasa_tipo'");
            $stmtUpdate->execute();
            
            echo json_encode([
                'success' => true, 
                'tasa' => $tasa_float, 
                'message' => 'Tasa extraída y configurada con BCV exitosamente.'
            ]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Falló el parseo de la tasa. Es probable que la estructura del BCV cambiara.']);
}
