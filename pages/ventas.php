<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
// Regla POS: no se puede vender sin caja abierta.
requireCajaAbierta($pdo);

function tipoMetodoPagoVenta(array $metodo): string {
    $nombre = strtolower(trim((string)($metodo['nombre'] ?? '')));
    $esUsd = str_contains($nombre, 'usd') || str_contains($nombre, 'dolar') || str_contains($nombre, 'dólar') || str_contains($nombre, 'usdt') || str_contains($nombre, 'binance');
    return $esUsd ? 'USD' : 'BS';
}

// Fetch metodos for the Modal
$stmtMetodos = $pdo->query("SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY id ASC");
$metodosPago = $stmtMetodos->fetchAll();

// Historial de proformas para el POS (se muestra en el modal)
$stmtProfs = $pdo->prepare("
    SELECT 
        p.id,
        p.fecha_emision,
        p.tipo_documento,
        p.total_usd,
        p.saldo_pendiente_usd,
        p.estado,
        c.nombre AS cliente_nombre,
        u.nombre AS vendedor_nombre
    FROM proformas p
    JOIN clientes c ON p.cliente_id = c.id
    JOIN usuarios u ON p.cajero_id = u.id
    ORDER BY p.id DESC
    LIMIT 200
");
$stmtProfs->execute();
$proformasHist = $stmtProfs->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary elprofe-panel-title"><i class="fa-solid fa-cart-shopping me-2"></i> Punto de Venta</h2>
    <div>
        <button type="button" class="btn btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#modalHistorialProformas">
            <i class="fa-solid fa-clock-rotate-left"></i> Historial (Proformas)
        </button>
    </div>
</div>

<div id="ventas-stock-alert" class="alert alert-warning d-none py-2 mb-3"></div>

<div class="row g-4">
    <!-- Panel Izquierdo: Selección de Productos -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100 elprofe-soft-card">
            <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0 px-4 position-relative">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-light border-0"><i class="fa-solid fa-barcode text-muted"></i></span>
                    <input type="text" id="buscador-producto" class="form-control bg-light border-0" placeholder="Escanear código o buscar por nombre (F2)" autofocus autocomplete="off">
                    <button type="button" class="btn btn-outline-primary" id="btn-catalogo-productos">
                        <i class="fa-solid fa-table-list me-1"></i> Productos
                    </button>
                </div>
                <!-- Lista de resultados sugeridos (hidden por defecto) -->
                <ul id="resultado-busqueda" class="list-group position-absolute shadow-sm" style="z-index: 1040; display: none; max-height: 300px; overflow-y: auto; left: 1.5rem; right: 1.5rem; top: calc(100% - 0.2rem);"></ul>
            </div>
            
            <div class="card-body p-4 p-0 mt-3">
                <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="tabla-venta">
                        <thead class="sticky-top bg-light">
                            <tr>
                                <th width="5%" class="text-center">#</th>
                                <th width="40%">Descripción</th>
                                <th width="20%" class="text-center">Cant.</th>
                                <th width="15%" class="text-end">Precio USD</th>
                                <th width="15%" class="text-end">Subtotal USD</th>
                                <th width="5%" class="text-center"><i class="fa-solid fa-trash"></i></th>
                            </tr>
                        </thead>
                        <tbody id="lista-productos">
                            <!-- Productos añadidos aparecerán acá dinámicamente -->
                            <tr id="fila-vacia">
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fa-solid fa-basket-shopping fa-3x mb-3 text-light"></i>
                                    <h5>La proforma está vacía</h5>
                                    <p class="mb-0">Busca un producto para empezar</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Panel Derecho: Resumen y Cliente -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4 bg-primary text-white" style="background: linear-gradient(135deg, var(--primary-color) 0%, #3b82f6 100%) !important;">
            <div class="card-body p-4 text-center">
                <h5 class="card-title fw-bold text-white-50">Total a Pagar (USD)</h5>
                <div class="pos-total text-white mb-2" id="gran-total-usd">$0.00</div>
                <div class="fs-5 text-white-50">Bs: <span id="gran-total-bs">0.00</span></div>
            </div>
        </div>
        <span id="tasa-actual" class="d-none"><?php echo floatval(getConfig('tasa_usd_bs', $pdo)); ?></span>
        
        <div class="card shadow-sm border-0 elprofe-soft-card">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">Datos del Cliente</h5>
                <div class="input-group mb-3">
                    <input type="text" class="form-control bg-light border-0" id="cliente-cedula" placeholder="Cédula / RIF" value="V-00000000">
                    <button class="btn btn-outline-secondary" type="button"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>
                <input type="text" class="form-control bg-light border-0 mb-4" id="cliente-nombre" placeholder="Nombre (o Consumidor Final)" value="Consumidor Final">
                
                <hr class="text-muted">
                
                <h5 class="fw-bold mb-3">Acciones</h5>
                <div class="d-grid gap-2">
                    <button class="btn btn-success btn-lg py-3 shadow-sm fw-bold w-100 mt-2 fs-4" id="btn-cobrar-pre" data-bs-toggle="modal" data-bs-target="#modalPago">
                        <i class="fa-solid fa-money-bill-wave me-2"></i> Procesar Pago (F9)
                    </button>
                    
                    <div class="row g-2 mt-1">
                        <div class="col-6">
                            <button class="btn btn-outline-warning w-100 py-2 h-100" id="btn-fiado">
                                <i class="fa-solid fa-handshake-angle d-block mb-1"></i> Crédito
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-info w-100 py-2 h-100" id="btn-hold">
                                <i class="fa-solid fa-pause d-block mb-1"></i> En Espera
                            </button>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary py-2 w-100 mt-1" id="btn-restore-hold">
                        <i class="fa-solid fa-clock-rotate-left me-2"></i> Recuperar Ticket <span id="hold-count" class="badge bg-danger ms-2">0</span>
                    </button>
                    
                    <button class="btn btn-danger py-2 w-100 mt-1" id="btn-anular">
                        <i class="fa-solid fa-ban me-2"></i> Limpiar Carrito
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pago -->
<div class="modal fade" id="modalPago" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h4 class="modal-title fw-bold"><i class="fa-solid fa-money-bill-transfer"></i> Procesar Pago</h4>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
          <div class="row align-items-center mb-4">
              <div class="col-md-4 text-center border-end">
                  <h5 class="text-muted mb-1">Monto a Cobrar</h5>
                  <h1 class="text-primary fw-bolder mb-0" id="modal-pagar-usd">$0.00</h1>
              </div>
              <div class="col-md-4 text-center border-end">
                  <h5 class="text-muted mb-1">Monto a Cobrar (Bs)</h5>
                  <h1 class="text-info fw-bolder mb-0" id="modal-pagar-bs">Bs 0.00</h1>
              </div>
              <div class="col-md-4 text-center">
                  <h5 class="text-muted mb-1">Resta por Cobrar</h5>
                  <h1 class="text-danger fw-bolder mb-0" id="modal-resta-usd">$0.00</h1>
                  <div class="small text-muted" id="modal-resta-bs">Bs 0.00</div>
              </div>
          </div>
          <div class="alert alert-info py-2 small"><i class="fa-solid fa-circle-info"></i> Tasa de Cálculo Actual: <strong id="modal-tasa-actual"><?php echo floatval(getConfig('tasa_usd_bs', $pdo)); ?></strong> Bs/$</div>

          <div class="mb-3 px-1">
              <label class="form-label fw-bold text-muted mb-1"><i class="fa-solid fa-wallet"></i> Modalidad de Cobro</label>
              <select id="modal-payment-mode" class="form-select form-select-lg border-secondary shadow-sm">
                  <option value="">Seleccione método de cobro...</option>
                  <option value="USD">Solo Dólar (USD)</option>
                  <option value="BS">Solo Bolívares (Bs)</option>
                  <option value="MIXTO">Pago Mixto</option>
              </select>
          </div>
          
          <div class="mb-3 px-1">
              <label class="form-label fw-bold text-muted mb-1"><i class="fa-solid fa-file-invoice"></i> Tipo de Documento SENIAT</label>
              <select id="modal-tipo-doc" class="form-select form-select-lg border-secondary shadow-sm">
                  <option value="FACTURA">🧾 Factura Fiscal (Exige Correlativo)</option>
                  <option value="PROFORMA" selected>📄 Nota de Entrega / Control Interno</option>
              </select>
          </div>

          <div id="panel-pago-usd" class="d-none">
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label small text-muted">Método USD</label>
                <select id="single-usd-metodo" class="form-select">
                  <?php foreach($metodosPago as $mp): ?>
                    <?php if (tipoMetodoPagoVenta($mp) === 'USD'): ?>
                      <option value="<?php echo (int)$mp['id']; ?>"><?php echo e($mp['nombre']); ?></option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Monto recibido (USD)</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="number" step="0.01" min="0" class="form-control text-end" id="single-usd-monto" placeholder="0.00">
                </div>
              </div>
            </div>
          </div>

          <div id="panel-pago-bs" class="d-none">
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label small text-muted">Método Bs</label>
                <select id="single-bs-metodo" class="form-select">
                  <?php foreach($metodosPago as $mp): ?>
                    <?php if (tipoMetodoPagoVenta($mp) === 'BS'): ?>
                      <option value="<?php echo (int)$mp['id']; ?>"><?php echo e($mp['nombre']); ?></option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Monto recibido (Bs)</label>
                <div class="input-group">
                  <span class="input-group-text">Bs</span>
                  <input type="number" step="0.01" min="0" class="form-control text-end" id="single-bs-monto" placeholder="0.00">
                </div>
              </div>
            </div>
          </div>

          <div id="panel-pago-mixto" class="d-none">
            <table class="table table-bordered align-middle mt-3">
                <thead class="bg-light">
                    <tr>
                        <th width="40%">Método de Pago</th>
                        <th width="30%" class="text-center">Monto USD</th>
                        <th width="30%" class="text-center">Monto Bs</th>
                    </tr>
                </thead>
                <tbody id="lista-metodos-pago">
                    <?php foreach($metodosPago as $mp): ?>
                    <?php $tipoMetodo = tipoMetodoPagoVenta($mp); ?>
                    <tr class="metodo-row" data-id="<?php echo $mp['id']; ?>" data-tipo="<?php echo $tipoMetodo; ?>">
                        <td class="fw-bold">
                          <i class="fa-solid fa-wallet text-muted me-2"></i> <?php echo e($mp['nombre']); ?>
                          <span class="badge ms-2 <?php echo $tipoMetodo === 'USD' ? 'bg-primary' : 'bg-secondary'; ?>"><?php echo $tipoMetodo; ?></span>
                        </td>
                        <td>
                            <div class="input-group">
                                <span class="input-group-text bg-light">$</span>
                                <input type="number" step="0.01" min="0" class="form-control text-end input-monto-usd bg-white" placeholder="0.00">
                            </div>
                        </td>
                        <td>
                            <div class="input-group">
                                <span class="input-group-text bg-light">Bs</span>
                                <input type="number" step="0.01" min="0" class="form-control text-end input-monto-bs bg-white" placeholder="0.00">
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
          </div>
      </div>
      <div class="modal-footer pb-3 px-4">
        <button type="button" class="btn btn-light fs-5" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success fs-5 px-5 fw-bold shadow-sm" id="btn-procesar-mixto" disabled><i class="fa-solid fa-check"></i> Emitir Proforma</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal catálogo productos -->
<div class="modal fade" id="modalCatalogoProductos" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-box-open me-2"></i> Catálogo de Productos</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="input-group mb-3">
          <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
          <input type="text" class="form-control" id="catalogo-buscar" placeholder="Buscar por nombre, código interno o barras">
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
              <tr>
                <th>Producto</th>
                <th>Cód. Interno</th>
                <th>Barras</th>
                <th class="text-end">Precio USD</th>
                <th class="text-center">Disponible</th>
                <th class="text-center">Acción</th>
              </tr>
            </thead>
            <tbody id="catalogo-body">
              <tr><td colspan="6" class="text-center text-muted py-4">Cargando catálogo...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="/ELPROFE/assets/js/pos.js"></script>

<!-- Modal: Historial de Proformas -->
<div class="modal fade" id="modalHistorialProformas" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-receipt me-2"></i> Historial de Proformas</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 datatable">
            <thead class="bg-light">
              <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Cliente</th>
                <th class="text-end">Total (USD)</th>
                <th class="text-end">Saldo (USD)</th>
                <th>Estado</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($proformasHist) > 0): ?>
                <?php foreach ($proformasHist as $p): ?>
                  <?php
                    $estadoBadge = 'bg-secondary';
                    if ($p['estado'] === 'PAGADO') $estadoBadge = 'bg-success';
                    if ($p['estado'] === 'PARCIAL') $estadoBadge = 'bg-warning text-dark';
                    if ($p['estado'] === 'PENDIENTE') $estadoBadge = 'bg-danger';
                  ?>
                  <tr>
                    <td class="fw-bold">#<?php echo (int)$p['id']; ?></td>
                    <td><?php echo e(date('d/m/Y H:i', strtotime($p['fecha_emision']))); ?></td>
                    <td><span class="badge bg-dark"><?php echo e($p['tipo_documento']); ?></span></td>
                    <td><?php echo e($p['cliente_nombre']); ?></td>
                    <td class="text-end text-success fw-bold">$<?php echo formatMoney((float)$p['total_usd']); ?></td>
                    <td class="text-end">$<?php echo formatMoney((float)$p['saldo_pendiente_usd']); ?></td>
                    <td><span class="badge <?php echo $estadoBadge; ?>"><?php echo e($p['estado']); ?></span></td>
                    <td class="text-center">
                      <a class="btn btn-sm btn-primary" target="_blank" href="/ELPROFE/pages/ticket.php?id=<?php echo (int)$p['id']; ?>">
                        <i class="fa-solid fa-receipt"></i>
                      </a>
                      <a class="btn btn-sm btn-warning text-dark ms-1" target="_blank" href="/ELPROFE/pages/nota_entrega.php?id=<?php echo (int)$p['id']; ?>">
                        <i class="fa-solid fa-file-pdf"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="8" class="text-center text-muted py-5">
                    <i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i>
                    Aún no hay proformas registradas.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <span class="text-muted small">
          Mostrando últimos <strong><?php echo count($proformasHist); ?></strong> registros.
        </span>
      </div>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
