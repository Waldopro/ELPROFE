<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
restrictAdmin();
require_once '../includes/header.php';

// Usamos fecha del servidor SQL para alinear filtros con timestamp real de movimientos.
$fechaServidor = $pdo->query("SELECT DATE(NOW())")->fetchColumn() ?: date('Y-m-d');

// Filtros
$desde = $_GET['desde'] ?? $fechaServidor;
$hasta = $_GET['hasta'] ?? $fechaServidor;
$usuario_id = $_GET['usuario_id'] ?? '';
$metodo_id = $_GET['metodo_id'] ?? '';
$tipo = $_GET['tipo'] ?? '';

// Build Where clause
$where_clauses = ["DATE(m.fecha) BETWEEN :desde AND :hasta"];
$params = [':desde' => $desde, ':hasta' => $hasta];

if ($usuario_id !== '') {
    $where_clauses[] = "s.usuario_id = :usuario_id";
    $params[':usuario_id'] = $usuario_id;
}
if ($metodo_id !== '') {
    $where_clauses[] = "m.metodo_pago_id = :metodo_id";
    $params[':metodo_id'] = $metodo_id;
}
if ($tipo !== '') {
    $where_clauses[] = "m.tipo_movimiento = :tipo";
    $params[':tipo'] = $tipo;
}

$where_sql = implode(' AND ', $where_clauses);

// Totales Filtrados
$stmt_totales = $pdo->prepare("
    SELECT mp.moneda_base, 
           COALESCE(SUM(CASE WHEN m.tipo_movimiento = 'ENTRADA' THEN m.monto_usd ELSE -m.monto_usd END), 0) as balance_usd,
           COALESCE(SUM(CASE WHEN m.tipo_movimiento = 'ENTRADA' THEN m.monto_bs ELSE -m.monto_bs END), 0) as balance_bs
    FROM movimientos_caja m
    JOIN metodos_pago mp ON m.metodo_pago_id = mp.id
    LEFT JOIN sesiones_caja s ON m.sesion_caja_id = s.id
    WHERE $where_sql
    GROUP BY mp.moneda_base
");
$stmt_totales->execute($params);
$totales = $stmt_totales->fetchAll(PDO::FETCH_ASSOC);

$total_usd = 0;
$total_bs = 0;
foreach($totales as $t) {
    if($t['moneda_base'] === 'USD') $total_usd += $t['balance_usd'];
    else $total_bs += $t['balance_bs'];
}

// Movimientos Filtrados (limit to avoid memory issues, or paginate)
$stmt_mov = $pdo->prepare("
    SELECT m.*, mp.nombre as metodo_nombre, mp.moneda_base, u.nombre as cajero_nombre, u.username, s.id as id_sesion
    FROM movimientos_caja m 
    JOIN metodos_pago mp ON m.metodo_pago_id = mp.id
    LEFT JOIN sesiones_caja s ON m.sesion_caja_id = s.id
    LEFT JOIN usuarios u ON s.usuario_id = u.id
    WHERE $where_sql
    ORDER BY m.fecha DESC
    LIMIT 1000
");
$stmt_mov->execute($params);
$movimientos = $stmt_mov->fetchAll(PDO::FETCH_ASSOC);

$stmtAbonos = $pdo->prepare("
    SELECT COUNT(*)
    FROM movimientos_caja m
    WHERE $where_sql AND m.referencia_tabla = 'abonos'
");
$stmtAbonos->execute($params);
$abonos_registrados = (int)$stmtAbonos->fetchColumn();

// Catalogos para filtros
$usuarios = $pdo->query("SELECT id, nombre, username FROM usuarios ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$metodos = $pdo->query("SELECT id, nombre FROM metodos_pago ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-file-invoice-dollar me-2"></i> Auditoría de Caja (Movimientos)</h2>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-success shadow-sm" href="/ELPROFE/export_caja?desde=<?php echo urlencode($desde); ?>&hasta=<?php echo urlencode($hasta); ?>&usuario_id=<?php echo urlencode($usuario_id); ?>&metodo_id=<?php echo urlencode($metodo_id); ?>&tipo=<?php echo urlencode($tipo); ?>&formato=excel" target="_blank" rel="noopener">
            <i class="fa-solid fa-file-excel me-1"></i> Excel
        </a>
        <a class="btn btn-outline-danger shadow-sm" href="/ELPROFE/export_caja?desde=<?php echo urlencode($desde); ?>&hasta=<?php echo urlencode($hasta); ?>&usuario_id=<?php echo urlencode($usuario_id); ?>&metodo_id=<?php echo urlencode($metodo_id); ?>&tipo=<?php echo urlencode($tipo); ?>&formato=pdf" target="_blank" rel="noopener">
            <i class="fa-solid fa-file-pdf me-1"></i> PDF
        </a>
        <button class="btn btn-outline-danger shadow-sm" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Imprimir</button>
    </div>
</div>

<!-- Imprimible Header -->
<div class="d-none d-print-block text-center mb-4">
    <h3 class="fw-bold">Reporte de Auditoría de Caja</h3>
    <p>Desde: <?php echo $desde; ?> | Hasta: <?php echo $hasta; ?></p>
</div>

<!-- Filtros -->
<div class="card shadow-sm border-0 mb-3 d-print-none elprofe-soft-card">
    <div class="card-body">
        <form method="GET" action="/ELPROFE/caja" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?php echo htmlspecialchars($desde); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?php echo htmlspecialchars($hasta); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Cajero</label>
                <select name="usuario_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach($usuarios as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $usuario_id == $u['id'] ? 'selected':''; ?>><?php echo htmlspecialchars($u['nombre'] . ' (' . $u['username'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Método</label>
                <select name="metodo_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach($metodos as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $metodo_id == $m['id'] ? 'selected':''; ?>><?php echo htmlspecialchars($m['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="ENTRADA" <?php echo $tipo == 'ENTRADA' ? 'selected':''; ?>>ENTRADAS</option>
                    <option value="SALIDA" <?php echo $tipo == 'SALIDA' ? 'selected':''; ?>>SALIDAS</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-filter me-1"></i> Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2 d-print-none">
    <div class="small text-muted">
        <i class="fa-solid fa-circle-info me-1"></i>
        Auditoría conectada en tiempo real: los abonos y ventas aparecen sin cerrar caja.
    </div>
    <div class="small text-muted d-flex align-items-center gap-3">
        <span><i class="fa-solid fa-hand-holding-dollar me-1 text-success"></i> Abonos en rango: <strong><?php echo (int)$abonos_registrados; ?></strong></span>
        <span><i class="fa-solid fa-clock me-1"></i> Actualiza cada 20s</span>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100 bg-body-tertiary elprofe-soft-card">
            <div class="card-body text-center">
                <h6 class="text-muted text-uppercase fw-bold mb-3"><i class="fa-solid fa-sack-dollar me-2"></i> Balance Total USD Filtrado</h6>
                <h2 class="fw-bold mb-0 <?php echo $total_usd >= 0 ? 'text-success' : 'text-danger'; ?>">$<?php echo formatMoney($total_usd); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100 bg-body-tertiary elprofe-soft-card">
            <div class="card-body text-center">
                <h6 class="text-muted text-uppercase fw-bold mb-3"><i class="fa-solid fa-money-bill-wave me-2"></i> Balance Total VES Filtrado</h6>
                <h2 class="fw-bold mb-0 text-primary">Bs. <?php echo formatMoney($total_bs); ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 elprofe-soft-card">
    <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0 px-4">
        <h5 class="fw-bold"><i class="fa-solid fa-list me-2"></i> Detalle de Movimientos</h5>
    </div>
    <div class="card-body p-0 mt-3">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 datatable">
                <thead class="bg-dark text-white">
                    <tr>
                        <th class="ps-4">Fecha</th>
                        <th>Cajero (Sesión)</th>
                        <th>Método</th>
                        <th>Ref. Operación</th>
                        <th>Tipo</th>
                        <th class="text-end">Monto USD</th>
                        <th class="text-end pe-4">Monto VES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (count($movimientos) > 0) {
                        foreach($movimientos as $m) {
                            $badge = $m['tipo_movimiento'] === 'ENTRADA' ? 'bg-success' : 'bg-danger';
                            $sesion_info = $m['cajero_nombre'] ? ($m['cajero_nombre'] . " (#" . $m['id_sesion'] . ")") : "Sistema / Global";
                            
                            $usd_val = ($m['tipo_movimiento'] === 'ENTRADA' ? '+' : '-') . "$" . formatMoney($m['monto_usd']);
                            $bs_val = ($m['tipo_movimiento'] === 'ENTRADA' ? '+' : '-') . "Bs. " . formatMoney($m['monto_bs']);
                            
                            echo "<tr>
                                    <td class='ps-4 fw-bold' style='font-size:0.85rem;'>".e($m['fecha'])."</td>
                                    <td><span class='text-muted'><i class='fa-solid fa-user me-1'></i>".e($sesion_info)."</span></td>
                                    <td class='fw-bold'>".e($m['metodo_nombre'])."</td>
                                    <td><span class='text-capitalize badge bg-light text-dark border'>".e($m['referencia_tabla'])." #".e($m['referencia_id'])."</span></td>
                                    <td><span class='badge {$badge} px-2'>".e($m['tipo_movimiento'])."</span></td>
                                    <td class='text-end fw-bold ".($m['tipo_movimiento'] === 'ENTRADA' ? 'text-success' : 'text-danger')."'>{$usd_val}</td>
                                    <td class='text-end pe-4 fw-bold ".($m['tipo_movimiento'] === 'ENTRADA' ? 'text-primary' : 'text-danger')."'>{$bs_val}</td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center text-muted py-5'><i class='fa-solid fa-inbox fa-3x mb-3 text-light'></i><h5 class='mb-0'>No hay movimientos para estos filtros</h5></td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    body { background: white !important; font-size: 12px; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; border-radius: 0 !important; }
    .table th { background-color: #f8f9fa !important; color: #000 !important; }
    .table td, .table th { padding: 4px 8px !important; }
    .bg-light { background-color: #fff !important; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Refresco automático para auditoría en tiempo real.
    setInterval(function() {
        if (document.hidden) return;
        const url = new URL(window.location.href);
        url.searchParams.set('_rt', Date.now().toString());
        window.location.replace(url.toString());
    }, 20000);
});
</script>

<?php require_once '../includes/footer.php'; ?>
