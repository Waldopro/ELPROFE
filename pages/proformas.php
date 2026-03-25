<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();

// Registrar Abono / Pago Parcial o Total para Fiados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar_abono') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $proforma_id = intval($_POST['proforma_id'] ?? 0);
    $metodo_id = intval($_POST['metodo_pago_id'] ?? 0);
    $monto_usd_entregado = floatval($_POST['monto_usd'] ?? 0);
    $monto_bs_entregado = floatval($_POST['monto_bs'] ?? 0);
    
    // TASA ACTUAL DE HOY! (El requerimiento clave para ventas a crédito multimoneda)
    $tasa_hoy = floatval(getConfig('tasa_usd_bs', $pdo));
    
    if ($proforma_id === 0 || $metodo_id === 0 || ($monto_usd_entregado <= 0 && $monto_bs_entregado <= 0)) {
        setFlash('error', 'Debe especificar monto e instrumento.');
        header("Location: /ELPROFE/proformas");
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $equiv_usd_pagado = round($monto_usd_entregado + ($monto_bs_entregado / $tasa_hoy), 2);
        
        // Extraer deuda
        $stmt = $pdo->prepare("SELECT saldo_pendiente_usd FROM proformas WHERE id = ? FOR UPDATE");
        $stmt->execute([$proforma_id]);
        $prof = $stmt->fetch();
        
        if(!$prof) throw new Exception("Nota de entrega no existe.");
        if($prof['saldo_pendiente_usd'] <= 0) throw new Exception("Esta factura ya fue 100% liqudada.");
        
        if ($equiv_usd_pagado > ($prof['saldo_pendiente_usd'] + 0.10)) { // 10 centavos de tolerancia en abonos altos c/bolos
             throw new Exception("El monto introducido (\$$equiv_usd_pagado) supera la deuda actual (\${$prof['saldo_pendiente_usd']}). Refleje el cambio físicamente.");
        }
        
        $nuevo_saldo = round($prof['saldo_pendiente_usd'] - $equiv_usd_pagado, 2);
        if ($nuevo_saldo < 0) $nuevo_saldo = 0;
        
        $nuevo_estado = ($nuevo_saldo === 0.00) ? 'PAGADO' : 'PARCIAL';
        
        // Inyectar en abonos
        $stmtAbono = $pdo->prepare("INSERT INTO abonos (proforma_id, tasa_bs_usd, monto_total_usd, nota) VALUES (?, ?, ?, ?)");
        $stmtAbono->execute([$proforma_id, $tasa_hoy, $equiv_usd_pagado, 'Abono Parcial / Cobranza']);
        $abono_id = $pdo->lastInsertId();
        
        // Pagos detalles
        $stmtPDetalle = $pdo->prepare("INSERT INTO pagos_detalles (abono_id, metodo_pago_id, monto_entregado_bs, monto_entregado_usd, monto_equivalente_usd) VALUES (?, ?, ?, ?, ?)");
        $stmtPDetalle->execute([$abono_id, $metodo_id, $monto_bs_entregado, $monto_usd_entregado, $equiv_usd_pagado]);
        
        // Sesion/Caja
        $stmtSesion = $pdo->prepare("SELECT id FROM sesiones_caja WHERE usuario_id = ? AND estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $stmtSesion->execute([$_SESSION['user_id']]);
        $ses_id = $stmtSesion->fetchColumn() ?: null;
        
        $stmtCaja = $pdo->prepare("INSERT INTO movimientos_caja (sesion_caja_id, metodo_pago_id, tipo_movimiento, monto_bs, monto_usd, referencia_id, referencia_tabla) VALUES (?, ?, 'ENTRADA', ?, ?, ?, 'abonos')");
        $stmtCaja->execute([$ses_id, $metodo_id, $monto_bs_entregado, $monto_usd_entregado, $abono_id]);
        
        // Actualizar Proforma
        $stmtUp = $pdo->prepare("UPDATE proformas SET saldo_pendiente_usd = ?, estado = ? WHERE id = ?");
        $stmtUp->execute([$nuevo_saldo, $nuevo_estado, $proforma_id]);
        
        $pdo->commit();
        setFlash('success', 'Abono Registrado por $'. $equiv_usd_pagado .' exitosamente.');
        
    } catch(Exception $e) {
        $pdo->rollBack();
        setFlash('error', $e->getMessage());
    }
    header("Location: /ELPROFE/proformas");
    exit;
}

$stmtMetodos = $pdo->query("SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY id ASC");
$metodosPago = $stmtMetodos->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-file-invoice me-2"></i> Cobranza y Proformas</h2>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0 mt-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">ID / Referencia</th>
                        <th>Cliente</th>
                        <th>Fecha de Emisión</th>
                        <th class="text-center">Total Dólares</th>
                        <th class="text-center">Deuda (Saldo USD)</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center pe-4"><i class="fa-solid fa-tools"></i> Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Mostrar fiados y parciales arriba, pagados abajo
                    $stmt = $pdo->query("SELECT p.*, c.nombre, c.apellido, c.cedula_rif 
                                         FROM proformas p 
                                         JOIN clientes c ON p.cliente_id = c.id
                                         ORDER BY CASE p.estado WHEN 'PENDIENTE' THEN 1 WHEN 'PARCIAL' THEN 2 ELSE 3 END, p.fecha_emision DESC 
                                         LIMIT 100");
                    $proformas = $stmt->fetchAll();
                    
                    if (count($proformas) > 0) {
                        foreach($proformas as $p) {
                            $badge = match($p['estado']) {
                                'PAGADO' => 'bg-success',
                                'PARCIAL' => 'bg-primary',
                                'PENDIENTE' => 'bg-warning text-dark',
                                'ANULADO' => 'bg-danger',
                                default => 'bg-secondary'
                            };
                            
                            $saldo = floatval($p['saldo_pendiente_usd']);
                            echo "<tr>
                                    <td class='ps-4 fw-bold'># ".str_pad($p['id'], 6, '0', STR_PAD_LEFT)."</td>
                                    <td><span class='fw-bold'>".e($p['nombre'] . ' ' . $p['apellido'])."</span><br><small class='text-muted'>".e($p['cedula_rif'])."</small></td>
                                    <td class='text-muted'>".e($p['fecha_emision'])."</td>
                                    <td class='text-center'>$".formatMoney($p['total_usd'])."</td>
                                    <td class='text-center text-danger fw-bolder fs-5'>$".formatMoney($saldo)."</td>
                                    <td class='text-center'><span class='badge {$badge} px-3 py-2'>".e($p['estado'])."</span></td>
                                    <td class='text-center pe-4'>";
                            if ($saldo > 0 && $p['estado'] !== 'ANULADO') {
                                echo "<button class='btn btn-sm btn-success fw-bold shadow-sm rounded-pill btn-abonar' 
                                            data-id='".e($p['id'])."' data-saldo='{$saldo}'>
                                        <i class='fa-solid fa-hand-holding-dollar'></i> Recibir Abono
                                      </button>";
                            } else {
                                echo "<span class='text-muted small'><i class='fa-solid fa-check-double'></i> Liquidada</span>";
                            }
                            echo "</td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center text-muted py-5'><i class='fa-solid fa-file-excel fa-3x mb-3 text-light'></i><h5 class='mb-0'>No hay notas de entrega registradas</h5></td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Abono Simple -->
<div class="modal fade" id="modalAbono" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-hand-holding-dollar"></i> Recibir Pago / Abono Parcial</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="/ELPROFE/proformas" id="form-abono">
          <div class="modal-body p-4">
            <input type="hidden" name="action" value="registrar_abono">
            <input type="hidden" name="proforma_id" id="modal-abono-id">
            <?php echo csrfField(); ?>
            <div class="text-center mb-4 border-bottom pb-3">
                <h6 class="text-muted">Deuda Pendiente (Fiado):</h6>
                <h1 class="text-danger fw-bolder" id="modal-abono-saldo">$0.00</h1>
                <small class="text-primary fw-bold"><i class="fa-solid fa-bolt"></i> Calculando deuda en Bs. usando tasa actual de hoy: <?php echo getConfig('tasa_usd_bs', $pdo); ?> Bs/$</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label text-muted small">Seleccione Instrumento</label>
                <select name="metodo_pago_id" class="form-select border-2 border-primary" required>
                    <?php foreach($metodosPago as $mp): ?>
                    <option value="<?php echo $mp['id']; ?>"><?php echo e($mp['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label text-muted small">Recibió (USD Billetes)</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" name="monto_usd" class="form-control" placeholder="0.00">
                    </div>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label text-muted small">Recibió (Bolívares Físico/Transfer)</label>
                    <div class="input-group">
                        <span class="input-group-text">Bs</span>
                        <input type="number" step="0.01" name="monto_bs" class="form-control" placeholder="0.00">
                    </div>
                </div>
            </div>
          </div>
          <div class="modal-footer pb-3">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm" id="btn-procesar-abono"><i class="fa-solid fa-paper-plane"></i> Abonar y Liquidar</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    $('.btn-abonar').click(function() {
        let id = $(this).data('id');
        let saldo = parseFloat($(this).data('saldo'));
        
        $('#modal-abono-id').val(id);
        $('#modal-abono-saldo').text('$' + saldo.toFixed(2));
        $('#form-abono')[0].reset();
        
        new bootstrap.Modal(document.getElementById('modalAbono')).show();
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
