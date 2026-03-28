<?php
// bcv_scraper.php - Ejecutado en el login
require_once __DIR__ . '/../includes/db.php';

function extraerTasaBCVHtml($html, $moneda_id) {
    // Busca exactamente el id "dolar" o "euro" y recorta el string dentro la etiqueta <strong>
    $regex = '/<div\s+id="' . preg_quote($moneda_id, '/') . '"[^>]*>.*?<strong>\s*([0-9\.,]+)\s*<\/strong>/si';
    
    if (preg_match($regex, $html, $match)) {
        // Limpiamos la coma por un punto para guardarlo en la Base de Datos
        $valor = str_replace(',', '.', str_replace('.', '', trim($match[1])));
        return (float) $valor;
    }
    return null;
}

function actualizarTasaManual($pdo) {
    $hoy = date('Y-m-d');
    $dia_semana = (int) date('w');
    
    // Si es Sábado (6) o Domingo (0) no forzamos nada porque la página del BCV no actualiza
    // En su lugar, deberíamos usar la última tasa disponible.
    if ($dia_semana === 0 || $dia_semana === 6) {
        // Podemos registrar que se intentó pero es fin de semana, o simplemente retornar
        return false;
    }

    // Verificar si ya existe la tasa de hoy
    $stmt = $pdo->prepare("SELECT id FROM tasas_bcv WHERE fecha = ?");
    $stmt->execute([$hoy]);
    if ($stmt->fetch()) {
        // Ya tenemos la tasa de hoy guardada, no hacemos nada
        return true;
    }

    $url = 'https://www.bcv.org.ve/';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // Mantener validación TLS activa para evitar MITM.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ELPROFE-POS/1.0 (+https://localhost/ELPROFE)');

    // Permite configurar un bundle CA si el servidor lo requiere.
    $caBundle = getenv('CURL_CA_BUNDLE') ?: getenv('SSL_CERT_FILE');
    if ($caBundle && is_file($caBundle)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Cancelar a los 10 segundos si su página está caída
    
    $html = curl_exec($ch);
    curl_close($ch);

    if($html) {
        $usd = extraerTasaBCVHtml($html, 'dolar');
        $eur = extraerTasaBCVHtml($html, 'euro');

        if ($usd > 0) {
            // Insertar en la tabla tasas_bcv
            $stmtInsert = $pdo->prepare("INSERT IGNORE INTO tasas_bcv (fecha, usd, eur) VALUES (?, ?, ?)");
            $stmtInsert->execute([$hoy, $usd, $eur]);

            // Actualizar la configuración global del sistema
            $stmtConfig = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'tasa_usd_bs'");
            $stmtConfig->execute([$usd]);

            $stmtConfig2 = $pdo->prepare("INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES ('tasa_tipo', 'BCV', 'Tipo')");
            $stmtConfig2->execute();

            $stmtConfig3 = $pdo->prepare("UPDATE configuracion SET valor = 'BCV' WHERE clave = 'tasa_tipo'");
            $stmtConfig3->execute();

            $stmtFechaIns = $pdo->prepare("INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES ('tasa_fecha', ?, 'Fecha de última actualización de tasa')");
            $stmtFechaIns->execute([$hoy]);
            $stmtFechaUp = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'tasa_fecha'");
            $stmtFechaUp->execute([$hoy]);

            return $usd;
        }
    }
    return false;
}

// Se comenta la ejecución automática para evitar bucles o lentitud innecesaria al incluir el archivo
// actualizarTasaManual($pdo);
?>
