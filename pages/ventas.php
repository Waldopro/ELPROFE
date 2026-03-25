<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();

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
                <div class="d-grid gap-3">
                    <button class="btn btn-success btn-lg py-3 shadow-sm fw-bold" id="btn-cobrar">
                        <i class="fa-solid fa-money-bill-wave me-2"></i> Procesar Pago (F9)
                    </button>
                    <button class="btn btn-outline-warning py-2" id="btn-fiado">
                        <i class="fa-solid fa-handshake-angle me-2"></i> Emitir a Crédito (Fiado)
                    </button>
                    <button class="btn btn-danger py-2" id="btn-anular">
                        <i class="fa-solid fa-ban me-2"></i> Anular
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // JS placeholder para la UI de ventas. En producción real este código debe
    // ir idealmente en main.js o en un archivo ventas.js.
    $(document).ready(function() {
        // En una implementación completa:
        // 1. input listener en 'buscador-producto' -> AJAX request -> autocompletar
        // 2. Al seleccionar -> añadir fila a 'lista-productos', actualizar totales
        // 3. Totales actualizan los divs 'gran-total-usd' y 'gran-total-bs' multiplicando por la tasa
    });
</script>

<?php require_once '../includes/footer.php'; ?>
