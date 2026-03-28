<?php
// api/ventas.php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
checkLogin();

// Solo validamos CSRF en operaciones de escritura (POST/PUT/PATCH/DELETE).
$httpMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($httpMethod !== 'GET') {
    verifyCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function tipoMetodoPagoApi(string $nombre): string {
    $n = strtolower(trim($nombre));
    if (str_contains($n, 'usd') || str_contains($n, 'dolar') || str_contains($n, 'dólar') || str_contains($n, 'usdt') || str_contains($n, 'binance')) {
        return 'USD';
    }
    return 'BS';
}

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

    try {
        $stmt = $pdo->prepare("
            SELECT 
                pr.id as presentacion_id,
                pr.codigo_barras,
                p.codigo_interno,
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
                      AND ( ? = 0 OR r.id <> ? )
                  ),
                  0
                ) AS stock_disponible_unidades
            FROM presentaciones pr
            JOIN productos p ON pr.producto_id = p.id
            WHERE pr.codigo_barras = ? OR p.codigo_barras = ? OR p.codigo_interno = ? OR p.nombre LIKE ?
            ORDER BY p.nombre
            LIMIT 10
        ");
        $stmt->execute([$resId, $resId, $q, $q, $q, "%$q%"]);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        // Fallback para instalaciones con esquema parcial de reservas.
        $stmt = $pdo->prepare("
            SELECT
                pr.id AS presentacion_id,
                pr.codigo_barras,
                p.codigo_interno,
                CONCAT(p.nombre, ' - ', pr.nombre_presentacion) AS nombre_completo,
                pr.precio_venta_usd,
                pr.factor_conversion,
                p.stock_actual,
                p.stock_actual AS stock_disponible_unidades
            FROM presentaciones pr
            JOIN productos p ON pr.producto_id = p.id
            WHERE pr.codigo_barras = ? OR p.codigo_barras = ? OR p.codigo_interno = ? OR p.nombre LIKE ?
            ORDER BY p.nombre
            LIMIT 10
        ");
        $stmt->execute([$q, $q, $q, "%$q%"]);
        $rows = $stmt->fetchAll();
    }

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

// GET: Buscar clientes para modal (selección rápida)
if ($action === 'buscar_clientes') {
    $q = trim((string)($_GET['q'] ?? ''));
    $limit = max(10, min(120, intval($_GET['limit'] ?? 30)));
    $like = '%' . $q . '%';

    $sql = "
        SELECT id, cedula_rif, nombre, apellido, telefono
        FROM clientes
        WHERE (? = '' OR cedula_rif LIKE ? OR nombre LIKE ? OR apellido LIKE ?)
        ORDER BY nombre ASC, apellido ASC
        LIMIT {$limit}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$q, $like, $like, $like]);
    responseJson(['success' => true, 'rows' => $stmt->fetchAll()]);
}

// POST: Crear cliente rápido desde POS
if ($action === 'crear_cliente_rapido') {
    $cedula = trim((string)($_POST['cedula_rif'] ?? ''));
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $apellido = trim((string)($_POST['apellido'] ?? ''));
    $telefono = trim((string)($_POST['telefono'] ?? ''));

    if ($cedula === '' || $nombre === '') {
        responseJson(['success' => false, 'message' => 'Cédula/RIF y nombre son obligatorios.'], 400);
    }
    if (strlen($cedula) > 20 || strlen($nombre) > 100 || strlen($apellido) > 100 || strlen($telefono) > 20) {
        responseJson(['success' => false, 'message' => 'Datos inválidos o demasiado largos.'], 400);
    }

    $stmtExist = $pdo->prepare("SELECT id, cedula_rif, nombre, apellido, telefono FROM clientes WHERE cedula_rif = ? LIMIT 1");
    $stmtExist->execute([$cedula]);
    $exist = $stmtExist->fetch();
    if ($exist) {
        responseJson(['success' => true, 'cliente' => $exist, 'message' => 'Cliente ya existía, se seleccionó automáticamente.']);
    }

    $stmt = $pdo->prepare("INSERT INTO clientes (cedula_rif, nombre, apellido, telefono) VALUES (?, ?, ?, ?)");
    $stmt->execute([$cedula, $nombre, $apellido, $telefono]);
    $id = (int)$pdo->lastInsertId();
    registrarAccion($pdo, 'CLIENTES', 'CREAR_RAPIDO', "Cliente creado desde POS: {$cedula}");

    responseJson([
        'success' => true,
        'cliente' => [
            'id' => $id,
            'cedula_rif' => $cedula,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'telefono' => $telefono
        ],
        'message' => 'Cliente creado correctamente.'
    ]);
}

// GET: Catálogo de productos para modal
if ($action === 'catalogo_productos') {
    $q = trim($_GET['q'] ?? '');
    $resId = intval($_GET['reservation_id'] ?? 0);
    $limit = max(20, min(200, intval($_GET['limit'] ?? 80)));
    $like = '%' . $q . '%';

    $stmt = $pdo->prepare("
        SELECT
            pr.id AS presentacion_id,
            p.nombre AS producto_nombre,
            pr.nombre_presentacion,
            p.codigo_interno,
            pr.codigo_barras,
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
                  AND ( ? = 0 OR r.id <> ? )
              ),
              0
            ) AS stock_disponible_unidades
        FROM presentaciones pr
        JOIN productos p ON p.id = pr.producto_id
        WHERE (? = '' OR p.nombre LIKE ? OR p.codigo_interno LIKE ? OR pr.codigo_barras LIKE ?)
        ORDER BY p.nombre ASC
        LIMIT ?
    ");
    $stmt->execute([$resId, $resId, $q, $like, $like, $like, $limit]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $fc = max(1.0, floatval($r['factor_conversion'] ?? 1));
        $stockUnd = floatval($r['stock_disponible_unidades'] ?? 0);
        $r['stock_disponible_presentaciones'] = max(0, (int)floor($stockUnd / $fc));
        $r['nombre_completo'] = $r['producto_nombre'] . ' - ' . $r['nombre_presentacion'];
    }
    unset($r);
    responseJson(['success' => true, 'rows' => $rows]);
}

// GET: Alertas de stock
if ($action === 'stock_alertas') {
    $stmt = $pdo->query("
        SELECT
            SUM(CASE WHEN stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
            SUM(CASE WHEN stock_actual > 0 AND stock_actual < 5 THEN 1 ELSE 0 END) AS stock_bajo
        FROM productos
    ");
    $sum = $stmt->fetch() ?: ['sin_stock' => 0, 'stock_bajo' => 0];

    $stmtDet = $pdo->query("
        SELECT id, nombre, codigo_interno, stock_actual
        FROM productos
        WHERE stock_actual <= 0 OR stock_actual < 5
        ORDER BY stock_actual ASC, nombre ASC
        LIMIT 15
    ");
    responseJson([
        'success' => true,
        'sin_stock' => (int)($sum['sin_stock'] ?? 0),
        'stock_bajo' => (int)($sum['stock_bajo'] ?? 0),
        'detalles' => $stmtDet->fetchAll()
    ]);
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
            $stmt = $pdo->prepare("SELECT pr.precio_venta_usd, pr.factor_conversion, p.stock_actual, p.nombre, p.id AS producto_id
                                   FROM presentaciones pr JOIN productos p ON pr.producto_id = p.id 
                                   WHERE pr.id = ? FOR UPDATE");
            $stmt->execute([$p['presentacion_id']]);
            $dbProd = $stmt->fetch();
            
            if(!$dbProd) throw new Exception("Presentación no encontrada.");
            
            $unidadesDescontar = $p['cantidad'] * $dbProd['factor_conversion'];
            $stockDisponible = floatval($dbProd['stock_actual']);

            // Blindaje anti-colisión: descontar reservas activas/HOLD de otros carritos.
            try {
                $stmtRes = $pdo->prepare("
                    SELECT COALESCE(SUM(ri.cantidad * prx.factor_conversion), 0)
                    FROM reservas_carrito_items ri
                    JOIN reservas_carrito r ON ri.reserva_id = r.id
                    JOIN presentaciones prx ON ri.presentacion_id = prx.id
                    WHERE prx.producto_id = ?
                      AND r.expires_at > NOW()
                      AND r.estado IN ('ACTIVE','HOLD')
                      AND ( ? = 0 OR r.id <> ? )
                ");
                $stmtRes->execute([(int)$dbProd['producto_id'], $reservationId, $reservationId]);
                $reservadoOtrasSesiones = floatval($stmtRes->fetchColumn() ?: 0);
                $stockDisponible = max(0, floatval($dbProd['stock_actual']) - $reservadoOtrasSesiones);
            } catch (Throwable $e) {
                // Si el esquema de reservas no está disponible, seguimos con stock actual.
                $stockDisponible = floatval($dbProd['stock_actual']);
            }

            if($stockDisponible < $unidadesDescontar) throw new Exception("Stock insuficiente (Unds) para: ".$dbProd['nombre']);
            
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
        
        // 3. Preparar pagos (contado / crédito parcial)
        $pagosInput = json_decode($_POST['pagos'] ?? '[]', true);
        if (!is_array($pagosInput)) $pagosInput = [];

        $stmtMet = $pdo->query("SELECT id, nombre FROM metodos_pago WHERE activo = 1");
        $metMap = [];
        foreach ($stmtMet->fetchAll() as $m) {
            $metMap[(int)$m['id']] = tipoMetodoPagoApi((string)$m['nombre']);
        }

        $pagosValidos = [];
        $suma_pagada_usd = 0.0;
        foreach ($pagosInput as $pg) {
            $metId = intval($pg['id'] ?? $pg['metodo_pago_id'] ?? 0);
            if ($metId <= 0 || !isset($metMap[$metId])) continue;
            $m_usd = round(floatval($pg['monto_usd'] ?? 0), 2);
            $m_bs = round(floatval($pg['monto_bs'] ?? 0), 2);
            if ($metMap[$metId] === 'USD') $m_bs = 0.0; else $m_usd = 0.0;
            $equiv_usd = round($m_usd + ($m_bs / $tasa), 2);
            if ($equiv_usd <= 0) continue;
            $pagosValidos[] = ['id' => $metId, 'monto_usd' => $m_usd, 'monto_bs' => $m_bs, 'equiv_usd' => $equiv_usd];
            $suma_pagada_usd += $equiv_usd;
        }
        $suma_pagada_usd = round($suma_pagada_usd, 2);

        if ($tipo === 'CONTADO' && empty($pagosValidos)) {
            throw new Exception("Debe estipular al menos un método de pago en ventas de contado.");
        }
        if ($tipo === 'CONTADO' && $suma_pagada_usd < ($totalUSD - 0.05)) {
            throw new Exception("El pago ingresado (\$" . $suma_pagada_usd . ") no cubre la factura (\$" . $totalUSD . ")");
        }
        if ($tipo === 'FIADO' && $suma_pagada_usd > ($totalUSD + 0.10)) {
            throw new Exception("El abono inicial supera el total del documento.");
        }

        $saldoPendiente = round(max(0, $totalUSD - ($tipo === 'FIADO' ? $suma_pagada_usd : $totalUSD)), 2);
        if ($tipo === 'CONTADO') $saldoPendiente = 0.0;
        if ($saldoPendiente <= 0.00001) $saldoPendiente = 0.0;
        $estado = $saldoPendiente > 0 ? ($suma_pagada_usd > 0 ? 'PARCIAL' : 'PENDIENTE') : 'PAGADO';

        // 4. Crear Documento (Proforma o Factura Fiscal)
        $tipoDocSolicitado = ($_POST['tipo_doc'] ?? 'PROFORMA') === 'FACTURA' ? 'FACTURA' : 'PROFORMA';
        if ($tipo === 'FIADO' && $tipoDocSolicitado === 'FACTURA') {
            throw new Exception("No se puede emitir FACTURA en ventas a crédito (FIADO).");
        }
        if ($tipoDocSolicitado === 'FACTURA' && $saldoPendiente > 0) {
            throw new Exception("La FACTURA solo puede emitirse con pago completo.");
        }
        $tipoDoc = $tipoDocSolicitado;
        
        // 3.1 Calcular desglose SENIAT en Bs
        $tasa = floatval(getConfig('tasa_usd_bs', $pdo));
        $exento_bs = 0; $base_imponible_bs = 0; $iva_bs = 0;
        
        foreach ($productos as $p) {
            $sub_bs = $p['subtotal_usd'] * $tasa;
            // Verificar exento desde la DB
            $stEx = $pdo->prepare("SELECT exento_iva FROM productos p JOIN presentaciones pr ON pr.producto_id = p.id WHERE pr.id = ?");
            $stEx->execute([$p['presentacion_id']]);
            if ($stEx->fetchColumn()) {
                $exento_bs += $sub_bs;
            } else {
                $b = $sub_bs / 1.16;
                $base_imponible_bs += $b;
                $iva_bs += ($sub_bs - $b);
            }
        }

        // Correlativo de Control simple para el ejemplo
        $ultimoControl = $pdo->query("SELECT MAX(CAST(numero_control AS UNSIGNED)) FROM proformas")->fetchColumn() ?: 1000;
        $numeroControl = $ultimoControl + 1;

        $stmt = $pdo->prepare("INSERT INTO proformas (cliente_id, cajero_id, tipo_documento, factura_numero, numero_control, tasa_dia_usd_bs, total_usd, saldo_pendiente_usd, estado, exento_bs, base_imponible_bs, iva_bs) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $fac_num = ($tipoDoc === 'FACTURA') ? 'F-' . str_pad($numeroControl, 6, '0', STR_PAD_LEFT) : null;
        $stmt->execute([$clienteId, $_SESSION['user_id'], $tipoDoc, $fac_num, $numeroControl, $tasa, $totalUSD, $saldoPendiente, $estado, $exento_bs, $base_imponible_bs, $iva_bs]);
        $proforma_id = $pdo->lastInsertId();
        
        // 5. Insertar Detalles (los triggers creados se encargarán de restar el inventario base multiplicando el factor!)
        $stmtDetalle = $pdo->prepare("INSERT INTO proforma_detalles (proforma_id, presentacion_id, cantidad, precio_unitario_usd, subtotal_usd) VALUES (?, ?, ?, ?, ?)");
        foreach ($productos as $p) {
            $stmtDetalle->execute([$proforma_id, $p['presentacion_id'], $p['cantidad'], $p['precio_unitario_usd'], $p['subtotal_usd']]);
        }
        
        // 6. Registrar pagos (contado o abono inicial de crédito)
        if (!empty($pagosValidos)) {
            // Buscamos sesion de caja
            $stmtSesion = $pdo->prepare("SELECT id FROM sesiones_caja WHERE usuario_id = ? AND estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
            $stmtSesion->execute([$_SESSION['user_id']]);
            $ses_id = $stmtSesion->fetchColumn() ?: null;
            if (!$ses_id) {
                throw new Exception("No hay caja ABIERTA. Abra su caja en 'Mi Caja' antes de cobrar.");
            }

            $notaAbono = $tipo === 'FIADO' ? 'Abono Inicial Crédito' : 'Pago Directo Contado';
            $stmtAbono = $pdo->prepare("INSERT INTO abonos (proforma_id, tasa_bs_usd, monto_total_usd, nota) VALUES (?, ?, ?, ?)");
            $stmtAbono->execute([$proforma_id, $tasa, $suma_pagada_usd, $notaAbono]);
            $abono_id = $pdo->lastInsertId();
            
            $stmtPDetalle = $pdo->prepare("INSERT INTO pagos_detalles (abono_id, metodo_pago_id, monto_entregado_bs, monto_entregado_usd, monto_equivalente_usd) VALUES (?, ?, ?, ?, ?)");
            $stmtCaja = $pdo->prepare("INSERT INTO movimientos_caja (sesion_caja_id, metodo_pago_id, tipo_movimiento, monto_bs, monto_usd, referencia_id, referencia_tabla) VALUES (?, ?, 'ENTRADA', ?, ?, ?, 'abonos')");
            foreach ($pagosValidos as $pg) {
                $stmtPDetalle->execute([$abono_id, $pg['id'], $pg['monto_bs'], $pg['monto_usd'], $pg['equiv_usd']]);
                $stmtCaja->execute([$ses_id, $pg['id'], $pg['monto_bs'], $pg['monto_usd'], $abono_id]);
            }
        }

        // Liberar reserva del carrito si se envió
        if ($reservationId > 0 && $deviceId !== '') {
            $stmt = $pdo->prepare("DELETE FROM reservas_carrito WHERE id = ? AND usuario_id = ? AND device_id = ?");
            $stmt->execute([$reservationId, $_SESSION['user_id'], $deviceId]);
        }
        
        $pdo->commit();
        registrarAccion($pdo, 'POS', ($tipoDoc === 'FACTURA' ? 'FACTURAR' : 'PROFORMA'), "Documento #$proforma_id creado ($tipo). Total: $totalUSD USD");
        $shareToken = generateShareLinkToken((int)$proforma_id);
        responseJson([
            'success' => true,
            'mensaje' => 'Proforma procesada con éxito.',
            'proforma_id' => $proforma_id,
            'share_token' => $shareToken,
            'pago_completo' => ($saldoPendiente <= 0)
        ]);
        
    } catch(Exception $e) {
        $pdo->rollBack();
        responseJson(['success' => false, 'message' => $e->getMessage()]);
    }
}

responseJson(['success' => false, 'message' => 'Acción no válida']);
