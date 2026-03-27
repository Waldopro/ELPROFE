<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
require_once '../includes/header.php';

// Formulario de guardar cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_cliente') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
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
            setFlash('success', 'Cliente registrado correctamente.');
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                setFlash('error', 'Error: La cédula/RIF ya existe en el sistema.');
            } else {
                setFlash('error', 'Error al guardar el cliente.');
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
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Cédula / RIF</th>
                        <th>Nombre y Apellido</th>
                        <th>Teléfono</th>
                        <th>Ubicación</th>
                        <th>Fecha Registro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM clientes ORDER BY id DESC LIMIT 50");
                    $clientes = $stmt->fetchAll();
                    
                    if (count($clientes) > 0) {
                        foreach($clientes as $c) {
                            echo "<tr>
                                    <td class='ps-4'><span class='fw-bold'>".e($c['cedula_rif'])."</span></td>
                                    <td>".e($c['nombre'] . ' ' . $c['apellido'])."</td>
                                    <td>".e($c['telefono'] ?: '-')."</td>
                                    <td>".e($c['ubicacion'] ?: '-')."</td>
                                    <td>".e(date('d/m/Y', strtotime($c['created_at'])))."</td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center text-muted py-5'><i class='fa-solid fa-folder-open fa-3x mb-3 text-light'></i><h5 class='mb-0'>No hay clientes registrados</h5></td></tr>";
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

<?php require_once '../includes/footer.php'; ?>
