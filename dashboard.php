<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
checkLogin();

// Obtain some metrics
$today = date('Y-m-d');
$caja_id = getCajaAbiertaId($pdo);
$caja_info = null;
if ($caja_id) {
    $s = $pdo->prepare("SELECT * FROM sesiones_caja WHERE id = ?");
    $s->execute([$caja_id]);
    $caja_info = $s->fetch(PDO::FETCH_ASSOC);

    $s_bal = $pdo->prepare("
        SELECT COALESCE(SUM(CASE WHEN mk.tipo_movimiento = 'ENTRADA' THEN mk.monto_usd ELSE -mk.monto_usd END), 0) as balance_usd,
               COALESCE(SUM(CASE WHEN mk.tipo_movimiento = 'ENTRADA' THEN mk.monto_bs ELSE -mk.monto_bs END), 0) as balance_bs
        FROM movimientos_caja mk WHERE sesion_caja_id = ?
    ");
    $s_bal->execute([$caja_id]);
    $bal_s = $s_bal->fetch(PDO::FETCH_ASSOC);
    $caja_info['bal_usd'] = $bal_s['balance_usd'];
    $caja_info['bal_bs'] = $bal_s['balance_bs'];
}

$tasa = getConfig('tasa_usd_bs', $pdo);

// Total Sales USD Today
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_usd), 0) FROM proformas WHERE DATE(fecha_emision) = ? AND estado IN ('PAGADO', 'PARCIAL')");
$stmt->execute([$today]);
$ventasHoyUSD = $stmt->fetchColumn();

// Count low stock items
$stmt = $pdo->query("SELECT COUNT(*) FROM productos WHERE stock_actual < 5");
$productosBajos = $stmt->fetchColumn();

require_once 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 elprofe-hero">
            <div>
                <h2 class="fw-bold text-primary elprofe-panel-title mb-1"><i class="fa-solid fa-chart-pie me-2"></i> Resumen del Día</h2>
                <p class="text-muted mb-0">Bienvenido, <?php echo htmlspecialchars($_SESSION['user_name']); ?>. Aquí tienes un vistazo general.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge rounded-pill text-bg-primary-subtle border border-primary-subtle px-3 py-2">
                    <i class="fa-regular fa-calendar me-1"></i> <?php echo e(date('d/m/Y')); ?>
                </span>
                <span class="badge rounded-pill text-bg-dark border border-secondary px-3 py-2">
                    <i class="fa-solid fa-bolt me-1"></i> Tiempo real activo
                </span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <?php if (isAdmin()): ?>

    <!-- Estado de Caja del Usuario -->
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm bg-body elprofe-soft-card">
            <div class="card-body d-flex flex-column justify-content-between p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="card-title fw-bold text-muted text-uppercase" style="font-size: 0.85rem;">Estado de Caja</h5>
                    <div class="<?php echo $caja_id ? 'text-primary' : 'text-danger'; ?> rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: <?php echo $caja_id ? 'rgba(0, 33, 87, 0.08)' : 'rgba(211, 16, 30, 0.08)'; ?>;">
                        <i class="fa-solid <?php echo $caja_id ? 'fa-cash-register' : 'fa-lock'; ?> fa-lg"></i>
                    </div>
                </div>
                <?php if ($caja_id): ?>
                    <h3 class="fw-bold mb-0 <?php echo $caja_id ? 'text-primary' : 'text-danger'; ?>">Abierta</h3>
                    <p class="small text-muted mt-1 mb-0">Bal: $<?php echo formatMoney($caja_info['bal_usd']); ?> | Bs. <?php echo formatMoney($caja_info['bal_bs']); ?></p>
                <?php else: ?>
                    <h3 class="fw-bold mb-0 text-danger">Cerrada</h3>
                    <p class="small text-muted mt-1 mb-0"><a href="/ELPROFE/mi_caja" class="text-primary fw-semibold">Abrir ahora para operar</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm bg-body text-body elprofe-soft-card">
            <div class="card-body d-flex flex-column justify-content-between p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="card-title fw-bold text-muted text-uppercase" style="font-size: 0.85rem;">Ventas Hoy (USD)</h5>
                    <div class="text-primary rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: rgba(0, 33, 87, 0.08);">
                        <i class="fa-solid fa-sack-dollar fa-lg"></i>
                    </div>
                </div>
                <h1 class="display-5 fw-bold mb-0 text-primary">$<?php echo formatMoney($ventasHoyUSD); ?></h1>
                <p class="small text-muted mt-2 mb-0">Aproximado en Bs: <?php echo formatMoney($ventasHoyUSD * $tasa); ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 d-none d-lg-block">
        <div class="card h-100 border-0 shadow-sm bg-body text-body elprofe-soft-card">
            <div class="card-body d-flex flex-column justify-content-between p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="card-title fw-bold text-muted text-uppercase" style="font-size: 0.85rem;">Alertas de Inventario</h5>
                    <div class="text-warning rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: rgba(255, 193, 7, 0.15);">
                        <i class="fa-solid fa-triangle-exclamation fa-lg"></i>
                    </div>
                </div>
                <h1 class="display-5 fw-bold text-dark mb-0"><?php echo $productosBajos; ?></h1>
                <p class="small text-muted mt-2 mb-0">Productos con stock bajo (< 5)</p>
            </div>
        </div>
    </div>
    <?php else: ?>

    <!-- Estado de Caja del Usuario -->
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm bg-body elprofe-soft-card">
            <div class="card-body d-flex flex-column justify-content-between p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="card-title fw-bold text-muted text-uppercase" style="font-size: 0.85rem;">Estado de Caja</h5>
                    <div class="<?php echo $caja_id ? 'text-primary' : 'text-danger'; ?> rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: <?php echo $caja_id ? 'rgba(0, 33, 87, 0.08)' : 'rgba(211, 16, 30, 0.08)'; ?>;">
                        <i class="fa-solid <?php echo $caja_id ? 'fa-cash-register' : 'fa-lock'; ?> fa-lg"></i>
                    </div>
                </div>
                <?php if ($caja_id): ?>
                    <h3 class="fw-bold mb-0 <?php echo $caja_id ? 'text-primary' : 'text-danger'; ?>">Abierta</h3>
                    <p class="small text-muted mt-1 mb-0">Bal: $<?php echo formatMoney($caja_info['bal_usd']); ?> | Bs. <?php echo formatMoney($caja_info['bal_bs']); ?></p>
                <?php else: ?>
                    <h3 class="fw-bold mb-0 text-danger">Cerrada</h3>
                    <p class="small text-muted mt-1 mb-0"><a href="/ELPROFE/mi_caja" class="text-primary fw-semibold">Abrir ahora para operar</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm bg-body text-body elprofe-soft-card">
            <div class="card-body p-4 d-flex flex-column justify-content-center">
                <h3 class="fw-bold mb-2 text-primary">Turno Operativo</h3>
                <p class="mb-0 text-muted">Mantén el registro claro de tus cobros. Usa <kbd class="bg-secondary px-2">F2</kbd> en cualquier momento para atender.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm bg-body text-body elprofe-soft-card">
            <div class="card-body d-flex flex-column justify-content-between p-4">
               <div class="d-flex align-items-center justify-content-between mb-3">
                   <h5 class="card-title fw-bold text-muted text-uppercase" style="font-size: 0.85rem;">Accesos Rápidos</h5>
                   <div class="text-primary rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: rgba(0, 33, 87, 0.08);">
                       <i class="fa-solid fa-bolt fa-lg"></i>
                   </div>
               </div>
               <div class="d-grid gap-2 mt-auto">
                   <a href="/ELPROFE/ventas" class="btn btn-primary fw-bold shadow-sm"><i class="fa-solid fa-plus me-1"></i> Nueva Proforma (F2)</a>
                   <?php if (isAdmin()): ?>
                   <a href="/ELPROFE/compras" class="btn btn-outline-secondary text-body"><i class="fa-solid fa-truck"></i> Registrar Compra</a>
                   <?php endif; ?>
               </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 elprofe-soft-card">
            <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0 px-4">
                <h5 class="fw-bold"><i class="fa-solid fa-clock-rotate-left me-2"></i> Últimos Movimientos de Hoy</h5>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Ref/Tipo</th>
                                <th>Fecha</th>
                                <th>Moneda</th>
                                <th>Monto USD</th>
                                <th>Monto Bs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->prepare("SELECT tipo_movimiento, fecha, monto_usd, monto_bs, referencia_tabla FROM movimientos_caja WHERE DATE(fecha) = ? ORDER BY fecha DESC LIMIT 5");
                            $stmt->execute([$today]);
                            $movs = $stmt->fetchAll();
                            
                            if (count($movs) > 0) {
                                foreach($movs as $m) {
                                    $badgeClass = $m['tipo_movimiento'] === 'ENTRADA' ? 'bg-success' : 'bg-danger';
                                    $tipoMov = e((string)$m['tipo_movimiento']);
                                    $refTabla = e(ucfirst((string)$m['referencia_tabla']));
                                    $fechaMov = e((string)$m['fecha']);
                                    echo "<tr>
                                            <td><span class='badge {$badgeClass}'>{$tipoMov}</span> <span class='text-muted small ms-2'>{$refTabla}</span></td>
                                            <td>{$fechaMov}</td>
                                            <td>Multimoneda</td>
                                            <td><strong class='text-success'>$".formatMoney($m['monto_usd'])."</strong></td>
                                            <td>Bs. ".formatMoney($m['monto_bs'])."</td>
                                          </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5' class='text-center text-muted py-4'><i class='fa-solid fa-inbox fa-2x mb-2 d-block'></i> Aún no hay movimientos hoy</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        <?php if (isAdmin() && getConfig('tasa_tipo', $pdo) === 'BCV'): ?>
        // Sincronización silenciosa del BCV diara al cargar el dashboard
        console.log("Comprobando Tasa BCV automáticamente...");
        $.post('/ELPROFE/api/bcv.php', { csrf_token: $('meta[name="csrf-token"]').attr('content') }, function(res){
            if(res.success && res.tasa) {
                console.log("Tasa BCV verificada/actualizada: " + res.tasa);
            }
        });
        <?php endif; ?>
    });
</script>

<?php require_once 'includes/footer.php'; ?>
