<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
if (!isAdmin()) {
    header("Location: /ELPROFE/dashboard");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token']);
    
    if (isset($_POST['action']) && $_POST['action'] === 'guardar_tasa_manual') {
        $tasa = floatval($_POST['tasa_manual']);
        if ($tasa > 0) {
            $stmt = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'tasa_usd_bs'");
            $stmt->execute([$tasa]);
            
            $stmtInsert = $pdo->prepare("INSERT IGNORE INTO configuracion (clave, valor) VALUES ('tasa_tipo', 'MANUAL')");
            $stmtInsert->execute();
            
            $stmtUpdate = $pdo->prepare("UPDATE configuracion SET valor = 'MANUAL' WHERE clave = 'tasa_tipo'");
            $stmtUpdate->execute();
            
            setFlash('success', 'Tasa manual fijada correctamente a ' . $tasa);
        } else {
            setFlash('error', 'La tasa debe ser mayor a 0');
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'usar_tasa_bcv') {
        $stmtInsert = $pdo->prepare("INSERT IGNORE INTO configuracion (clave, valor) VALUES ('tasa_tipo', 'BCV')");
        $stmtInsert->execute();
        
        $stmtUpdate = $pdo->prepare("UPDATE configuracion SET valor = 'BCV' WHERE clave = 'tasa_tipo'");
        $stmtUpdate->execute();
        
        $stmtLast = $pdo->query("SELECT usd FROM tasas_bcv ORDER BY fecha DESC LIMIT 1");
        $last_tasa = $stmtLast->fetchColumn();
        if ($last_tasa) {
           $stmtConfig = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'tasa_usd_bs'");
           $stmtConfig->execute([$last_tasa]);
        }
        setFlash('success', 'Modalidad de tasa cambiada a BCV oficialmente.');
    } elseif (isset($_POST['action']) && $_POST['action'] === 'guardar_empresa') {
        $claves = ['empresa_nombre', 'empresa_direccion', 'empresa_telefono', 'empresa_rif', 'empresa_iva', 'empresa_margen'];
        foreach ($claves as $c) {
            if (isset($_POST[$c])) {
                $val = trim($_POST[$c]);
                // Ensure key exists
                $stmtInst = $pdo->prepare("INSERT IGNORE INTO configuracion (clave, valor) VALUES (?, '')");
                $stmtInst->execute([$c]);
                // Update value
                $stmtUp = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = ?");
                $stmtUp->execute([$val, $c]);
            }
        }
        setFlash('success', 'Datos de la empresa actualizados correctamente.');
    }
    
    header("Location: /ELPROFE/configuracion");
    exit;
}

$tasa_actual = getConfig('tasa_usd_bs', $pdo);
$tasa_tipo = getConfig('tasa_tipo', $pdo) ?: 'MANUAL';

$emp_nombre = getConfig('empresa_nombre', $pdo) ?: 'ELPROFE POS';
$emp_dir = getConfig('empresa_direccion', $pdo) ?: '';
$emp_tel = getConfig('empresa_telefono', $pdo) ?: '';
$emp_rif = getConfig('empresa_rif', $pdo) ?: 'J-00000000-0';
$emp_iva = getConfig('empresa_iva', $pdo) ?: '16.00';
$emp_margen = getConfig('empresa_margen', $pdo) ?: '30.00';

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-gears me-2"></i> Configuración del POS</h2>
</div>

<div class="row">
    <!-- Panel de Tasa de Cambio -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-dark text-white">
                <h5 class="card-title fw-bold mb-0"><i class="fa-solid fa-money-bill-trend-up"></i> Tasa de Cambio Base</h5>
            </div>
            <div class="card-body">
                <div class="alert <?php echo $tasa_tipo === 'BCV' ? 'alert-info' : 'alert-warning'; ?> d-flex align-items-center">
                    <i class="fa-solid <?php echo $tasa_tipo === 'BCV' ? 'fa-globe' : 'fa-hand'; ?> fa-2x me-3"></i>
                    <div>
                        <h4 class="mb-0 fw-bold"><?php echo number_format($tasa_actual, 4); ?> Bs/$</h4>
                        <small>Gestión actual: <strong><?php echo $tasa_tipo; ?></strong></small>
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-md-6 border-end">
                        <h6 class="fw-bold text-muted">Sincronización BCV</h6>
                        <p class="small text-muted mb-2">Extrae la tasa de manera dinámica (Scrapping) desde bcv.org.ve directamente.</p>
                        <button class="btn btn-outline-primary w-100 fw-bold mb-2" id="btn-sync-bcv">
                            <i class="fa-solid fa-bolt"></i> Sincronizar BCV Ahora
                        </button>
                        
                        <?php if ($tasa_tipo !== 'BCV'): ?>
                        <form method="POST" action="/ELPROFE/configuracion">
                            <input type="hidden" name="action" value="usar_tasa_bcv">
                            <?php echo csrfField(); ?>
                            <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-check"></i> Activar Modo BCV</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-muted">Fijar Tasa Manual</h6>
                        <p class="small text-muted mb-2">Anclar la tasa de forma paralela a un valor personalizado.</p>
                        <form method="POST" action="/ELPROFE/configuracion">
                            <input type="hidden" name="action" value="guardar_tasa_manual">
                            <?php echo csrfField(); ?>
                            <div class="input-group mb-2">
                                <span class="input-group-text">Bs</span>
                                <input type="number" step="0.0001" min="0.1" name="tasa_manual" class="form-control" placeholder="Ej. 36.50" value="<?php echo number_format($tasa_actual, 4, '.', ''); ?>" required>
                                <button class="btn btn-warning" type="submit"><i class="fa-solid fa-save"></i> Fija</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Panel de Empresa -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-dark text-white">
                <h5 class="card-title fw-bold mb-0"><i class="fa-solid fa-store"></i> Datos de la Empresa</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/ELPROFE/configuracion">
                    <input type="hidden" name="action" value="guardar_empresa">
                    <?php echo csrfField(); ?>
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <label class="form-label text-muted fw-bold">RIF</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-id-card"></i></span>
                                <input type="text" name="empresa_rif" class="form-control" value="<?php echo htmlspecialchars($emp_rif); ?>" required>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label text-muted fw-bold">Razón Social</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-shop"></i></span>
                                <input type="text" name="empresa_nombre" class="form-control" value="<?php echo htmlspecialchars($emp_nombre); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <label class="form-label text-muted fw-bold">IVA (%)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-receipt"></i></span>
                                <input type="number" step="0.01" name="empresa_iva" class="form-control" value="<?php echo htmlspecialchars($emp_iva); ?>" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label text-muted fw-bold">Ganancia Base (%)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-chart-line"></i></span>
                                <input type="number" step="0.01" name="empresa_margen" class="form-control" value="<?php echo htmlspecialchars($emp_margen); ?>">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold">Teléfono / WhatsApp</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                            <input type="text" name="empresa_telefono" class="form-control" value="<?php echo htmlspecialchars($emp_tel); ?>">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label text-muted fw-bold">Dirección Fiscal / Ticket</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-map-location-dot"></i></span>
                            <textarea name="empresa_direccion" class="form-control" rows="2"><?php echo htmlspecialchars($emp_dir); ?></textarea>
                        </div>
                    </div>
                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary fw-bold"><i class="fa-solid fa-save"></i> Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#btn-sync-bcv').click(function() {
        let btn = $(this);
        let htmlOriginal = btn.html();
        btn.html('<i class="fa-solid fa-spinner fa-spin"></i> Conectando al Servidor BCV...').prop('disabled', true);
        
        $.post('/ELPROFE/api/bcv.php', {
            target: 'sync'
        }, function(res) {
            btn.html(htmlOriginal).prop('disabled', false);
            if(res.success) {
                Swal.fire({
                    title: '¡Sincronizado!',
                    text: 'Tasa jalada de BCV actual: ' + res.tasa + ' Bs/$',
                    icon: 'success'
                }).then(() => location.reload());
            } else {
                Swal.fire('Error Scrapping', res.message, 'error');
            }
        }).fail(function() {
            btn.html(htmlOriginal).prop('disabled', false);
            Swal.fire('Error', 'No hubo respuesta del backend API.', 'error');
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
