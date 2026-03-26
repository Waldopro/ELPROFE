<?php
// api/ventas.php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
checkLogin();
verifyCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// GET: Buscar producto por presentación
if ($action === 'buscar_producto') {
    $q = trim($_GET['q'] ?? '');
    if(strlen($q) < 2) responseJson([]);
    
    $stmt = $pdo->prepare("SELECT pr.id as presentacion_id, pr.codigo_barras, 
                                  CONCAT(p.nombre, ' - ', pr.nombre_presentacion) as nombre_completo,
                                  pr.precio_venta_usd, pr.factor_conversion, p.stock_actual 
                           FROM presentaciones pr 
                           JOIN productos p ON pr.producto_id = p.id 
                           WHERE pr.codigo_barras = :q OR p.nombre LIKE :lq ORDER BY p.nombre LIMIT 10");
    $stmt->execute(['q' => $q, 'lq' => "%$q%"]);
    responseJson($stmt->fetchAll());
}

// GET: Buscar cliente por cedula/rif (proforma requerimiento)
if ($action === 'buscar_cliente') {
    $q = trim($_GET['q'] ?? '');
    $stmt = $pdo->prepare("SELECT id, cedula_rif, nombre, apellido FROM clientes WHERE cedula_rif = :q LIMIT 1");
    $stmt->execute(['q' => $q]);
    $cliente = $stmt->fetch();
    responseJson(['success' => boolval($cliente), 'cliente' => $cliente]);
}

// POST: Procesar Proforma
if ($action === 'procesar_proforma') {
    $productos = json_decode($_POST['productos'] ?? '[]', true);
    $clienteId = intval($_POST['cliente_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? 'CONTADO'; // CONTADO o FIADO
    $metodoPagoId = intval($_POST['metodo_pago_id'] ?? 1); // Por defecto metodo 1
    
    if (empty($productos) || !is_array($productos)) {
        responseJson(['success' => false, 'message' => 'Carrito vacío']);
    }

    try {
        $pdo->beginTransaction();
        
        $tasa = floatval(getConfig('tasa_usd_bs', $pdo));
        if($tasa <= 0) throw new Exception("Tasa del sistema no configurada.");
        
        // 1. Calcular Totales reales desde la DB para evitar hackeo del cart JS
        $totalUSD = 0;
        foreach ($productos as &$p) {
            $stmt = $pdo->prepare("SELECT pr.precio_venta_usd, pr.factor_conversion, p.stock_actual, p.nombre 
                                   FROM presentaciones pr JOIN productos p ON pr.producto_id = p.id 
                                   WHERE pr.id = ? FOR UPDATE");
            $stmt->execute([$p['presentacion_id']]);
            $dbProd = $stmt->fetch();
            
            if(!$dbProd) throw new Exception("Presentación no encontrada.");
            
            $unidadesDescontar = $p['cantidad'] * $dbProd['factor_conversion'];
            if($dbProd['stock_actual'] < $unidadesDescontar) throw new Exception("Stock insuficiente (Unds) para: ".$dbProd['nombre']);
            
            $p['precio_unitario_usd'] = $dbProd['precio_venta_usd'];
            $p['subtotal_usd'] = round($p['cantidad'] * $dbProd['precio_venta_usd'], 2);
            $totalUSD += $p['subtotal_usd'];
        }
        
        // 2. Si no hay cliente, asignarlo al "Consumidor Final" por defecto
        if ($clienteId === 0) {
            $stmt = $pdo->query("SELECT id FROM clientes WHERE cedula_rif = 'V-00000000'");
            $cf = $stmt->fetch();
            if ($cf) $clienteId = $cf['id'];
            else {
                $pdo->query("INSERT INTO clientes (nombre, cedula_rif) VALUES ('Consumidor Final', 'V-00000000')");
                $clienteId = $pdo->lastInsertId();
            }
        }
        
        // 3. Crear Documento (Proforma o Factura Fiscal)
        $saldoPendiente = ($tipo === 'FIADO') ? $totalUSD : 0.00;
        $estado = ($tipo === 'FIADO') ? 'PENDIENTE' : 'PAGADO';
        $tipoDoc = ($_POST['tipo_doc'] ?? 'PROFORMA') === 'FACTURA' ? 'FACTURA' : 'PROFORMA';
        
        $stmt = $pdo->prepare("INSERT INTO proformas (cliente_id, cajero_id, tipo_documento, tasa_dia_usd_bs, total_usd, saldo_pendiente_usd, estado) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$clienteId, $_SESSION['user_id'], $tipoDoc, $tasa, $totalUSD, $saldoPendiente, $estado]);
        $proforma_id = $pdo->lastInsertId();
        
        // 4. Insertar Detalles (los triggers creados se encargarán de restar el inventario base multiplicando el factor!)
        $stmtDetalle = $pdo->prepare("INSERT INTO proforma_detalles (proforma_id, presentacion_id, cantidad, precio_unitario_usd, subtotal_usd) VALUES (?, ?, ?, ?, ?)");
        foreach ($productos as $p) {
            $stmtDetalle->execute([$proforma_id, $p['presentacion_id'], $p['cantidad'], $p['precio_unitario_usd'], $p['subtotal_usd']]);
        }
        
        // 5. Si es de Contado, registrar Abonos Fragmentados Equivalentes
        if ($tipo === 'CONTADO') {
            $pagos = json_decode($_POST['pagos'] ?? '[]', true);
            if (empty($pagos)) throw new Exception("Debe estipular al menos un método de pago en ventas de contado.");
            
            $stmtAbono = $pdo->prepare("INSERT INTO abonos (proforma_id, tasa_bs_usd, monto_total_usd, nota) VALUES (?, ?, ?, ?)");
            $stmtAbono->execute([$proforma_id, $tasa, $totalUSD, 'Pago Directo Contado']);
            $abono_id = $pdo->lastInsertId();
            
            // Buscamos sesion de caja
            $stmtSesion = $pdo->prepare("SELECT id FROM sesiones_caja WHERE usuario_id = ? AND estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
            $stmtSesion->execute([$_SESSION['user_id']]);
            $ses_id = $stmtSesion->fetchColumn() ?: null;
            
            $stmtPDetalle = $pdo->prepare("INSERT INTO pagos_detalles (abono_id, metodo_pago_id, monto_entregado_bs, monto_entregado_usd, monto_equivalente_usd) VALUES (?, ?, ?, ?, ?)");
            $stmtCaja = $pdo->prepare("INSERT INTO movimientos_caja (sesion_caja_id, metodo_pago_id, tipo_movimiento, monto_bs, monto_usd, referencia_id, referencia_tabla) VALUES (?, ?, 'ENTRADA', ?, ?, ?, 'abonos')");
            
            $suma_pagada_usd = 0;
            
            foreach ($pagos as $pg) {
                $m_usd = floatval($pg['monto_usd'] ?? 0);
                $m_bs = floatval($pg['monto_bs'] ?? 0);
                $equiv_usd = round($m_usd + ($m_bs / $tasa), 2);
                
                if ($equiv_usd > 0) {
                    $stmtPDetalle->execute([$abono_id, $pg['id'], $m_bs, $m_usd, $equiv_usd]);
                    $stmtCaja->execute([$ses_id, $pg['id'], $m_bs, $m_usd, $abono_id]);
                    $suma_pagada_usd += $equiv_usd;
                }
            }
            
            // Tolerancia de 5 centavos por redondeos matematicos de conversión de tasa baja. 
            // Vueltos en contra son tolerados si sobrepasa. 
            if ($suma_pagada_usd < ($totalUSD - 0.05)) {
                throw new Exception("El pago ingresado (\$" . $suma_pagada_usd . ") no cubre la factura (\$" . $totalUSD . ")");
            }
        }
        
        $pdo->commit();
        responseJson(['success' => true, 'mensaje' => 'Proforma procesada con éxito.', 'proforma_id' => $proforma_id]);
        
    } catch(Exception $e) {
        $pdo->rollBack();
        responseJson(['success' => false, 'message' => $e->getMessage()]);
    }
}

responseJson(['success' => false, 'message' => 'Acción no válida']);
