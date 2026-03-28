<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
$esAdmin = isAdmin();

function tipoMetodoPago(array $metodo): string {
    $nombre = strtolower(trim((string)($metodo['nombre'] ?? '')));
    $esUsd = str_contains($nombre, 'usd') || str_contains($nombre, 'dolar') || str_contains($nombre, 'dólar') || str_contains($nombre, 'usdt') || str_contains($nombre, 'binance');
    return $esUsd ? 'USD' : 'BS';
}

// Registrar Abono / Pago Parcial o Total para Fiados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar_abono') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $proforma_id = intval($_POST['proforma_id'] ?? 0);
    $modo_pago = strtoupper(trim((string)($_POST['modo_pago'] ?? 'MIXTO')));

    // TASA ACTUAL DEL SISTEMA
    $tasa_hoy = floatval(getConfig('tasa_usd_bs', $pdo));
    if ($tasa_hoy <= 0) {
        setFlash('error', 'La tasa USD/Bs es inválida. Verifique configuración.');
        header('Location: /ELPROFE/proformas');
        exit;
    }

    $pagos = [];
    $pagosJson = trim((string)($_POST['pagos_json'] ?? ''));

    if ($pagosJson !== '') {
        $tmp = json_decode($pagosJson, true);
        if (is_array($tmp)) {
            $pagos = $tmp;
        }
    }

    // Compatibilidad con envío simple
    if (count($pagos) === 0) {
        $pagos[] = [
            'metodo_pago_id' => intval($_POST['metodo_pago_id'] ?? 0),
            'monto_usd' => floatval($_POST['monto_usd'] ?? 0),
            'monto_bs' => floatval($_POST['monto_bs'] ?? 0),
        ];
    }

    if ($proforma_id <= 0) {
        setFlash('error', 'Documento inválido para cobrar.');
        header('Location: /ELPROFE/proformas');
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Catálogo métodos para validar IDs y tipo de moneda permitido
        $stmtMetodos = $pdo->query('SELECT id, nombre FROM metodos_pago WHERE activo = 1');
        $mapMetodos = [];
        foreach ($stmtMetodos->fetchAll() as $m) {
            $mapMetodos[(int)$m['id']] = [
                'id' => (int)$m['id'],
                'nombre' => (string)$m['nombre'],
                'tipo' => tipoMetodoPago($m),
            ];
        }

        $pagosLimpios = [];
        $equiv_usd_pagado = 0.0;

        foreach ($pagos as $pg) {
            $metodoId = intval($pg['metodo_pago_id'] ?? $pg['id'] ?? 0);
            $m_usd = round(floatval($pg['monto_usd'] ?? 0), 2);
            $m_bs = round(floatval($pg['monto_bs'] ?? 0), 2);

            if ($metodoId <= 0 || (!isset($mapMetodos[$metodoId]))) {
                continue;
            }

            $tipoMetodo = $mapMetodos[$metodoId]['tipo'];

            // Regla: métodos USD solo aceptan USD; métodos Bs solo aceptan Bs.
            if ($tipoMetodo === 'USD') {
                $m_bs = 0.0;
            } else {
                $m_usd = 0.0;
            }

            if ($m_usd <= 0 && $m_bs <= 0) {
                continue;
            }

            $equiv = round($m_usd + ($m_bs / $tasa_hoy), 2);
            if ($equiv <= 0) {
                continue;
            }

            $equiv_usd_pagado += $equiv;
            $pagosLimpios[] = [
                'metodo_pago_id' => $metodoId,
                'monto_usd' => $m_usd,
                'monto_bs' => $m_bs,
                'equiv_usd' => $equiv,
            ];
        }

        if (count($pagosLimpios) === 0) {
            throw new Exception('Debe especificar al menos un monto válido para registrar el abono.');
        }

        // Extraer deuda actual con control por rol:
        // Admin puede abonar cualquier documento; cajero solo sus créditos.
        if ($esAdmin) {
            $stmt = $pdo->prepare('SELECT saldo_pendiente_usd FROM proformas WHERE id = ? FOR UPDATE');
            $stmt->execute([$proforma_id]);
        } else {
            $stmt = $pdo->prepare('SELECT saldo_pendiente_usd FROM proformas WHERE id = ? AND cajero_id = ? FOR UPDATE');
            $stmt->execute([$proforma_id, $_SESSION['user_id']]);
        }
        $prof = $stmt->fetch();

        if (!$prof) {
            throw new Exception('La nota de entrega no existe.');
        }
        if (floatval($prof['saldo_pendiente_usd']) <= 0) {
            throw new Exception('Este documento ya fue liquidado.');
        }

        $saldoActual = round(floatval($prof['saldo_pendiente_usd']), 2);
        $equiv_usd_pagado = round($equiv_usd_pagado, 2);

        if ($equiv_usd_pagado > ($saldoActual + 0.10)) {
            throw new Exception('El monto introducido supera la deuda actual. Registre el cambio por separado.');
        }

        $nuevo_saldo = round($saldoActual - $equiv_usd_pagado, 2);
        if ($nuevo_saldo < 0) {
            $nuevo_saldo = 0;
        }

        $nuevo_estado = ($nuevo_saldo === 0.00) ? 'PAGADO' : 'PARCIAL';

        // Crear abono padre
        $nota = 'Abono Cobranza (' . $modo_pago . ')';
        $stmtAbono = $pdo->prepare('INSERT INTO abonos (proforma_id, tasa_bs_usd, monto_total_usd, nota) VALUES (?, ?, ?, ?)');
        $stmtAbono->execute([$proforma_id, $tasa_hoy, $equiv_usd_pagado, $nota]);
        $abono_id = $pdo->lastInsertId();

        // Validar sesión de caja
        $stmtSesion = $pdo->prepare("SELECT id FROM sesiones_caja WHERE usuario_id = ? AND estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $stmtSesion->execute([$_SESSION['user_id']]);
        $ses_id = $stmtSesion->fetchColumn() ?: null;
        if (!$ses_id) {
            throw new Exception("No hay caja ABIERTA. Abra su caja en 'Mi Caja' antes de registrar abonos.");
        }

        $stmtPDetalle = $pdo->prepare('INSERT INTO pagos_detalles (abono_id, metodo_pago_id, monto_entregado_bs, monto_entregado_usd, monto_equivalente_usd) VALUES (?, ?, ?, ?, ?)');
        $stmtCaja = $pdo->prepare("INSERT INTO movimientos_caja (sesion_caja_id, metodo_pago_id, tipo_movimiento, monto_bs, monto_usd, referencia_id, referencia_tabla) VALUES (?, ?, 'ENTRADA', ?, ?, ?, 'abonos')");

        foreach ($pagosLimpios as $pg) {
            $stmtPDetalle->execute([$abono_id, $pg['metodo_pago_id'], $pg['monto_bs'], $pg['monto_usd'], $pg['equiv_usd']]);
            $stmtCaja->execute([$ses_id, $pg['metodo_pago_id'], $pg['monto_bs'], $pg['monto_usd'], $abono_id]);
        }

        // Actualizar documento
        $stmtUp = $pdo->prepare('UPDATE proformas SET saldo_pendiente_usd = ?, estado = ? WHERE id = ?');
        $stmtUp->execute([$nuevo_saldo, $nuevo_estado, $proforma_id]);

        $pdo->commit();
        setFlash('success', 'Abono registrado: $' . formatMoney($equiv_usd_pagado) . ' USD equivalentes.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', $e->getMessage());
    }

    header('Location: /ELPROFE/proformas');
    exit;
}

// Convertir Proforma PAGADA a FACTURA bajo solicitud explícita
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'emitir_factura') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $proforma_id = intval($_POST['proforma_id'] ?? 0);
    if ($proforma_id <= 0) {
        setFlash('error', 'Documento inválido.');
        header('Location: /ELPROFE/proformas');
        exit;
    }

    try {
        $pdo->beginTransaction();

        if ($esAdmin) {
            $stmt = $pdo->prepare("SELECT * FROM proformas WHERE id = ? FOR UPDATE");
            $stmt->execute([$proforma_id]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM proformas WHERE id = ? AND cajero_id = ? FOR UPDATE");
            $stmt->execute([$proforma_id, $_SESSION['user_id']]);
        }
        $prof = $stmt->fetch();
        if (!$prof) {
            throw new Exception('No existe la proforma solicitada.');
        }
        if ((string)$prof['estado'] !== 'PAGADO' || floatval($prof['saldo_pendiente_usd']) > 0) {
            throw new Exception('Solo puedes facturar una proforma completamente pagada.');
        }
        if ((string)$prof['tipo_documento'] === 'FACTURA') {
            throw new Exception('Este documento ya está facturado.');
        }

        $nextControl = (int)($pdo->query("SELECT COALESCE(MAX(CAST(numero_control AS UNSIGNED)), 1000) + 1 FROM proformas")->fetchColumn() ?: 1001);
        $facturaNum = 'FAC-' . date('Ymd') . '-' . str_pad((string)$proforma_id, 6, '0', STR_PAD_LEFT);

        $stmtUnique = $pdo->prepare("SELECT id FROM proformas WHERE factura_numero = ? LIMIT 1");
        $stmtUnique->execute([$facturaNum]);
        if ($stmtUnique->fetch()) {
            $facturaNum .= '-' . substr((string)time(), -4);
        }

        $stmtUp = $pdo->prepare("UPDATE proformas SET tipo_documento = 'FACTURA', factura_numero = ?, numero_control = ? WHERE id = ?");
        $stmtUp->execute([$facturaNum, (string)$nextControl, $proforma_id]);

        registrarAccion($pdo, 'POS', 'EMITIR_FACTURA', "Proforma #{$proforma_id} convertida a FACTURA {$facturaNum}");
        $pdo->commit();
        setFlash('success', "Factura emitida correctamente: {$facturaNum}");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        setFlash('error', $e->getMessage());
    }

    header('Location: /ELPROFE/proformas');
    exit;
}

$stmtMetodos = $pdo->query('SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY id ASC');
$metodosPago = $stmtMetodos->fetchAll();
$metodosUI = [];
foreach ($metodosPago as $mp) {
    $mp['tipo'] = tipoMetodoPago($mp);
    $metodosUI[] = [
        'id' => (int)$mp['id'],
        'nombre' => (string)$mp['nombre'],
        'tipo' => (string)$mp['tipo'],
    ];
}

$tasaActual = floatval(getConfig('tasa_usd_bs', $pdo));

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-file-invoice me-2"></i> Cobranza (Fiados)</h2>
    <div class="btn-group shadow-sm">
        <a href="/ELPROFE/proformas" class="btn <?php echo !isset($_GET['todas']) ? 'btn-primary' : 'btn-light text-primary border-primary'; ?> fw-bold px-4">
            <i class="fa-solid fa-clock-rotate-left me-1"></i> Pendientes de Pago
        </a>
        <a href="/ELPROFE/proformas?todas=1" class="btn <?php echo isset($_GET['todas']) ? 'btn-primary' : 'btn-light text-primary border-primary'; ?> fw-bold px-4">
            <i class="fa-solid fa-list-check me-1"></i> Todo el Historial
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0 mt-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 datatable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">ID / Referencia</th>
                        <th>Cliente</th>
                        <th>Fecha de Emisión</th>
                        <th class="text-center">Total USD</th>
                        <th class="text-center">Saldo Pendiente (USD)</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center pe-4"><i class="fa-solid fa-tools"></i> Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $filterCondition = "AND p.estado != 'PAGADO'";
                    if (isset($_GET['todas'])) $filterCondition = "";

                    if ($esAdmin) {
                        $stmt = $pdo->query("SELECT p.*, c.nombre, c.apellido, c.cedula_rif
                                             FROM proformas p
                                             JOIN clientes c ON p.cliente_id = c.id
                                             WHERE 1=1 $filterCondition
                                             ORDER BY CASE p.estado WHEN 'PENDIENTE' THEN 1 WHEN 'PARCIAL' THEN 2 ELSE 3 END, p.fecha_emision DESC
                                             LIMIT 300");
                        $proformas = $stmt->fetchAll();
                    } else {
                        $stmt = $pdo->prepare("SELECT p.*, c.nombre, c.apellido, c.cedula_rif
                                               FROM proformas p
                                               JOIN clientes c ON p.cliente_id = c.id
                                               WHERE p.cajero_id = ? $filterCondition
                                               ORDER BY CASE p.estado WHEN 'PENDIENTE' THEN 1 WHEN 'PARCIAL' THEN 2 ELSE 3 END, p.fecha_emision DESC
                                               LIMIT 300");
                        $stmt->execute([$_SESSION['user_id']]);
                        $proformas = $stmt->fetchAll();
                    }

                    if (count($proformas) > 0) {
                        foreach ($proformas as $p) {
                            $badge = match($p['estado']) {
                                'PAGADO' => 'bg-success',
                                'PARCIAL' => 'bg-primary',
                                'PENDIENTE' => 'bg-warning text-dark',
                                'ANULADO' => 'bg-danger',
                                default => 'bg-secondary'
                            };

                            $saldo = floatval($p['saldo_pendiente_usd']);
                            echo "<tr>
                                    <td class='ps-4 fw-bold'># " . str_pad($p['id'], 6, '0', STR_PAD_LEFT) . "</td>
                                    <td><span class='fw-bold'>" . e($p['nombre'] . ' ' . $p['apellido']) . "</span><br><small class='text-muted'>" . e($p['cedula_rif']) . "</small></td>
                                    <td class='text-muted'>" . e($p['fecha_emision']) . "</td>
                                    <td class='text-center'>$" . formatMoney($p['total_usd']) . "</td>
                                    <td class='text-center text-danger fw-bolder fs-5'>$" . formatMoney($saldo) . "</td>
                                    <td class='text-center'><span class='badge {$badge} px-3 py-2'>" . e($p['estado']) . "</span></td>
                                    <td class='text-center pe-4'>";
                            if ($saldo > 0 && $p['estado'] !== 'ANULADO') {
                                echo "<button class='btn btn-sm btn-success fw-bold shadow-sm rounded-pill btn-abonar'
                                            data-id='" . e($p['id']) . "' data-saldo='" . e($saldo) . "'>
                                        <i class='fa-solid fa-hand-holding-dollar'></i> Recibir Abono
                                      </button>";
                            } else {
                                if ((string)$p['estado'] === 'PAGADO' && (string)$p['tipo_documento'] === 'PROFORMA') {
                                    echo "<form method='POST' action='/ELPROFE/proformas' class='d-inline'>
                                            <input type='hidden' name='action' value='emitir_factura'>
                                            <input type='hidden' name='proforma_id' value='" . e((string)$p['id']) . "'>
                                            " . csrfField() . "
                                            <button type='submit' class='btn btn-sm btn-warning fw-bold shadow-sm rounded-pill' onclick=\"return confirm('¿Emitir factura legal para la proforma #" . e((string)$p['id']) . "?');\">
                                                <i class='fa-solid fa-file-invoice-dollar'></i> Emitir Factura
                                            </button>
                                          </form>";
                                } else {
                                    $docTxt = ((string)$p['tipo_documento'] === 'FACTURA') ? 'Facturada' : 'Liquidada';
                                    echo "<span class='text-muted small'><i class='fa-solid fa-check-double'></i> {$docTxt}</span>";
                                }
                            }
                            echo "</td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center text-muted py-5'><i class='fa-solid fa-file-excel fa-3x mb-3 text-light'></i><h5 class='mb-0'>No hay notas de entrega registradas</h5></td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Abono Inteligente -->
<div class="modal fade" id="modalAbono" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-hand-holding-dollar"></i> Recibir Pago / Abono Parcial</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="/ELPROFE/proformas" id="form-abono">
          <div class="modal-body p-4">
            <input type="hidden" name="action" value="registrar_abono">
            <input type="hidden" name="proforma_id" id="modal-abono-id">
            <input type="hidden" name="pagos_json" id="pagos-json">
            <input type="hidden" name="metodo_pago_id" id="legacy-metodo-id" value="0">
            <input type="hidden" name="monto_usd" id="legacy-monto-usd" value="0">
            <input type="hidden" name="monto_bs" id="legacy-monto-bs" value="0">
            <?php echo csrfField(); ?>

            <div class="text-center mb-4 border-bottom pb-3">
                <h6 class="text-muted">Deuda Pendiente (Fiado)</h6>
                <h1 class="text-danger fw-bolder mb-1" id="modal-abono-saldo-usd">$0.00</h1>
                <div class="fw-bold text-secondary" id="modal-abono-saldo-bs">Bs. 0.00</div>
                <small class="text-primary fw-bold"><i class="fa-solid fa-bolt"></i> Tasa actual de hoy: <?php echo number_format($tasaActual, 4, '.', ''); ?> Bs/$</small>
            </div>

            <div class="mb-3">
                <label class="form-label text-muted small">Modo de Pago</label>
                <select name="modo_pago" id="modo-pago" class="form-select border-2 border-primary" required>
                    <option value="USD">Solo Dólares (USD)</option>
                    <option value="BS">Solo Bolívares (Bs)</option>
                    <option value="MIXTO" selected>Mixto (USD + Bs / múltiples métodos)</option>
                </select>
            </div>

            <div id="abono-panel-usd" class="d-none">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Método USD</label>
                        <select class="form-select" id="abono-usd-metodo"></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Monto recibido en USD</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" min="0" step="0.01" id="abono-usd-monto" class="form-control" placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>

            <div id="abono-panel-bs" class="d-none">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Método en Bs</label>
                        <select class="form-select" id="abono-bs-metodo"></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Monto recibido en Bs</label>
                        <div class="input-group">
                            <span class="input-group-text">Bs</span>
                            <input type="number" min="0" step="0.01" id="abono-bs-monto" class="form-control" placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>

            <div id="abono-panel-mixto">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label text-muted small mb-0">Distribución de pago mixto</label>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-linea-mixto">
                        <i class="fa-solid fa-plus"></i> Agregar método
                    </button>
                </div>
                <div id="abono-mixto-lista" class="d-grid gap-2"></div>
            </div>

            <div class="alert alert-info mt-3 mb-0 py-2">
                <div class="small fw-semibold">Total abonado equivalente USD: <span id="abono-total-eq-usd">$0.00</span></div>
                <div class="small">Resta por cobrar: <span id="abono-resta-usd" class="fw-bold text-danger">$0.00</span></div>
            </div>
          </div>
          <div class="modal-footer pb-3">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm" id="btn-procesar-abono"><i class="fa-solid fa-paper-plane"></i> Registrar Abono</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
(function() {
    const tasa = <?php echo json_encode($tasaActual); ?>;
    const metodos = <?php echo json_encode($metodosUI, JSON_UNESCAPED_UNICODE); ?>;
    const metodosUsd = metodos.filter((m) => m.tipo === 'USD');
    const metodosBs = metodos.filter((m) => m.tipo === 'BS');

    let saldoUsdActual = 0;

    function fmt(n) {
        const num = Number(n || 0);
        return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function setOptions(selectEl, lista) {
        selectEl.innerHTML = '';
        lista.forEach((m) => {
            const opt = document.createElement('option');
            opt.value = String(m.id);
            opt.textContent = m.nombre;
            selectEl.appendChild(opt);
        });
    }

    function crearLineaMixta(defaultMetodoId = null) {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end abono-linea-mixta';

        const c1 = document.createElement('div');
        c1.className = 'col-md-5';
        const c2 = document.createElement('div');
        c2.className = 'col-md-5';
        const c3 = document.createElement('div');
        c3.className = 'col-md-2 d-grid';

        c1.innerHTML = '<label class="form-label text-muted small">Método</label>';
        const sel = document.createElement('select');
        sel.className = 'form-select linea-metodo';
        metodos.forEach((m) => {
            const opt = document.createElement('option');
            opt.value = String(m.id);
            opt.textContent = `${m.nombre} (${m.tipo})`;
            if (defaultMetodoId && Number(defaultMetodoId) === Number(m.id)) {
                opt.selected = true;
            }
            sel.appendChild(opt);
        });

        c2.innerHTML = '<label class="form-label text-muted small">Monto</label>';
        const group = document.createElement('div');
        group.className = 'input-group';
        const prefix = document.createElement('span');
        prefix.className = 'input-group-text linea-prefijo';
        const inp = document.createElement('input');
        inp.type = 'number';
        inp.step = '0.01';
        inp.min = '0';
        inp.placeholder = '0.00';
        inp.className = 'form-control linea-monto';
        group.appendChild(prefix);
        group.appendChild(inp);

        const btnRm = document.createElement('button');
        btnRm.type = 'button';
        btnRm.className = 'btn btn-outline-danger';
        btnRm.innerHTML = '<i class="fa-solid fa-trash"></i>';

        c1.appendChild(sel);
        c2.appendChild(group);
        c3.appendChild(btnRm);

        row.appendChild(c1);
        row.appendChild(c2);
        row.appendChild(c3);

        function syncByMethod() {
            const methodId = Number(sel.value || 0);
            const m = metodos.find((x) => x.id === methodId);
            const tipo = (m && m.tipo) ? m.tipo : 'BS';
            prefix.textContent = tipo === 'USD' ? '$' : 'Bs';
            inp.dataset.tipo = tipo;
        }

        sel.addEventListener('change', () => {
            syncByMethod();
            recalcularResumen();
        });
        inp.addEventListener('input', recalcularResumen);
        btnRm.addEventListener('click', () => {
            row.remove();
            recalcularResumen();
        });

        syncByMethod();
        return row;
    }

    function pagosDesdeUI() {
        const modo = document.getElementById('modo-pago').value;
        const pagos = [];

        if (modo === 'USD') {
            const metodo = Number(document.getElementById('abono-usd-metodo').value || 0);
            const monto = Number(document.getElementById('abono-usd-monto').value || 0);
            if (metodo > 0 && monto > 0) {
                pagos.push({ metodo_pago_id: metodo, monto_usd: monto, monto_bs: 0 });
            }
            return pagos;
        }

        if (modo === 'BS') {
            const metodo = Number(document.getElementById('abono-bs-metodo').value || 0);
            const monto = Number(document.getElementById('abono-bs-monto').value || 0);
            if (metodo > 0 && monto > 0) {
                pagos.push({ metodo_pago_id: metodo, monto_usd: 0, monto_bs: monto });
            }
            return pagos;
        }

        document.querySelectorAll('.abono-linea-mixta').forEach((row) => {
            const metodo = Number(row.querySelector('.linea-metodo')?.value || 0);
            const monto = Number(row.querySelector('.linea-monto')?.value || 0);
            const tipo = String(row.querySelector('.linea-monto')?.dataset.tipo || 'BS');
            if (metodo > 0 && monto > 0) {
                pagos.push({
                    metodo_pago_id: metodo,
                    monto_usd: tipo === 'USD' ? monto : 0,
                    monto_bs: tipo === 'BS' ? monto : 0,
                });
            }
        });

        return pagos;
    }

    function recalcularResumen() {
        const pagos = pagosDesdeUI();
        let totalEq = 0;
        pagos.forEach((pg) => {
            totalEq += Number(pg.monto_usd || 0) + (Number(pg.monto_bs || 0) / tasa);
        });

        totalEq = Number(totalEq.toFixed(2));
        let resta = Number((saldoUsdActual - totalEq).toFixed(2));
        if (resta < 0) resta = 0;

        const totalEl = document.getElementById('abono-total-eq-usd');
        const restaEl = document.getElementById('abono-resta-usd');
        const btn = document.getElementById('btn-procesar-abono');

        totalEl.textContent = '$' + fmt(totalEq);
        restaEl.textContent = '$' + fmt(resta);
        restaEl.classList.toggle('text-success', resta <= 0.10);
        restaEl.classList.toggle('text-danger', resta > 0.10);

        btn.disabled = pagos.length === 0 || totalEq <= 0;
    }

    function renderModo() {
        const modo = document.getElementById('modo-pago').value;
        document.getElementById('abono-panel-usd').classList.toggle('d-none', modo !== 'USD');
        document.getElementById('abono-panel-bs').classList.toggle('d-none', modo !== 'BS');
        document.getElementById('abono-panel-mixto').classList.toggle('d-none', modo !== 'MIXTO');
        recalcularResumen();
    }

    function initModalFor(saldoUsd, proformaId) {
        saldoUsdActual = Number(saldoUsd || 0);

        document.getElementById('modal-abono-id').value = String(proformaId || 0);
        document.getElementById('modal-abono-saldo-usd').textContent = '$' + fmt(saldoUsdActual);
        document.getElementById('modal-abono-saldo-bs').textContent = 'Bs. ' + fmt(saldoUsdActual * tasa);

        document.getElementById('modo-pago').value = 'MIXTO';
        document.getElementById('abono-usd-monto').value = '';
        document.getElementById('abono-bs-monto').value = '';

        const lista = document.getElementById('abono-mixto-lista');
        lista.innerHTML = '';
        if (metodos.length > 0) {
            lista.appendChild(crearLineaMixta(metodos[0].id));
        }

        renderModo();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const usdSel = document.getElementById('abono-usd-metodo');
        const bsSel = document.getElementById('abono-bs-metodo');
        setOptions(usdSel, metodosUsd.length > 0 ? metodosUsd : metodos);
        setOptions(bsSel, metodosBs.length > 0 ? metodosBs : metodos);

        $(document).on('click', '.btn-abonar', function() {
            const id = Number($(this).data('id') || 0);
            const saldo = Number($(this).data('saldo') || 0);
            initModalFor(saldo, id);
            new bootstrap.Modal(document.getElementById('modalAbono')).show();
        });

        document.getElementById('modo-pago').addEventListener('change', renderModo);
        document.getElementById('abono-usd-monto').addEventListener('input', recalcularResumen);
        document.getElementById('abono-bs-monto').addEventListener('input', recalcularResumen);
        document.getElementById('abono-usd-metodo').addEventListener('change', recalcularResumen);
        document.getElementById('abono-bs-metodo').addEventListener('change', recalcularResumen);

        document.getElementById('btn-add-linea-mixto').addEventListener('click', () => {
            const lista = document.getElementById('abono-mixto-lista');
            lista.appendChild(crearLineaMixta());
            recalcularResumen();
        });

        document.getElementById('form-abono').addEventListener('submit', function(e) {
            const pagos = pagosDesdeUI();
            if (pagos.length === 0) {
                e.preventDefault();
                Swal.fire('Atención', 'Debe ingresar al menos un monto de abono.', 'warning');
                return;
            }

            const json = JSON.stringify(pagos);
            document.getElementById('pagos-json').value = json;

            // Compatibilidad backend legado
            const p0 = pagos[0];
            document.getElementById('legacy-metodo-id').value = String(p0.metodo_pago_id || 0);
            document.getElementById('legacy-monto-usd').value = String(p0.monto_usd || 0);
            document.getElementById('legacy-monto-bs').value = String(p0.monto_bs || 0);
        });
    });
})();
</script>

<?php require_once '../includes/footer.php'; ?>
