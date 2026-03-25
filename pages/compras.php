<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
restrictAdmin();

// Procesar Formulario de Compra de Mercancía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_compra') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $proveedor_id = intval($_POST['proveedor_id'] ?? 0);
    $factura = trim($_POST['factura'] ?? '');
    $productos_json = $_POST['compra_data'] ?? '[]';
    $productos = json_decode($productos_json, true);
    
    if ($proveedor_id === 0 || empty($productos) || !is_array($productos)) {
        setFlash('error', 'Error: Datos de la compra incompletos.');
        header("Location: /compras");
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $tasa = floatval(getConfig('tasa_usd_bs', $pdo));
        $total_usd = 0;
        
        // Calcular total USD de la compra
        foreach ($productos as $p) {
            $total_usd += floatval($p['costo_total_usd']);
        }
        $total_bs = $total_usd * $tasa;
        
        // Crear Compra
        $stmt = $pdo->prepare("INSERT INTO compras (proveedor_id, factura_numero, tasa_bs_usd, total_usd, total_bs, estado) VALUES (?, ?, ?, ?, ?, 'PROCESADA')");
        $stmt->execute([$proveedor_id, $factura, $tasa, $total_usd, $total_bs]);
        $compra_id = $pdo->lastInsertId();
        
        // Insertar Detalles (El Trigger `trg_compra_detalle_insert` SUMARÁ el stock y calculará promedio)
        $stmtDetalle = $pdo->prepare("INSERT INTO compra_detalles (compra_id, presentacion_id, cantidad, costo_unitario_usd, costo_total_usd) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($productos as $p) {
            $stmtDetalle->execute([$compra_id, $p['presentacion_id'], floatval($p['cantidad']), floatval($p['costo_unitario_usd']), floatval($p['costo_total_usd'])]);
        }
        
        $pdo->commit();
        setFlash('success', '¡Compra procesada! El inventario ha sido actualizado usando motores Triggers de la DB.');
        
    } catch(Exception $e) {
        $pdo->rollBack();
        setFlash('error', 'Error crítico procesando compra: ' . $e->getMessage());
    }
    
    header("Location: /compras");
    exit;
}

require_once '../includes/header.php';

// Obtener Proveedores y Catálogo para el JS
$provs = $pdo->query("SELECT id, nombre, rif FROM proveedores ORDER BY nombre")->fetchAll();
$prods = $pdo->query("SELECT pr.id, pr.codigo_barras, CONCAT(p.nombre, ' [', pr.nombre_presentacion, ']') as label 
                      FROM presentaciones pr JOIN productos p ON pr.producto_id = p.id ORDER BY p.nombre")->fetchAll();
?>

<div class="row gx-4 mb-4">
    <!-- Panel Izquierdo: Formulario de Nueva Compra -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0 px-4">
                <h5 class="fw-bold text-primary"><i class="fa-solid fa-file-invoice-dollar me-2"></i> Ingreso de Mercancía</h5>
            </div>
            
            <form id="form-compras" method="POST" action="/compras" class="card-body p-4 pt-3">
                <input type="hidden" name="action" value="guardar_compra">
                <?php echo csrfField(); ?>
                <input type="hidden" name="compra_data" id="compra_data" value="[]">
                
                <div class="mb-3">
                    <label class="form-label text-muted small">Proveedor *</label>
                    <select name="proveedor_id" class="form-select" required>
                        <option value="">-- Seleccione Proveedor --</option>
                        <?php foreach($provs as $pr): ?>
                            <option value="<?php echo $pr['id']; ?>"><?php echo e($pr['nombre'] . ' (' . $pr['rif'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted small">N° Factura Física (Opcional)</label>
                    <input type="text" name="factura" class="form-control" placeholder="Ej: 0001-A">
                </div>
                
                <div class="alert alert-warning py-2 mb-4" style="font-size: 0.85rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <strong>Importante:</strong> Al guardar, las cantidades se inyectarán irreversiblemente a tu inventario.
                </div>
                
                <h6 class="fw-bold mb-3 d-flex justify-content-between">
                    Total Factura:
                    <span class="text-success fs-4 fw-bolder" id="total_factura_label">$0.00</span>
                </h6>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary py-3 fw-bold" id="btn-finalizar"><i class="fa-solid fa-check-double me-2"></i> Procesar Factura de Compra</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Panel Derecho: Agregar Producto a la tabla de compra -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 mb-4 bg-light">
            <div class="card-body p-4">
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label text-muted small mb-1">Buscar Producto en Catálogo</label>
                        <select id="select-producto" class="form-select">
                            <option value="">-- Escanee o Elija Presentación --</option>
                            <?php foreach($prods as $pd): ?>
                                <option value="<?php echo $pd['id']; ?>" data-nombre="<?php echo e($pd['label']); ?>"><?php echo e($pd['codigo_barras'] . ' - ' . $pd['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-muted small mb-1">Cantidad</label>
                        <input type="number" step="0.01" min="0.01" id="c_qty" class="form-control" value="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small mb-1">Costo Unitario ($)</label>
                        <input type="number" step="0.01" min="0.01" id="c_cost" class="form-control" value="0.00">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="button" class="btn btn-secondary" id="btn-add-list"><i class="fa-solid fa-plus"></i></button>
                    </div>
                </div>
                <div class="text-end mt-2">
                    <a href="/inventario" class="small text-decoration-none"><i class="fa-solid fa-box-open"></i> Crear nuevo producto en catálogo</a>
                </div>
            </div>
        </div>
        
        <!-- Tabla Carga Previa -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 350px;">
                    <table class="table table-hover align-middle mb-0" id="tabla-carga">
                        <thead class="bg-body-tertiary sticky-top">
                            <tr>
                                <th width="40%">Producto</th>
                                <th width="15%" class="text-center">Cant.</th>
                                <th width="20%" class="text-end">Costo Uni ($)</th>
                                <th width="20%" class="text-end">Subtotal ($)</th>
                                <th width="5%" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="lista-cargas">
                            <tr id="cargas-vacia"><td colspan="5" class="text-center text-muted py-4"><i class="fa-solid fa-arrow-up-right-dots fa-2x mb-2 d-block"></i> No has añadido productos a la orden.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let orderList = [];
    
    function renderOrder() {
        const tbody = $('#lista-cargas');
        let total = 0;
        tbody.empty();
        
        if (orderList.length === 0) {
            tbody.append('<tr id="cargas-vacia"><td colspan="5" class="text-center text-muted py-4"><i class="fa-solid fa-arrow-up-right-dots fa-2x mb-2 d-block"></i> No has añadido productos a la orden.</td></tr>');
            $('#btn-finalizar').prop('disabled', true);
        } else {
            $('#btn-finalizar').prop('disabled', false);
            orderList.forEach((item, index) => {
                total += item.costo_total_usd;
                tbody.append(`<tr>
                    <td class="fw-bold">${item.nombre}</td>
                    <td class="text-center"><span class="badge bg-secondary">${item.cantidad}</span></td>
                    <td class="text-end">$${item.costo_unitario_usd.toFixed(2)}</td>
                    <td class="text-end fw-bold text-success">$${item.costo_total_usd.toFixed(2)}</td>
                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-del" data-idx="${index}"><i class="fa-solid fa-xmark"></i></button></td>
                </tr>`);
            });
        }
        
        $('#total_factura_label').text('$' + total.toFixed(2));
        $('#compra_data').val(JSON.stringify(orderList));
    }
    
    // Init block form if empty
    renderOrder();
    
    $('#btn-add-list').click(function() {
        const select = $('#select-producto option:selected');
        const id = select.val();
        const nombre = select.data('nombre');
        const qty = parseFloat($('#c_qty').val());
        const cost = parseFloat($('#c_cost').val());
        
        if(!id || qty <= 0 || cost < 0) {
            Swal.fire({toast: true, position:'top-end', icon:'warning', title:'Formulario de producto inválido', showConfirmButton:false, timer:2000});
            return;
        }
        
        // Verifica si ya fue añadido
        const index = orderList.findIndex(x => x.presentacion_id === id);
        if(index > -1) {
            orderList[index].cantidad += qty;
            orderList[index].costo_unitario_usd = cost; // Update al ultimo costo ingresado
            orderList[index].costo_total_usd = orderList[index].cantidad * orderList[index].costo_unitario_usd;
        } else {
            orderList.push({
                presentacion_id: id,
                nombre: nombre,
                cantidad: qty,
                costo_unitario_usd: cost,
                costo_total_usd: qty * cost
            });
        }
        
        $('#c_qty').val(1);
        $('#c_cost').val('0.00');
        $('#select-producto').val('');
        
        renderOrder();
    });
    
    $(document).on('click', '.btn-del', function() {
        const idx = $(this).data('idx');
        orderList.splice(idx, 1);
        renderOrder();
    });
});
</script>

<!-- Modulo Historial abajo -->
<div class="card shadow-sm border-0 mt-5">
    <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0 px-4">
        <h5 class="fw-bold"><i class="fa-solid fa-clock-rotate-left me-2"></i> Historial Reciente de Compras Procesadas</h5>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="bg-light">
                    <tr>
                        <th width="10%">ID Op.</th>
                        <th width="20%">Fecha</th>
                        <th width="30%">Proveedor / Factura</th>
                        <th width="20%" class="text-end">Total USD</th>
                        <th width="20%" class="text-center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT c.id, c.fecha, c.factura_numero, c.total_usd, c.estado, p.rif, p.nombre FROM compras c JOIN proveedores p ON c.proveedor_id = p.id ORDER BY c.fecha DESC LIMIT 10");
                    $compras = $stmt->fetchAll();
                    
                    if (count($compras) > 0) {
                        foreach($compras as $c) {
                            $badge = $c['estado'] === 'PROCESADA' ? 'bg-success' : 'bg-warning text-dark';
                            echo "<tr>
                                    <td><span class='text-muted fw-bold'># ".str_pad($c['id'], 5, '0', STR_PAD_LEFT)."</span></td>
                                    <td>".e($c['fecha'])."</td>
                                    <td><span class='fw-bold'>".e($c['nombre'])."</span><br><small class='text-muted'>Factura: ".e($c['factura_numero'] ?: 'S/N')."</small></td>
                                    <td class='text-end fw-bold'>$".formatMoney($c['total_usd'])."</td>
                                    <td class='text-center'><span class='badge {$badge}'>".e($c['estado'])."</span></td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center text-muted py-5'>No hay compras históricas.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
