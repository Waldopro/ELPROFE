<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();

// Fetch metodos for the Modal
$stmtMetodos = $pdo->query("SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY id ASC");
$metodosPago = $stmtMetodos->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-cart-shopping me-2"></i> Punto de Venta</h2>
    <div>
        <button class="btn btn-outline-secondary me-2"><i class="fa-solid fa-clock-rotate-left"></i> Historial (Proformas)</button>
    </div>
</div>

<div class="row g-4">
    <!-- Panel Izquierdo: Selección de Productos -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0 px-4">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-light border-0"><i class="fa-solid fa-barcode text-muted"></i></span>
                    <input type="text" id="buscador-producto" class="form-control bg-light border-0" placeholder="Escanear código o buscar por nombre (F2)" autofocus autocomplete="off">
                </div>
                <!-- Lista de resultados sugeridos (hidden por defecto) -->
                <ul id="resultado-busqueda" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1000; display: none; max-height: 300px; overflow-y: auto;"></ul>
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
        
        <div class="card shadow-sm border-0">
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

<!-- Modal Pago Mixto -->
<div class="modal fade" id="modalPago" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h4 class="modal-title fw-bold"><i class="fa-solid fa-money-bill-transfer"></i> Procesar Pago Multiple</h4>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
          <div class="row align-items-center mb-4">
              <div class="col-md-6 text-center border-end">
                  <h5 class="text-muted mb-1">Monto a Cobrar</h5>
                  <h1 class="text-primary fw-bolder mb-0" id="modal-pagar-usd">$0.00</h1>
              </div>
              <div class="col-md-6 text-center">
                  <h5 class="text-muted mb-1">Resta por Cobrar</h5>
                  <h1 class="text-danger fw-bolder mb-0" id="modal-resta-usd">$0.00</h1>
              </div>
          </div>
          <div class="alert alert-info py-2 small"><i class="fa-solid fa-circle-info"></i> Tasa de Cálculo Actual: <strong id="modal-tasa-actual"><?php echo floatval(getConfig('tasa_usd_bs', $pdo)); ?></strong> Bs/$</div>
          
          <div class="mb-3 px-1">
              <label class="form-label fw-bold text-muted mb-1"><i class="fa-solid fa-file-invoice"></i> Tipo de Documento SENIAT</label>
              <select id="modal-tipo-doc" class="form-select form-select-lg border-secondary shadow-sm">
                  <option value="FACTURA">🧾 Factura Fiscal (Exige Correlativo)</option>
                  <option value="PROFORMA" selected>📄 Nota de Entrega / Control Interno</option>
              </select>
          </div>
          
          <table class="table table-bordered align-middle mt-3">
              <thead class="bg-light">
                  <tr>
                      <th width="40%">Método de Pago</th>
                      <th width="30%" class="text-center">Monto (USD)</th>
                      <th width="30%" class="text-center">Monto (Bs)</th>
                  </tr>
              </thead>
              <tbody id="lista-metodos-pago">
                  <?php foreach($metodosPago as $mp): ?>
                  <tr class="metodo-row" data-id="<?php echo $mp['id']; ?>">
                      <td class="fw-bold"><i class="fa-solid fa-wallet text-muted me-2"></i> <?php echo e($mp['nombre']); ?></td>
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
      <div class="modal-footer pb-3 px-4">
        <button type="button" class="btn btn-light fs-5" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success fs-5 px-5 fw-bold shadow-sm" id="btn-procesar-mixto" disabled><i class="fa-solid fa-check"></i> Emitir Proforma</button>
      </div>
    </div>
  </div>
</div>

<script src="/ELPROFE/assets/js/pos.js"></script>

<?php require_once '../includes/footer.php'; ?>
