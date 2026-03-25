<?php
// api/producto.php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
checkLogin();

// Si es upload via file multipart, CSRF puede ir en header o POST
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
verifyCsrfToken($token);

$action = $_POST['action'] ?? '';

if ($action === 'upload_foto') {
    $id = intval($_POST['producto_id'] ?? 0);
    
    if ($id <= 0 || !isset($_FILES['foto'])) {
        responseJson(['success' => false, 'message' => 'Faltan parámetros.'], 400);
    }
    
    $file = $_FILES['foto'];
    
    if($file['error'] !== UPLOAD_ERR_OK) {
        responseJson(['success' => false, 'message' => 'Error al subir archivo. (Error Code: '.$file['error'].')'], 400);
    }
    
    $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!array_key_exists($mime, $exts)) {
        responseJson(['success' => false, 'message' => 'Formato no permitido. Use JPG, PNG o WEBP.'], 400);
    }
    
    // File size 2MB
    if ($file['size'] > 2 * 1024 * 1024) {
        responseJson(['success' => false, 'message' => 'Archivo muy grande. Máximo 2MB.'], 400);
    }
    
    // Upload dir
    $uploadDir = __DIR__ . '/../assets/img/productos/';
    if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $filename = 'prod_' . $id . '_' . time() . '.' . $exts[$mime];
    $destination = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Obtenemos la foto vieja por si la queremos borrar, esto es opcional pero bueno.
        $stmt = $pdo->prepare("SELECT foto FROM productos WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        
        if($old && file_exists($uploadDir . $old)) {
            @unlink($uploadDir . $old);
        }
        
        $stmt = $pdo->prepare("UPDATE productos SET foto = ? WHERE id = ?");
        $stmt->execute([$filename, $id]);
        
        responseJson(['success' => true, 'filename' => $filename]);
    } else {
        responseJson(['success' => false, 'message' => 'No se pudo guardar la imagen.'], 500);
    }
}

responseJson(['success' => false, 'message' => 'Acción no encontrada'], 404);
