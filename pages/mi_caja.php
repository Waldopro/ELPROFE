<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();

// Abrir / Cerrar sesión de caja (por usuario)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    if ($_POST['action'] === 'abrir_sesion') {
        $m_usd = floatval($_POST['monto_inicial_usd'] ?? 0);
        $m_bs = floatval($_POST['monto_inicial_bs'] ?? 0);

        // Evitar doble apertura
        $stmt = $pdo->prepare("SELECT id FROM sesiones_caja WHERE usuario_id = ? AND estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $existe = $stmt->fetchColumn();
        if ($existe) {
            setFlash('error', 'Ya tienes una caja ABIERTA.');
            header("Location: /ELPROFE/mi_caja");
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO sesiones_caja (usuario_id, monto_inicial_usd, monto_inicial_bs) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $m_usd, $m_bs]);
        setFlash('success', 'Caja abierta correctamente.');
        header("Location: /ELPROFE/mi_caja");
        exit;
    }

    if ($_POST['action'] === 'cerrar_sesion') {
        $m_cierre_usd = ($_POST['monto_cierre_usd_declarado'] ?? '') === '' ? null : floatval($_POST['monto_cierre_usd_declarado']);
        $m_cierre_bs = ($_POST['monto_cierre_bs_declarado'] ?? '') === '' ? null : floatval($_POST['monto_cierre_bs_declarado']);
        $notas = trim($_POST['notas_cierre'] ?? '');

        $stmt = $pdo->prepare("SELECT id FROM sesiones_caja WHERE usuario_id = ? AND estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $sesId = $stmt->fetchColumn();

        if (!$sesId) {
            setFlash('error', 'No hay caja ABIERTA para cerrar.');
            header("Location: /ELPROFE/mi_caja");
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE sesiones_caja
               SET estado = 'CERRADA',
                   fecha_cierre = NOW(),
                   monto_cierre_usd_declarado = ?,
                   monto_cierre_bs_declarado = ?,
                   notas_cierre = ?
             WHERE id = ? AND usuario_id = ?
        ");
        $stmt->execute([$m_cierre_usd, $m_cierre_bs, $notas, $sesId, $_SESSION['user_id']]);
        setFlash('success', 'Caja cerrada correctamente.');
        header("Location: /ELPROFE/mi_caja");
        exit;
    }
}

// Sesión abierta actual
$stmt = $pdo->prepare("SELECT * FROM sesiones_caja WHERE usuario_id = ? AND estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$sesion = $stmt->fetch();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-cash-register me-2"></i> Mi Caja</h2>
  <span class="badge bg-dark border border-secondary">Multi-caja</span>
</div>

<?php if (!$sesion): ?>
  <div class="card shadow-sm border-0">
    <div class="card-body p-4">
      <h5 class="fw-bold mb-2">Abrir caja</h5>
      <p class="text-muted mb-4">Debes abrir tu caja antes de procesar ventas o registrar abonos.</p>
      <form method="POST" action="/ELPROFE/mi_caja" class="row g-3">
        <input type="hidden" name="action" value="abrir_sesion">
        <?php echo csrfField(); ?>
        <div class="col-md-6">
          <label class="form-label">Monto inicial (USD)</label>
          <input type="number" step="0.01" min="0" name="monto_inicial_usd" class="form-control" value="0.00">
        </div>
        <div class="col-md-6">
          <label class="form-label">Monto inicial (Bs)</label>
          <input type="number" step="0.01" min="0" name="monto_inicial_bs" class="form-control" value="0.00">
        </div>
        <div class="col-12">
          <button class="btn btn-success fw-bold px-4"><i class="fa-solid fa-lock-open me-2"></i> Abrir Caja</button>
        </div>
      </form>
    </div>
  </div>
<?php else: ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body p-4">
          <h6 class="text-muted text-uppercase fw-bold mb-3">Sesión activa</h6>
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-bold">#<?php echo (int)$sesion['id']; ?></div>
              <div class="small text-muted">Apertura: <?php echo e(date('d/m/Y H:i', strtotime($sesion['fecha_apertura']))); ?></div>
            </div>
            <span class="badge bg-success">ABIERTA</span>
          </div>
          <hr class="opacity-25">
          <div class="small text-muted">Inicial USD: <strong>$<?php echo formatMoney((float)$sesion['monto_inicial_usd']); ?></strong></div>
          <div class="small text-muted">Inicial Bs: <strong>Bs. <?php echo formatMoney((float)$sesion['monto_inicial_bs']); ?></strong></div>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Balance por método</h5>
            <span class="text-muted small">Actualiza en tiempo real</span>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="bg-light">
                <tr>
                  <th>Método</th>
                  <th class="text-end">Balance USD</th>
                  <th class="text-end">Balance Bs</th>
                </tr>
              </thead>
              <tbody id="mi-caja-balances">
                <tr><td colspan="3" class="text-center text-muted py-4">Cargando...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm border-0 mt-4">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Últimos movimientos</h5>
        <span class="text-muted small" id="mi-caja-server-time"></span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="bg-light">
            <tr>
              <th>Fecha</th>
              <th>Método</th>
              <th>Tipo</th>
              <th>Ref</th>
              <th class="text-end">USD</th>
              <th class="text-end">Bs</th>
            </tr>
          </thead>
          <tbody id="mi-caja-movs">
            <tr><td colspan="6" class="text-center text-muted py-4">Cargando...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card shadow-sm border-0 mt-4">
    <div class="card-body p-4">
      <h5 class="fw-bold mb-3 text-danger"><i class="fa-solid fa-lock me-2"></i> Cerrar caja</h5>
      <form method="POST" action="/ELPROFE/mi_caja" class="row g-3">
        <input type="hidden" name="action" value="cerrar_sesion">
        <?php echo csrfField(); ?>
        <div class="col-md-6">
          <label class="form-label">Monto cierre declarado (USD)</label>
          <input type="number" step="0.01" min="0" name="monto_cierre_usd_declarado" class="form-control" placeholder="Opcional">
        </div>
        <div class="col-md-6">
          <label class="form-label">Monto cierre declarado (Bs)</label>
          <input type="number" step="0.01" min="0" name="monto_cierre_bs_declarado" class="form-control" placeholder="Opcional">
        </div>
        <div class="col-12">
          <label class="form-label">Notas</label>
          <textarea name="notas_cierre" class="form-control" rows="2" placeholder="Opcional"></textarea>
        </div>
        <div class="col-12">
          <button class="btn btn-danger fw-bold" onclick="return confirm('¿Cerrar caja? Ya no podrás registrar ventas en esta sesión.');">
            <i class="fa-solid fa-door-closed me-2"></i> Cerrar Caja
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function fmt(n) {
      const num = Number(n || 0);
      return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    async function refrescarMiCaja() {
      try {
        const res = await fetch('/ELPROFE/api/caja.php?action=estado_sesion', {
          headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
        });
        const json = await res.json();
        if (!json.success) return;
        if (!json.abierta) return;

        document.getElementById('mi-caja-server-time').textContent = 'Servidor: ' + (json.server_time || '');

        const tb = document.getElementById('mi-caja-balances');
        tb.innerHTML = '';
        (json.balances || []).forEach((b) => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td class="fw-bold">${b.nombre}</td>
            <td class="text-end text-success">$${fmt(b.balance_usd)}</td>
            <td class="text-end">Bs. ${fmt(b.balance_bs)}</td>
          `;
          tb.appendChild(tr);
        });

        const tm = document.getElementById('mi-caja-movs');
        tm.innerHTML = '';
        const movs = json.movimientos || [];
        if (movs.length === 0) {
          tm.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">No hay movimientos aún.</td></tr>`;
          return;
        }
        movs.forEach((m) => {
          const badge = m.tipo_movimiento === 'ENTRADA' ? 'bg-success' : 'bg-danger';
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td class="text-muted small">${m.fecha}</td>
            <td class="fw-bold">${m.metodo_nombre}</td>
            <td><span class="badge ${badge}">${m.tipo_movimiento}</span></td>
            <td class="text-muted small">${(m.referencia_tabla || '')} #${(m.referencia_id || '')}</td>
            <td class="text-end">$${fmt(m.monto_usd)}</td>
            <td class="text-end">Bs. ${fmt(m.monto_bs)}</td>
          `;
          tm.appendChild(tr);
        });
      } catch (e) {}
    }

    document.addEventListener('DOMContentLoaded', function() {
      refrescarMiCaja();
      setInterval(refrescarMiCaja, 5000);
    });
  </script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

