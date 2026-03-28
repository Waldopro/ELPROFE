<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($_POST['action'] === 'actualizar_perfil') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));

        if ($nombre === '' || $username === '') {
            setFlash('error', 'Nombre y username son obligatorios.');
            header('Location: /ELPROFE/perfil');
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE usuarios SET nombre = ?, username = ? WHERE id = ?');
            $stmt->execute([$nombre, $username, $userId]);
            $_SESSION['user_name'] = $nombre;
            registrarAccion($pdo, 'SISTEMA', 'PERFIL_ACTUALIZADO', 'Usuario actualizó sus datos de perfil.');
            setFlash('success', 'Perfil actualizado correctamente.');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                setFlash('error', 'Ese username ya está en uso.');
            } else {
                setFlash('error', 'No se pudo actualizar el perfil.');
            }
        }

        header('Location: /ELPROFE/perfil');
        exit;
    }

    if ($_POST['action'] === 'cambiar_password') {
        $actual = (string)($_POST['password_actual'] ?? '');
        $nueva = (string)($_POST['password_nueva'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');

        if ($actual === '' || $nueva === '' || $confirm === '') {
            setFlash('error', 'Completa todos los campos de contraseña.');
            header('Location: /ELPROFE/perfil');
            exit;
        }
        if (strlen($nueva) < 6) {
            setFlash('error', 'La nueva contraseña debe tener al menos 6 caracteres.');
            header('Location: /ELPROFE/perfil');
            exit;
        }
        if ($nueva !== $confirm) {
            setFlash('error', 'La confirmación no coincide con la nueva contraseña.');
            header('Location: /ELPROFE/perfil');
            exit;
        }

        $stmt = $pdo->prepare('SELECT password FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $hash = (string)$stmt->fetchColumn();
        if ($hash === '' || !password_verify($actual, $hash)) {
            setFlash('error', 'La contraseña actual no es correcta.');
            header('Location: /ELPROFE/perfil');
            exit;
        }

        $nuevoHash = password_hash($nueva, PASSWORD_BCRYPT);
        $stmtUp = $pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ?');
        $stmtUp->execute([$nuevoHash, $userId]);
        registrarAccion($pdo, 'SEGURIDAD', 'PASSWORD_CAMBIADA', 'Usuario cambió su contraseña desde Mi Perfil.');
        setFlash('success', 'Contraseña actualizada correctamente.');

        header('Location: /ELPROFE/perfil');
        exit;
    }
}

$stmt = $pdo->prepare('SELECT id, nombre, username, rol, created_at FROM usuarios WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$u = $stmt->fetch();
if (!$u) {
    header('Location: /ELPROFE/logout');
    exit;
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 elprofe-hero">
  <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-id-badge me-2"></i> Mi Perfil</h2>
  <span class="badge bg-dark border border-secondary"><?php echo e($u['rol']); ?></span>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card shadow-sm border-0 elprofe-soft-card h-100">
      <div class="card-body p-4">
        <h5 class="fw-bold mb-3">Datos de Cuenta</h5>
        <form method="POST" action="/ELPROFE/perfil" class="row g-3">
          <input type="hidden" name="action" value="actualizar_perfil">
          <?php echo csrfField(); ?>

          <div class="col-12">
            <label class="form-label">Nombre visible</label>
            <input type="text" class="form-control" name="nombre" required value="<?php echo e($u['nombre']); ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Username de acceso</label>
            <input type="text" class="form-control" name="username" required value="<?php echo e($u['username']); ?>">
          </div>

          <div class="col-12 small text-muted">
            Usuario #<?php echo (int)$u['id']; ?> · Registrado: <?php echo e(date('d/m/Y H:i', strtotime((string)$u['created_at']))); ?>
          </div>

          <div class="col-12">
            <button class="btn btn-primary fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Guardar Perfil</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm border-0 elprofe-soft-card h-100">
      <div class="card-body p-4">
        <h5 class="fw-bold mb-3">Seguridad</h5>
        <form method="POST" action="/ELPROFE/perfil" class="row g-3">
          <input type="hidden" name="action" value="cambiar_password">
          <?php echo csrfField(); ?>

          <div class="col-12">
            <label class="form-label">Contraseña actual</label>
            <input type="password" class="form-control" name="password_actual" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Nueva contraseña</label>
            <input type="password" class="form-control" name="password_nueva" minlength="6" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Confirmar nueva contraseña</label>
            <input type="password" class="form-control" name="password_confirm" minlength="6" required>
          </div>

          <div class="col-12">
            <button class="btn btn-warning fw-bold"><i class="fa-solid fa-key me-1"></i> Cambiar Contraseña</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
