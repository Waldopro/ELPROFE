<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
restrictAdmin();
require_once '../includes/header.php';

// Formulario de guardar proveedor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_proveedor') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
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
            setFlash('success', 'Proveedor registrado correctamente.');
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                setFlash('error', 'Error: El RIF ya existe en el sistema.');
            } else {
                setFlash('error', 'Error al guardar el proveedor.');
            }
        }
    }
    header("Location: /ELPROFE/proveedores");
    exit;
}
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
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">RIF</th>
                        <th>Razón Social</th>
                        <th>Contacto</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Dirección</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM proveedores ORDER BY id DESC");
                    $proveedores = $stmt->fetchAll();
                    
                    if (count($proveedores) > 0) {
                        foreach($proveedores as $p) {
                            echo "<tr>
                                    <td class='ps-4'><span class='fw-bold'>".e($p['rif'])."</span></td>
                                    <td>".e($p['nombre'])."</td>
                                    <td>".e($p['contacto'] ?: '-')."</td>
                                    <td>".e($p['telefono'] ?: '-')."</td>
                                    <td>".e($p['email'] ?: '-')."</td>
                                    <td>".e($p['direccion'] ?: '-')."</td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center text-muted py-5'><i class='fa-solid fa-box-open fa-3x mb-3 text-light'></i><h5 class='mb-0'>No hay proveedores registrados</h5></td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nuevo Proveedor -->
<div class="modal fade" id="modalProveedor" tabindex="-1" aria-hidden="true">
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

<?php require_once '../includes/footer.php'; ?>
