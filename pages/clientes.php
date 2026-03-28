<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
require_once '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    if ($_POST['action'] === 'save_cliente') {
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido'] ?? '');
        $cedula_rif = trim($_POST['cedula_rif']);
        $telefono = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $ubicacion = trim($_POST['ubicacion'] ?? '');
        
        if (empty($nombre) || empty($cedula_rif)) {
            setFlash('error', 'El nombre y la cédula resuelven obligatorios.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO clientes (nombre, apellido, cedula_rif, telefono, direccion, ubicacion) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $apellido, $cedula_rif, $telefono, $direccion, $ubicacion]);
                registrarAccion($pdo, 'CLIENTES', 'CREAR', "Cliente registrado: $cedula_rif - $nombre");
                setFlash('success', 'Cliente registrado correctamente.');
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000) setFlash('error', 'Error: La cédula/RIF ya existe.');
                else setFlash('error', 'Error al guardar el cliente.');
            }
        }
    }

    if ($_POST['action'] === 'edit_cliente') {
        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido'] ?? '');
        $cedula_rif = trim($_POST['cedula_rif']);
        $telefono = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $ubicacion = trim($_POST['ubicacion'] ?? '');
        
        if ($id > 0 && !empty($nombre) && !empty($cedula_rif)) {
            try {
                $stmt = $pdo->prepare("UPDATE clientes SET nombre=?, apellido=?, cedula_rif=?, telefono=?, direccion=?, ubicacion=? WHERE id=?");
                $stmt->execute([$nombre, $apellido, $cedula_rif, $telefono, $direccion, $ubicacion, $id]);
                registrarAccion($pdo, 'CLIENTES', 'MODIFICAR', "Cliente actualizado ID: $id -> $nombre");
                setFlash('success', 'Cliente actualizado correctamente.');
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000) setFlash('error', 'La cédula/RIF ya existe.');
                else setFlash('error', 'Error al actualizar el cliente.');
            }
        }
    }

    if ($_POST['action'] === 'delete_cliente') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM clientes WHERE id=?")->execute([$id]);
                registrarAccion($pdo, 'CLIENTES', 'ELIMINAR', "Cliente eliminado ID: $id");
                setFlash('success', 'Cliente eliminado.');
            } catch (\PDOException $e) {
                setFlash('error', 'No se puede eliminar, el cliente tiene dependencias activas.');
            }
        }
    }

    header("Location: /ELPROFE/clientes");
    exit;
}

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-users me-2"></i> Directorio de Clientes</h2>
    <div>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCliente">
            <i class="fa-solid fa-plus me-1"></i> Registrar Cliente
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
                    <input type="text" class="form-control border-0 bg-light custom-search" placeholder="Buscar Documento o Nombre...">
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 datatable" id="tablaClientes">
                <thead class="table-dark-custom shadow-sm">
                    <tr>
                        <th class="ps-4">Cédula / RIF</th>
                        <th>Nombre Completo</th>
                        <th>Teléfono</th>
                        <th>Ubicación</th>
                        <th>Fecha Registro</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM clientes ORDER BY id DESC LIMIT 50");
                    $clientes = $stmt->fetchAll();
                    
                    if (count($clientes) > 0) {
                        foreach($clientes as $c) {
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?php echo e($c['cedula_rif']); ?></td>
                                <td><?php echo e($c['nombre'] . ' ' . $c['apellido']); ?></td>
                                <td><?php echo e($c['telefono'] ?: '-'); ?></td>
                                <td><?php echo e($c['ubicacion'] ?: '-'); ?></td>
                                <td><?php echo e(date('d/m/Y', strtotime($c['created_at']))); ?></td>
                                <td class="text-nowrap pe-4">
                                    <div class="d-flex justify-content-end gap-1 action-btns">
                                        <button type="button" class="btn btn-outline-primary btn-edit-cli"
                                            data-id="<?php echo e($c['id']); ?>" data-nombre="<?php echo e($c['nombre']); ?>" data-apellido="<?php echo e($c['apellido']); ?>"
                                            data-cedula="<?php echo e($c['cedula_rif']); ?>" data-telefono="<?php echo e($c['telefono']); ?>"
                                            data-ubicacion="<?php echo e($c['ubicacion']); ?>" data-direccion="<?php echo e($c['direccion']); ?>"
                                            title="Editar">
                                            <i class="fa-solid fa-user-pen"></i>
                                        </button>
                                        <form method="POST" action="/ELPROFE/clientes" onsubmit="return confirm('¿Eliminar cliente?');" class="m-0">
                                            <input type="hidden" name="action" value="delete_cliente">
                                            <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
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
                        echo "<tr><td colspan='6' class='text-center text-muted py-5'><i class='fa-solid fa-folder-open fa-3x mb-3 text-light'></i><h5 class='mb-0'>No hay clientes registrados</h5></td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nuevo Cliente -->
<div class="modal fade" id="modalCliente" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Registrar Cliente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="/ELPROFE/clientes">
          <div class="modal-body">
            <input type="hidden" name="action" value="save_cliente">
            <?php echo csrfField(); ?>
            <div class="mb-3">
                <label class="form-label text-muted small">Cédula o RIF *</label>
                <input type="text" name="cedula_rif" class="form-control" required placeholder="Ej: V-12345678">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Nombre *</label>
                <input type="text" name="nombre" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Apellido</label>
                <input type="text" name="apellido" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Teléfono</label>
                <input type="text" name="telefono" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Ubicación</label>
                <input type="text" name="ubicacion" class="form-control" placeholder="Ej: Sector / Ciudad / Referencia">
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

<div class="modal fade" id="modalEditCliente" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Editar Cliente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="/ELPROFE/clientes">
          <div class="modal-body">
            <input type="hidden" name="action" value="edit_cliente">
            <input type="hidden" name="id" id="ec_id">
            <?php echo csrfField(); ?>
            <div class="mb-3">
                <label class="form-label text-muted small">Cédula o RIF *</label>
                <input type="text" name="cedula_rif" id="ec_cedula" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Nombre *</label>
                <input type="text" name="nombre" id="ec_nombre" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Apellido</label>
                <input type="text" name="apellido" id="ec_apellido" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Teléfono</label>
                <input type="text" name="telefono" id="ec_telefono" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Ubicación</label>
                <input type="text" name="ubicacion" id="ec_ubicacion" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Dirección</label>
                <textarea name="direccion" id="ec_direccion" class="form-control" rows="2"></textarea>
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
    $('.btn-edit-cli').on('click', function() {
        $('#ec_id').val($(this).data('id'));
        $('#ec_cedula').val($(this).data('cedula'));
        $('#ec_nombre').val($(this).data('nombre'));
        $('#ec_apellido').val($(this).data('apellido'));
        $('#ec_telefono').val($(this).data('telefono'));
        $('#ec_ubicacion').val($(this).data('ubicacion'));
        $('#ec_direccion').val($(this).data('direccion'));
        var m = new bootstrap.Modal(document.getElementById('modalEditCliente'));
        m.show();
    });
});
</script>

<style>
/* Ajustes para tabla en móvil */
@media (max-width: 768px) {
    #tablaClientes { font-size: 0.85rem; }
    .action-btns .btn { padding: 4px 8px; font-size: 0.8rem; }
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
