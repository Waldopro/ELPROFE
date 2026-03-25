<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
if (!isAdmin()) {
    header("Location: /ELPROFE/dashboard");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_tasa_manual') {
    verifyCsrfToken($_POST['csrf_token']);
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
    header("Location: /ELPROFE/configuracion");
    exit;
}

$tasa_actual = getConfig('tasa_usd_bs', $pdo);
$tasa_tipo = getConfig('tasa_tipo', $pdo) ?: 'MANUAL';

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
                        <p class="small text-muted mb-3">Extrae la tasa de manera dinámica (Scrapping) desde bcv.org.ve directamente.</p>
                        <button class="btn btn-outline-primary w-100 fw-bold" id="btn-sync-bcv">
                            <i class="fa-solid fa-bolt"></i> Sincronizar BCV Ahora
                        </button>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-muted">Fijar Tasa Manual</h6>
                        <p class="small text-muted mb-2">Anclar la tasa de forma paralela a un valor personalizado.</p>
                        <form method="POST" action="/ELPROFE/configuracion">
                            <input type="hidden" name="action" value="guardar_tasa_manual">
                            <?php echo csrfField(); ?>
                            <div class="input-group mb-2">
                                <span class="input-group-text">Bs</span>
                                <input type="number" step="0.0001" min="0.1" name="tasa_manual" class="form-control" placeholder="Ej. 36.50" required>
                                <button class="btn btn-warning" type="submit"><i class="fa-solid fa-save"></i> Fija</button>
                            </div>
                        </form>
                    </div>
                </div>
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
