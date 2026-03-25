<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
restrictAdmin();
require_once '../includes/header.php';

// Obtener saldos de métodos de pago
$stmt = $pdo->query("
    SELECT mp.nombre, mp.moneda_base, 
           COALESCE(SUM(CASE WHEN mk.tipo_movimiento = 'ENTRADA' THEN mk.monto_usd ELSE -mk.monto_usd END), 0) as balance_usd,
           COALESCE(SUM(CASE WHEN mk.tipo_movimiento = 'ENTRADA' THEN mk.monto_bs ELSE -mk.monto_bs END), 0) as balance_bs
    FROM metodos_pago mp
    LEFT JOIN movimientos_caja mk ON mp.id = mk.metodo_pago_id
    GROUP BY mp.id
");
$cuentas = $stmt->fetchAll();

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-cash-register me-2"></i> Contabilidad y Caja</h2>
    <div>
        <button class="btn btn-outline-primary shadow-sm"><i class="fa-solid fa-print me-1"></i> Imprimir Reporte</button>
    </div>
</div>

<div class="row g-4 mb-4">
    <?php foreach($cuentas as $c): ?>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h6 class="text-muted text-uppercase fw-bold mb-3"><i class="fa-solid fa-wallet me-2"></i> <?php echo e($c['nombre']); ?></h6>
                <?php if($c['moneda_base'] === 'USD'): ?>
                    <h3 class="fw-bold mb-0 text-success">$<?php echo formatMoney($c['balance_usd']); ?></h3>
                <?php else: ?>
                    <h3 class="fw-bold mb-0 text-primary">Bs. <?php echo formatMoney($c['balance_bs']); ?></h3>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0 px-4">
        <h5 class="fw-bold"><i class="fa-solid fa-clock-rotate-left me-2"></i> Historial Reciente de Movimientos</h5>
    </div>
    <div class="card-body p-0 mt-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Cajero</th>
                        <th>Afectación</th>
                        <th>Método</th>
                        <th>Tipo Operación</th>
                        <th class="text-end">Monto Equivalente</th>
                        <th class="text-end pe-4">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT m.*, mp.nombre as metodo_nombre, u.nombre as cajero_nombre, s.id as id_sesion
                                         FROM movimientos_caja m 
                                         JOIN metodos_pago mp ON m.metodo_pago_id = mp.id
                                         LEFT JOIN sesiones_caja s ON m.sesion_caja_id = s.id
                                         LEFT JOIN usuarios u ON s.usuario_id = u.id
                                         ORDER BY m.fecha DESC LIMIT 50");
                    $movs = $stmt->fetchAll();
                    
                    if (count($movs) > 0) {
                        foreach($movs as $m) {
                            $badge = $m['tipo_movimiento'] === 'ENTRADA' ? 'bg-success' : 'bg-danger';
                            // Priorizar siempre USD en reportes internos como dice requerimiento "Contabilidad de caja"
                            echo "<tr>
                                    <td class='ps-4'><span class='text-muted'><i class='fa-solid fa-user me-1'></i>".e($m['cajero_nombre'] ?: 'Sistema')."</span></td>
                                    <td><span class='badge {$badge} px-2'>".e($m['tipo_movimiento'])."</span></td>
                                    <td class='fw-bold'>".e($m['metodo_nombre'])."</td>
                                    <td><span class='text-capitalize'>".e($m['referencia_tabla'])." #".e($m['referencia_id'])."</span></td>
                                    <td class='text-end fw-bold'>$".formatMoney($m['monto_usd'])."</td>
                                    <td class='text-end pe-4 text-muted small'>".e($m['fecha'])."</td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center text-muted py-5'><i class='fa-solid fa-inbox fa-3x mb-3 text-light'></i><h5 class='mb-0'>No hay movimientos</h5></td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
