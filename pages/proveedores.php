<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
restrictAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    if ($_POST['action'] === 'save_proveedor') {
        $nombre = trim($_POST['nombre']);
        $rif = trim($_POST['rif']);
        $contacto = trim($_POST['contacto'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        
        if (empty($nombre) || empty($rif)) {
            setFlash('error', 'El nombre y el RIF son obligatorios.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO proveedores (nombre, rif, contacto, telefono, email, direccion) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $rif, $contacto, $telefono, $email, $direccion]);
                registrarAccion($pdo, 'PROVEEDORES', 'CREAR', "Proveedor creado: $rif - $nombre");
                setFlash('success', 'Proveedor registrado correctamente.');
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000) setFlash('error', 'Error: El RIF ya existe.');
                else setFlash('error', 'Error al guardar.');
            }
        }
    }
    
    if ($_POST['action'] === 'edit_proveedor') {
        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre']);
        $rif = trim($_POST['rif']);
        $contacto = trim($_POST['contacto'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        
        if ($id > 0 && !empty($nombre) && !empty($rif)) {
            try {
                $stmt = $pdo->prepare("UPDATE proveedores SET nombre=?, rif=?, contacto=?, telefono=?, email=?, direccion=? WHERE id=?");
                $stmt->execute([$nombre, $rif, $contacto, $telefono, $email, $direccion, $id]);
                registrarAccion($pdo, 'PROVEEDORES', 'MODIFICAR', "Proveedor actualizado ID: $id -> $nombre");
                setFlash('success', 'Proveedor actualizado.');
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000) setFlash('error', 'El RIF ya existe.');
                else setFlash('error', 'Error al actualizar.');
            }
        }
    }

    if ($_POST['action'] === 'delete_proveedor') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM proveedores WHERE id=?")->execute([$id]);
                registrarAccion($pdo, 'PROVEEDORES', 'ELIMINAR', "Proveedor eliminado ID: $id");
                setFlash('success', 'Proveedor eliminado.');
            } catch (\PDOException $e) {
                setFlash('error', 'No se puede eliminar, tiene dependencias.');
            }
        }
    }

    header("Location: /ELPROFE/proveedores");
    exit;
}
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-truck-field me-2"></i> Directorio de Proveedores</h2>
    <div>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalProveedor">
            <i class="fa-solid fa-plus me-1"></i> Registrar Proveedor
        </button>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="row mb-3 p-3 pb-0">
            <div class="col-md-6"></div>
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-0"><i class="fa-solid fa-search"></i></span>
                    <input type="text" class="form-control border-0 bg-light custom-search" placeholder="Buscar RIF o Nombre...">
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 datatable" id="tablaProveedores">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">RIF</th>
                        <th>Razón Social</th>
                        <th>Contacto</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Dirección</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM proveedores ORDER BY id DESC");
                    $proveedores = $stmt->fetchAll();
                    if (count($proveedores) > 0) {
                        foreach($proveedores as $p) {
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?php echo e($p['rif']); ?></td>
                                <td class="fw-bold"><?php echo e($p['nombre']); ?></td>
                                <td><?php echo e($p['contacto'] ?: '-'); ?></td>
                                <td><?php echo e($p['telefono'] ?: '-'); ?></td>
                                <td class="small text-muted"><?php echo e($p['email'] ?: '-'); ?></td>
                                <td class="small text-muted text-truncate" style="max-width: 150px;"><?php echo e($p['direccion'] ?: '-'); ?></td>
                                <td class="text-nowrap pe-4 text-end">
                                    <div class="d-flex justify-content-end gap-1 action-btns">
                                        <button type="button" class="btn btn-outline-primary btn-edit-prov"
                                            data-id="<?php echo e($p['id']); ?>" data-rif="<?php echo e($p['rif']); ?>" data-nombre="<?php echo e($p['nombre']); ?>"
                                            data-contacto="<?php echo e($p['contacto']); ?>" data-telefono="<?php echo e($p['telefono']); ?>"
                                            data-email="<?php echo e($p['email']); ?>" data-direccion="<?php echo e($p['direccion']); ?>"
                                            title="Editar Proveedor">
                                            <i class="fa-solid fa-truck-ramp-box"></i>
                                        </button>
                                        <form method="POST" action="/ELPROFE/proveedores" onsubmit="return confirm('¿Seguro de eliminar este proveedor?');" class="m-0">
                                            <input type="hidden" name="action" value="delete_proveedor">
                                            <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                            <?php echo csrfField(); ?>
                                            <button type="submit" class="btn btn-outline-danger" title="Eliminar">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center text-muted py-5'>No se encontraron proveedores.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nuevo Proveedor -->
<div class="modal fade" id="modalProveedor" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Registrar Proveedor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="/ELPROFE/proveedores">
          <div class="modal-body">
            <input type="hidden" name="action" value="save_proveedor">
            <?php echo csrfField(); ?>
            <div class="mb-3">
                <label class="form-label text-muted small">RIF *</label>
                <input type="text" name="rif" class="form-control" required placeholder="Ej: J-12345678-9">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Razón Social *</label>
                <input type="text" name="nombre" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Contacto</label>
                <input type="text" name="contacto" class="form-control" placeholder="Persona de contacto">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Teléfono</label>
                <input type="text" name="telefono" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Email</label>
                <input type="email" name="email" class="form-control" placeholder="correo@dominio.com">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Dirección</label>
                <textarea name="direccion" class="form-control" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer pb-3">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm"><i class="fa-solid fa-save"></i> Guardar</button>
          </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEditProveedor" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Editar Proveedor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="/ELPROFE/proveedores">
          <div class="modal-body">
            <input type="hidden" name="action" value="edit_proveedor">
            <input type="hidden" name="id" id="ep_id">
            <?php echo csrfField(); ?>
            <div class="mb-3">
                <label class="form-label text-muted small">RIF *</label>
                <input type="text" name="rif" id="ep_rif" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Razón Social *</label>
                <input type="text" name="nombre" id="ep_nombre" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Contacto</label>
                <input type="text" name="contacto" id="ep_contacto" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Teléfono</label>
                <input type="text" name="telefono" id="ep_telefono" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Email</label>
                <input type="email" name="email" id="ep_email" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Dirección</label>
                <textarea name="direccion" id="ep_direccion" class="form-control" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer pb-3">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm"><i class="fa-solid fa-save"></i> Guardar</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    $('.btn-edit-prov').on('click', function() {
        $('#ep_id').val($(this).data('id'));
        $('#ep_rif').val($(this).data('rif'));
        $('#ep_nombre').val($(this).data('nombre'));
        $('#ep_contacto').val($(this).data('contacto'));
        $('#ep_telefono').val($(this).data('telefono'));
        $('#ep_email').val($(this).data('email'));
        $('#ep_direccion').val($(this).data('direccion'));
        var m = new bootstrap.Modal(document.getElementById('modalEditProveedor'));
        m.show();
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
