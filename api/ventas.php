<?php
// api/ventas.php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
checkLogin();
verifyCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Bootstrap reservas (evita error en instalaciones sin migración)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reservas_carrito (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            device_id VARCHAR(80) NOT NULL,
            estado ENUM('ACTIVE', 'HOLD') DEFAULT 'ACTIVE',
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_reservas_user (usuario_id),
            KEY idx_reservas_exp (expires_at),
            KEY idx_reservas_device (device_id)
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reservas_carrito_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reserva_id INT NOT NULL,
            presentacion_id INT NOT NULL,
            cantidad DECIMAL(10,2) NOT NULL,
            UNIQUE KEY uq_reserva_presentacion (reserva_id, presentacion_id)
        )
    ");
} catch (Exception $e) {}

// GET: Buscar producto por presentación
if ($action === 'buscar_producto') {
    $q = trim($_GET['q'] ?? '');
    $resId = intval($_GET['reservation_id'] ?? 0);
    if(strlen($q) < 2) responseJson([]);
    
    $stmt = $pdo->prepare("
        SELECT 
            pr.id as presentacion_id,
            pr.codigo_barras,
            CONCAT(p.nombre, ' - ', pr.nombre_presentacion) as nombre_completo,
            pr.precio_venta_usd,
            pr.factor_conversion,
            p.stock_actual,
            GREATEST(
              p.stock_actual - (
                SELECT COALESCE(SUM(ri.cantidad * prx.factor_conversion), 0)
                FROM reservas_carrito_items ri
                JOIN reservas_carrito r ON ri.reserva_id = r.id
                JOIN presentaciones prx ON ri.presentacion_id = prx.id
                WHERE prx.producto_id = p.id
                  AND r.expires_at > NOW()
                  AND r.estado IN ('ACTIVE','HOLD')
                  AND ( :resId = 0 OR r.id <> :resId )
              ),
              0
            ) AS stock_disponible_unidades
        FROM presentaciones pr
        JOIN productos p ON pr.producto_id = p.id
        WHERE pr.codigo_barras = :q OR p.nombre LIKE :lq
        ORDER BY p.nombre
        LIMIT 10
    ");
    $stmt->execute(['q' => $q, 'lq' => "%$q%", 'resId' => $resId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $fc = floatval($r['factor_conversion'] ?? 1);
        $dispUnd = floatval($r['stock_disponible_unidades'] ?? 0);
        $r['stock_disponible_presentaciones'] = $fc > 0 ? floor($dispUnd / $fc) : 0;
    }
    unset($r);
    responseJson($rows);
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
    $tipo = ($tipo === 'FIADO') ? 'FIADO' : 'CONTADO';
    $reservationId = intval($_POST['reservation_id'] ?? 0);
    $deviceId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['device_id'] ?? ''));
    
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
        // Regla de negocio: la FACTURA sólo se emite cuando la venta está 100% pagada.
        // Un FIADO nunca puede nacer como FACTURA.
        $tipoDocSolicitado = ($_POST['tipo_doc'] ?? 'PROFORMA') === 'FACTURA' ? 'FACTURA' : 'PROFORMA';
        if ($tipo === 'FIADO' && $tipoDocSolicitado === 'FACTURA') {
            throw new Exception("No se puede emitir FACTURA en ventas a crédito (FIADO). Genere PROFORMA y facture al liquidar.");
        }
        $tipoDoc = $tipoDocSolicitado;
        
        $saldoPendiente = ($tipo === 'FIADO') ? $totalUSD : 0.00;
        $estado = ($tipo === 'FIADO') ? 'PENDIENTE' : 'PAGADO';
        
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
            if (!$ses_id) {
                throw new Exception("No hay caja ABIERTA. Abra su caja en 'Mi Caja' antes de cobrar.");
            }
            
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

        // Liberar reserva del carrito si se envió
        if ($reservationId > 0 && $deviceId !== '') {
            $stmt = $pdo->prepare("DELETE FROM reservas_carrito WHERE id = ? AND usuario_id = ? AND device_id = ?");
            $stmt->execute([$reservationId, $_SESSION['user_id'], $deviceId]);
        }
        
        $pdo->commit();
        $shareToken = generateShareLinkToken((int)$proforma_id);
        responseJson([
            'success' => true,
            'mensaje' => 'Proforma procesada con éxito.',
            'proforma_id' => $proforma_id,
            'share_token' => $shareToken
        ]);
        
    } catch(Exception $e) {
        $pdo->rollBack();
        responseJson(['success' => false, 'message' => $e->getMessage()]);
    }
}

responseJson(['success' => false, 'message' => 'Acción no válida']);
