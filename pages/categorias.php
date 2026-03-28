<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
restrictAdmin();

// Auto-bootstrap de tabla (para instalaciones existentes sin migraciones)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categorias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(80) NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {
    // Si falla, la consulta abajo mostrará el error de todas formas
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($_POST['action'] === 'add_categoria') {
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') {
            setFlash('error', 'Nombre de categoría requerido.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO categorias (nombre) VALUES (?)");
                $stmt->execute([$nombre]);
                registrarAccion($pdo, 'CATEGORIAS', 'CREAR', "Categoría creada: $nombre");
                setFlash('success', 'Categoría creada.');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) setFlash('error', 'La categoría ya existe.');
                else setFlash('error', 'Error al crear categoría.');
            }
        }
    }

    if ($_POST['action'] === 'delete_categoria') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
                $stmt->execute([$id]);
                registrarAccion($pdo, 'CATEGORIAS', 'ELIMINAR', "Categoría eliminada ID: $id");
                setFlash('success', 'Categoría eliminada.');
            } catch (PDOException $e) {
                setFlash('error', 'No se pudo eliminar. Puede estar en uso por productos.');
            }
        }
    }

    if ($_POST['action'] === 'edit_categoria') {
        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        if ($id > 0 && $nombre !== '') {
            try {
                $stmt = $pdo->prepare("UPDATE categorias SET nombre = ? WHERE id = ?");
                $stmt->execute([$nombre, $id]);
                registrarAccion($pdo, 'CATEGORIAS', 'MODIFICAR', "Categoría editada ID: $id -> $nombre");
                setFlash('success', 'Categoría actualizada.');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) setFlash('error', 'La categoría ya existe.');
                else setFlash('error', 'Error al actualizar.');
            }
        }
    }

    header("Location: /ELPROFE/categorias");
    exit;
}

$cats = $pdo->query("SELECT * FROM categorias ORDER BY nombre ASC")->fetchAll();
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-tags me-2"></i> Categorías</h2>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCategoria">
        <i class="fa-solid fa-plus me-1"></i> Nueva Categoría
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="row mb-3 p-3 pb-0">
            <div class="col-md-6"></div>
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-0"><i class="fa-solid fa-search"></i></span>
                    <input type="text" class="form-control border-0 bg-light custom-search" placeholder="Buscar por Nombre...">
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 datatable" id="tablaCategorias">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Nombre</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($cats) > 0): ?>
                        <?php foreach ($cats as $c): ?>
                            <tr>
                                <td class="ps-4 fw-bold">#<?php echo (int)$c['id']; ?></td>
                                <td><?php echo e($c['nombre']); ?></td>
                                <td class="text-end pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1 btn-edit" data-id="<?php echo (int)$c['id']; ?>" data-nombre="<?php echo e($c['nombre']); ?>">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="POST" action="/ELPROFE/categorias" class="d-inline" onsubmit="return confirm('¿Eliminar categoría?');">
                                        <input type="hidden" name="action" value="delete_categoria">
                                        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                        <?php echo csrfField(); ?>
                                        <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-center text-muted py-5">No hay categorías registradas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCategoria" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Nueva Categoría</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="/ELPROFE/categorias">
        <div class="modal-body">
          <input type="hidden" name="action" value="add_categoria">
          <?php echo csrfField(); ?>
          <div class="mb-3">
            <label class="form-label text-muted small">Nombre *</label>
            <input type="text" name="nombre" class="form-control" required placeholder="Ej: Ferretería / Abarrotes">
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

<div class="modal fade" id="modalEditCategoria" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Editar Categoría</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="/ELPROFE/categorias">
        <div class="modal-body">
          <input type="hidden" name="action" value="edit_categoria">
          <input type="hidden" name="id" id="edit_cat_id">
          <?php echo csrfField(); ?>
          <div class="mb-3">
            <label class="form-label text-muted small">Nombre *</label>
            <input type="text" name="nombre" id="edit_cat_nombre" class="form-control" required>
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
    $('.btn-edit').on('click', function() {
        $('#edit_cat_id').val($(this).data('id'));
        $('#edit_cat_nombre').val($(this).data('nombre'));
        var m = new bootstrap.Modal(document.getElementById('modalEditCategoria'));
        m.show();
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
