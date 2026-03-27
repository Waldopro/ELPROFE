<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
if (!isAdmin()) {
    header("Location: /ELPROFE/dashboard"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    if ($_POST['action'] === 'add_user') {
        $user = trim($_POST['username']);
        $pass = $_POST['password'];
        $nombre = trim($_POST['nombre']);
        $rol = $_POST['rol'];
        
        if ($user && $pass && $nombre) {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare("INSERT INTO usuarios (username, password, nombre, rol) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user, $hash, $nombre, $rol]);
                setFlash('success', 'Usuario registrado exitosamente.');
            } catch(PDOException $e) {
                if ($e->getCode() == 23000) setFlash('error', 'El nombre de usuario ya existe.');
                else setFlash('error', 'Error al crear usuario.');
            }
        }
    }
    
    if ($_POST['action'] === 'delete_user') {
        $id = intval($_POST['user_id']);
        if ($id !== $_SESSION['user_id']) { // Prevenir auto-eliminación
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Usuario eliminado.');
        } else {
            setFlash('error', 'No puedes auto eliminarte.');
        }
    }

    if ($_POST['action'] === 'edit_user') {
        $id = intval($_POST['user_id'] ?? 0);
        if ($id <= 0) {
            setFlash('error', 'Usuario inválido.');
            header("Location: /ELPROFE/usuarios");
            exit;
        }

        if ($id === $_SESSION['user_id']) {
            setFlash('error', 'No puedes editar tu propia cuenta desde aquí.');
            header("Location: /ELPROFE/usuarios");
            exit;
        }

        $user = trim($_POST['username'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $rol = $_POST['rol'] ?? 'CAJERO';
        $pass = $_POST['password'] ?? '';

        if ($user === '' || $nombre === '' || !in_array($rol, ['ADMIN', 'CAJERO'], true)) {
            setFlash('error', 'Datos inválidos para editar usuario.');
            header("Location: /ELPROFE/usuarios");
            exit;
        }

        try {
            if ($pass !== '') {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE usuarios SET username = ?, password = ?, nombre = ?, rol = ? WHERE id = ?");
                $stmt->execute([$user, $hash, $nombre, $rol, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE usuarios SET username = ?, nombre = ?, rol = ? WHERE id = ?");
                $stmt->execute([$user, $nombre, $rol, $id]);
            }
            setFlash('success', 'Usuario actualizado correctamente.');
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) setFlash('error', 'El username ya existe.');
            else setFlash('error', 'Error al actualizar usuario.');
        }
    }
    
    header("Location: /ELPROFE/usuarios");
    exit;
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-users-gear me-2"></i> Gestión de Usuarios</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario">
        <i class="fa-solid fa-plus"></i> Nuevo Usuario
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Nombre / Empleado</th>
                        <th>Username (Credencial)</th>
                        <th>Rol</th>
                        <th>Creado</th>
                        <th class="text-center pe-4"><i class="fa-solid fa-tools"></i> Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM usuarios ORDER BY id ASC");
                    while($row = $stmt->fetch()):
                        $badge = $row['rol'] === 'ADMIN' ? 'bg-danger' : 'bg-primary';
                    ?>
                    <tr>
                        <td class="ps-4 fw-bold">#<?php echo $row['id']; ?></td>
                        <td><?php echo e($row['nombre']); ?></td>
                        <td class="text-muted"><i class="fa-solid fa-user-lock"></i> <?php echo e($row['username']); ?></td>
                        <td><span class="badge <?php echo $badge; ?>"><?php echo e($row['rol']); ?></span></td>
                        <td><?php echo e(date('d/m/Y', strtotime($row['created_at']))); ?></td>
                        <td class="text-center pe-4">
                            <?php if($row['id'] !== $_SESSION['user_id']): ?>
                            <button type="button"
                                class="btn btn-sm btn-outline-secondary me-1 btn-editar-usuario"
                                data-user-id="<?php echo (int)$row['id']; ?>"
                                data-nombre="<?php echo e($row['nombre']); ?>"
                                data-username="<?php echo e($row['username']); ?>"
                                data-rol="<?php echo e($row['rol']); ?>"
                                title="Editar usuario">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form method="POST" action="/ELPROFE/usuarios" class="d-inline" onsubmit="return confirm('¿Eliminar usuario? Esta acción rompe registros FK si ya interactuó. Preferiblemente se deben inactivar (Fase 2).');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                <?php echo csrfField(); ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                            <?php else: ?>
                            <span class="text-success small fw-bold">Mi Cuenta</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nuevo Usuario -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold">Añadir Personal</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="/ELPROFE/usuarios">
          <div class="modal-body p-4">
              <input type="hidden" name="action" value="add_user">
              <?php echo csrfField(); ?>
              
              <div class="mb-3">
                  <label class="form-label">Nombre Completo</label>
                  <input type="text" name="nombre" class="form-control" required>
              </div>
              <div class="row">
                  <div class="col-6 mb-3">
                      <label class="form-label">Username (Para iniciar sesión)</label>
                      <input type="text" name="username" class="form-control" required autocomplete="off">
                  </div>
                  <div class="col-6 mb-3">
                      <label class="form-label">Constraseña Segura</label>
                      <input type="password" name="password" class="form-control" required autocomplete="new-password">
                  </div>
              </div>
              <div class="mb-3">
                  <label class="form-label">Rol del Sistema</label>
                  <select name="rol" class="form-select">
                      <option value="CAJERO">CAJERO (Acceso solo al POS y Clientes)</option>
                      <option value="ADMIN">ADMINISTRADOR (Acceso global al ERP)</option>
                  </select>
              </div>
          </div>
          <div class="modal-footer">
              <button type="submit" class="btn btn-primary fw-bold w-100"><i class="fa-solid fa-check"></i> Registrar Trabajador</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Editar Usuario -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title fw-bold">Editar Usuario</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="/ELPROFE/usuarios" id="formEditarUsuario">
        <div class="modal-body p-4">
          <input type="hidden" name="action" value="edit_user">
          <?php echo csrfField(); ?>
          <input type="hidden" name="user_id" id="editar-user-id">

          <div class="mb-3">
            <label class="form-label">Nombre Completo</label>
            <input type="text" name="nombre" class="form-control" id="editar-nombre" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Username (Credencial)</label>
            <input type="text" name="username" class="form-control" id="editar-username" required autocomplete="off">
          </div>

          <div class="row">
            <div class="col-12 mb-3">
              <label class="form-label">Nueva Contraseña (opcional)</label>
              <input type="password" name="password" class="form-control" id="editar-password" placeholder="Dejar vacío para no cambiar">
            </div>
          </div>

          <div class="mb-1">
            <label class="form-label">Rol del Sistema</label>
            <select name="rol" class="form-select" id="editar-rol">
              <option value="CAJERO">CAJERO (Acceso solo al POS y Clientes)</option>
              <option value="ADMIN">ADMINISTRADOR (Acceso global al ERP)</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary fw-bold w-100"><i class="fa-solid fa-check"></i> Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const modalEl = document.getElementById('modalEditarUsuario');
  const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
  const form = document.getElementById('formEditarUsuario');

  document.querySelectorAll('.btn-editar-usuario').forEach((btn) => {
    btn.addEventListener('click', () => {
      const userId = btn.getAttribute('data-user-id');
      document.getElementById('editar-user-id').value = userId;
      document.getElementById('editar-nombre').value = btn.getAttribute('data-nombre');
      document.getElementById('editar-username').value = btn.getAttribute('data-username');
      document.getElementById('editar-rol').value = btn.getAttribute('data-rol');
      document.getElementById('editar-password').value = '';
      if(modal) modal.show();
    });
  });
});
</script>

<?php require_once '../includes/footer.php'; ?>
