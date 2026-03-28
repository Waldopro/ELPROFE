<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
restrictAdmin();

require_once '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'limpiar_bitacora') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    try {
        $pdo->exec("DELETE FROM bitacora_acciones WHERE id NOT IN (SELECT id FROM (SELECT id FROM bitacora_acciones ORDER BY fecha DESC LIMIT 30) as t)");
        $pdo->exec("DELETE FROM bitacora_accesos WHERE id NOT IN (SELECT id FROM (SELECT id FROM bitacora_accesos ORDER BY fecha DESC LIMIT 30) as t)");
        registrarAccion($pdo, 'SISTEMA', 'MANTENIMIENTO', 'Limpieza de bitácora (Mantuvo últimos 30)');
        setFlash('success', 'Bitácora depurada. Solo se conservan los últimos 30 registros.');
    } catch (Exception $e) {
        setFlash('error', 'Error al depurar: ' . $e->getMessage());
    }
    header("Location: /ELPROFE/bitacora");
    exit;
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-shield-halved me-2"></i> Auditoría y Bitácoras</h2>
    <div class="d-flex gap-2 w-100 w-md-auto">
        <form method="POST" onsubmit="return confirm('¿Seguro que desea depurar? Se borrará todo excepto los últimos 30 registros.');">
            <input type="hidden" name="action" value="limpiar_bitacora">
            <?php echo csrfField(); ?>
            <button type="submit" class="btn btn-outline-danger shadow-sm fw-bold">
                <i class="fa-solid fa-broom me-1"></i> <span class="d-none d-sm-inline">Depurar Historial</span>
            </button>
        </form>
    </div>
</div>

<ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active fw-bold" data-bs-toggle="pill" data-bs-target="#pills-accesos" type="button">Log de Accesos (Login)</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#pills-acciones" type="button">Traza de Acciones (Actividad)</button>
  </li>
</ul>

<div class="alert alert-secondary border-0 shadow-sm mb-4">
    <h6 class="fw-bold mb-1"><i class="fa-solid fa-circle-info text-primary"></i> Leyenda de Monitoreo Organizacional:</h6>
    <ul class="small mb-0 text-muted">
        <li><strong>Log de Accesos:</strong> Vigila los intentos de entrar a tu sistema. Si ves un <i class="fa-solid fa-triangle-exclamation text-danger"></i> significa que alguien probó contraseñas incorrectas para intentar vulnerar tu caja.</li>
        <li><strong>Traza de Actividad:</strong> Registra movimientos exactos de dinero o inventario (Emitir proformas, abonar, anular ventas). El módulo <kbd class="bg-primary">SISTEMA</kbd> es interno, mientras que <kbd class="bg-success">POS</kbd> o <kbd class="bg-dark">INVENTARIO</kbd> son movimientos de tienda.</li>
    </ul>
</div>

<div class="tab-content" id="pills-tabContent">
  
  <!-- Tab Accesos -->
  <div class="tab-pane fade show active" id="pills-accesos" role="tabpanel">
      <div class="card shadow-sm border-0">
          <div class="card-body p-0">
              <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0 datatable shadow-sm">
                      <thead class="table-dark-custom shadow-sm">
                          <tr>
                              <th class="ps-4">Fecha / Hora</th>
                              <th>Username</th>
                              <th>Dispositivo (User Agent)</th>
                              <th>Dirección IP</th>
                              <th>Ubicación Estimada</th>
                              <th>Estado</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php
                          $stmt = $pdo->query("SELECT * FROM bitacora_accesos ORDER BY fecha DESC LIMIT 100");
                          while($row = $stmt->fetch()):
                              $badge = $row['exito'] ? 'text-success' : 'text-danger';
                              $icon = $row['exito'] ? 'fa-check-circle' : 'fa-triangle-exclamation';
                          ?>
                          <tr>
                              <td class="ps-4 text-muted"><i class="fa-regular fa-clock"></i> <?php echo e(date('Y-m-d h:i A', strtotime($row['fecha']))); ?></td>
                              <td class="fw-bold"><?php echo e($row['username_intento']); ?></td>
                              <td class="small" style="max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo e($row['dispositivo']); ?>">
                                  <?php echo e($row['dispositivo']); ?>
                              </td>
                              <td><span class="badge bg-secondary"><?php echo e($row['ip']); ?></span></td>
                              <?php $ubicacionAcceso = trim((string)($row['ubicacion'] ?? '')); ?>
                              <td>
                                <small class="text-muted ip-location"
                                       data-ip="<?php echo e($row['ip']); ?>"
                                       data-location="<?php echo e($ubicacionAcceso); ?>">
                                    <?php echo $ubicacionAcceso !== '' ? e($ubicacionAcceso) : 'Detectando...'; ?>
                                </small>
                              </td>
                              <td><i class="fa-solid <?php echo $icon; ?> <?php echo $badge; ?> fs-5"></i></td>
                          </tr>
                          <?php endwhile; ?>
                      </tbody>
                  </table>
              </div>
          </div>
      </div>
  </div>

  <!-- Tab Acciones -->
  <div class="tab-pane fade" id="pills-acciones" role="tabpanel">
      <div class="card shadow-sm border-0">
          <div class="card-body p-0">
              <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0 datatable shadow-sm">
                      <thead class="table-dark-custom shadow-sm">
                          <tr>
                              <th class="ps-4">Fecha / Hora</th>
                              <th>Usuario</th>
                              <th>Módulo</th>
                              <th>Acción Técnica</th>
                              <th>Detalle / Payload</th>
                              <th>IP</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php
                          $stmt2 = $pdo->query("
                              SELECT a.*, u.username 
                              FROM bitacora_acciones a 
                              LEFT JOIN usuarios u ON a.usuario_id = u.id 
                              ORDER BY a.fecha DESC LIMIT 150
                          ");
                          while($row = $stmt2->fetch()):
                              $payloadDetalle = [
                                  'texto' => (string)$row['detalle'],
                                  'metodo_http' => '',
                                  'ubicacion_evento' => trim((string)($row['ubicacion'] ?? ''))
                              ];
                              $detalleTxt = (string)$row['detalle'];
                              $dec = json_decode($detalleTxt, true);
                              if (is_array($dec)) {
                                  $payloadDetalle['texto'] = (string)($dec['detalle'] ?? $detalleTxt);
                                  $payloadDetalle['metodo_http'] = (string)($dec['metodo_http'] ?? '');
                                  if (trim((string)($dec['ubicacion_evento'] ?? '')) !== '') {
                                      $payloadDetalle['ubicacion_evento'] = trim((string)$dec['ubicacion_evento']);
                                  }
                              }
                              if (trim($payloadDetalle['texto']) === '') {
                                  $payloadDetalle['texto'] = 'Sin detalle adicional.';
                              }
                          ?>
                           <tr>
                              <td class="ps-4 text-muted small"><i class="fa-regular fa-clock me-1"></i> <?php echo e(date('d/m/Y h:i A', strtotime($row['fecha']))); ?></td>
                              <td class="fw-bold"><i class="fa-solid fa-user me-1 text-muted"></i> <?php echo e($row['username'] ?? 'Sistema/Eliminado'); ?></td>
                              <?php
                              $modulo = strtoupper(trim((string)($row['modulo'] ?? '')));
                              $moduloClase = 'badge px-2';
                              if ($modulo === 'INVENTARIO') {
                                  $moduloClase .= ' badge-inventario text-dark border';
                              } elseif ($modulo === 'POS') {
                                  $moduloClase .= ' badge-pos text-white border';
                              } elseif ($modulo === 'SISTEMA') {
                                  $moduloClase .= ' badge-sistema text-white border';
                              } else {
                                  $moduloClase .= ' bg-primary-subtle text-primary border border-primary-subtle';
                              }
                              ?>
                              <td><span class="<?php echo e($moduloClase); ?>"><?php echo e($row['modulo']); ?></span></td>
                              <td><span class="badge bg-dark border font-monospace text-warning"><?php echo e($row['accion']); ?></span></td>
                              <td class="small">
                                  <div class="d-flex align-items-center justify-content-between">
                                      <span class="text-truncate d-inline-block" style="max-width: 260px;"><?php echo e($payloadDetalle['texto']); ?></span>
                                      <button class="btn btn-sm btn-link p-0 ms-2 text-primary fw-bold btn-bitacora-detalle" 
                                              data-id="<?php echo $row['id']; ?>"
                                              data-fecha="<?php echo e(date('d/m/Y h:i A', strtotime($row['fecha']))); ?>"
                                              data-usuario="<?php echo e($row['username'] ?? 'Sistema'); ?>"
                                              data-modulo="<?php echo e($row['modulo']); ?>"
                                              data-accion="<?php echo e($row['accion']); ?>"
                                              data-ip="<?php echo e($row['ip']); ?>"
                                              data-detalle="<?php echo e($payloadDetalle['texto']); ?>"
                                              data-metodo="<?php echo e($payloadDetalle['metodo_http']); ?>"
                                              data-location="<?php echo e($payloadDetalle['ubicacion_evento']); ?>">
                                          Ver detalles
                                      </button>
                                  </div>
                              </td>
                              <td>
                                  <small class="text-muted font-monospace d-block"><?php echo e($row['ip']); ?></small>
                                  <small class="text-muted ip-location"
                                         data-ip="<?php echo e($row['ip']); ?>"
                                         data-location="<?php echo e($payloadDetalle['ubicacion_evento']); ?>">
                                      <?php echo trim((string)$payloadDetalle['ubicacion_evento']) !== '' ? e($payloadDetalle['ubicacion_evento']) : 'Detectando...'; ?>
                                  </small>
                              </td>
                           </tr>
                          <?php endwhile; ?>
                      </tbody>
                  </table>
              </div>
          </div>
      </div>
  </div>
</div>

<!-- Modal Detalles Bitácora -->
<div class="modal fade" id="modalDetalleBitacora" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg border-0">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-circle-info me-2"></i> Detalles de la Actividad</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="mb-3">
          <label class="text-muted small fw-bold d-block mb-1">FECHA Y HORA:</label>
          <div id="bit-fecha" class="fw-bold fs-5"></div>
        </div>
        <div class="row mb-3">
          <div class="col-6">
            <label class="text-muted small fw-bold d-block mb-1">USUARIO:</label>
            <div id="bit-usuario" class="fw-bold"></div>
          </div>
          <div class="col-6">
            <label class="text-muted small fw-bold d-block mb-1">DIRECCIÓN IP:</label>
            <div id="bit-ip" class="font-monospace"></div>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-6">
            <label class="text-muted small fw-bold d-block mb-1">MÓDULO:</label>
            <span id="bit-modulo" class="badge"></span>
          </div>
          <div class="col-6">
            <label class="text-muted small fw-bold d-block mb-1">ACCIÓN TÉCNICA:</label>
            <span id="bit-accion" class="badge bg-dark border font-monospace text-warning"></span>
          </div>
        </div>
        <hr class="my-3 opacity-25">
        <div class="mb-0">
          <label class="text-muted small fw-bold d-block mb-1">INFORMACIÓN DETALLADA / PAYLOAD:</label>
          <div id="bit-detalle" class="p-3 bg-light rounded text-dark overflow-auto mb-2" style="max-height: 180px; font-size: 0.9rem;"></div>
          <div id="bit-location" class="small text-muted mb-2"></div>
          <div id="bit-meta" class="small text-muted" style="white-space: pre-line;"></div>
        </div>
      </div>
      <div class="modal-footer pb-3 pt-0 border-top-0">
        <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    $('.btn-bitacora-detalle').on('click', function() {
        $('#bit-fecha').text($(this).data('fecha'));
        $('#bit-usuario').text($(this).data('usuario'));
        $('#bit-ip').text($(this).data('ip'));
        const moduloDetalle = $(this).data('modulo') || '';
        $('#bit-modulo').text(moduloDetalle).removeClass();
        if (moduloDetalle.toUpperCase() === 'INVENTARIO') {
            $('#bit-modulo').addClass('badge badge-inventario text-dark border');
        } else if (moduloDetalle.toUpperCase() === 'POS') {
            $('#bit-modulo').addClass('badge badge-pos text-white border');
        } else if (moduloDetalle.toUpperCase() === 'SISTEMA') {
            $('#bit-modulo').addClass('badge badge-sistema text-white border');
        } else {
            $('#bit-modulo').addClass('badge bg-primary text-white');
        }
        $('#bit-accion').text($(this).data('accion'));
        $('#bit-detalle').text($(this).data('detalle'));
        const metodo = $(this).data('metodo') || '-';
        const ubic = $(this).data('location') || '';
        $('#bit-location').text(ubic ? ('Ubicación registrada: ' + ubic) : '');
        $('#bit-meta').text(
            'Módulo: ' + ($(this).data('modulo') || '-') + '\n' +
            'Acción: ' + ($(this).data('accion') || '-') + '\n' +
            'Método HTTP: ' + metodo
        );
        
        new bootstrap.Modal(document.getElementById('modalDetalleBitacora')).show();
    });
});

function completarUbicaciones() {
    const elementos = Array.from(document.querySelectorAll('.ip-location'));
    elementos.forEach((el) => {
        const snapshot = (el.getAttribute('data-location') || '').trim();
        if (snapshot) {
            el.textContent = snapshot;
            return;
        }
        el.textContent = '—';
    });
}

document.addEventListener('DOMContentLoaded', completarUbicaciones);
</script>

<style>
/* Ajustes para tabla en móvil */
@media (max-width: 768px) {
    .datatable { font-size: 0.85rem; }
    .card-body { padding: 1rem !important; }
}

/* Forzar encabezados legibles en modo oscuro */
.table-dark-custom {
    background-color: var(--bs-body-bg);
}
[data-bs-theme="dark"] .table-dark-custom th {
    background-color: #1a1d21 !important;
    color: #e9ecef !important;
    border-bottom: 2px solid #323539 !important;
}
[data-bs-theme="light"] .table-dark-custom th {
    background-color: #f8f9fa !important;
    color: #495057 !important;
    border-bottom: 2px solid #dee2e6 !important;
}

.table-dark-custom th {
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    padding: 15px 12px;
}
</style>

<?php require_once '../includes/footer.php'; ?>
