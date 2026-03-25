<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
restrictAdmin();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-shield-halved me-2"></i> Auditoría y Bitácoras</h2>
</div>

<ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active fw-bold" data-bs-toggle="pill" data-bs-target="#pills-accesos" type="button">Log de Accesos (Login)</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#pills-acciones" type="button">Traza de Acciones (Actividad)</button>
  </li>
</ul>

<div class="tab-content" id="pills-tabContent">
  
  <!-- Tab Accesos -->
  <div class="tab-pane fade show active" id="pills-accesos" role="tabpanel">
      <div class="card shadow-sm border-0">
          <div class="card-body p-0">
              <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                      <thead class="bg-light">
                          <tr>
                              <th class="ps-4">Fecha / Hora</th>
                              <th>Username</th>
                              <th>Dispositivo (User Agent)</th>
                              <th>Dirección IP</th>
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
                              <td class="ps-4 text-muted"><i class="fa-regular fa-clock"></i> <?php echo e($row['fecha']); ?></td>
                              <td class="fw-bold"><?php echo e($row['username_intento']); ?></td>
                              <td class="small" style="max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo e($row['dispositivo']); ?>">
                                  <?php echo e($row['dispositivo']); ?>
                              </td>
                              <td><span class="badge bg-secondary"><?php echo e($row['ip']); ?></span></td>
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
                  <table class="table table-hover align-middle mb-0">
                      <thead class="bg-light">
                          <tr>
                              <th class="ps-4">Fecha</th>
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
                          ?>
                          <tr>
                              <td class="ps-4 text-muted"><i class="fa-regular fa-calendar-days"></i> <?php echo e($row['fecha']); ?></td>
                              <td class="fw-bold"><i class="fa-solid fa-user-tag text-muted"></i> <?php echo e($row['username'] ?? 'Sistema/Eliminado'); ?></td>
                              <td><span class="badge bg-primary px-2"><?php echo e($row['modulo']); ?></span></td>
                              <td><span class="badge bg-dark border font-monospace text-warning"><?php echo e($row['accion']); ?></span></td>
                              <td class="small"><?php echo e($row['detalle']); ?></td>
                              <td><small class="text-muted"><?php echo e($row['ip']); ?></small></td>
                          </tr>
                          <?php endwhile; ?>
                      </tbody>
                  </table>
              </div>
          </div>
      </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
