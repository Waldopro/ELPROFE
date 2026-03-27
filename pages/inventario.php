<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
restrictAdmin();

// Formulario de catalogar producto completo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_producto_completo') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $codigo_barras = trim($_POST['codigo_barras'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $categoria_id = intval($_POST['categoria_id'] ?? 0);
    $costo_usd = floatval($_POST['costo_usd'] ?? 0);
    $precio_usd = floatval($_POST['precio_usd'] ?? 0);
    $stock_actual = floatval($_POST['stock_actual'] ?? 0);
    $stock_minimo = floatval($_POST['stock_minimo'] ?? 0);
    $exento_iva = isset($_POST['exento_iva']) ? 1 : 0;
    
    if (empty($nombre) || empty($codigo_barras) || $precio_usd <= 0) {
        setFlash('error', 'Faltan datos obligatorios (Nombre, Código, Precio).');
        header("Location: /ELPROFE/inventario");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Insert product
        $stmt_prod = $pdo->prepare("INSERT INTO productos (codigo_interno, nombre, marca, categoria_id, stock_minimo, exento_iva, costo_promedio_usd, stock_actual) VALUES (?, ?, ?, ?, ?, ?, ?, 0)"); 
        // stock_actual is 0 initially, will be updated by purchase if > 0
        $stmt_prod->execute([$codigo_barras, $nombre, $marca, $categoria_id > 0 ? $categoria_id : null, $stock_minimo, $exento_iva, $costo_usd]);
        $producto_id = $pdo->lastInsertId();

        // 2. Foto upload (if provided inline)
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['foto'];
            $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (array_key_exists($mime, $exts) && $file['size'] <= 2 * 1024 * 1024) {
                $uploadDir = __DIR__ . '/../assets/img/productos/';
                if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $filename = 'prod_' . $producto_id . '_' . time() . '.' . $exts[$mime];
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $pdo->prepare("UPDATE productos SET foto = ? WHERE id = ?")->execute([$filename, $producto_id]);
                }
            }
        }

        // 3. Create default presentation 'Unidad'
        $stmt_pres = $pdo->prepare("INSERT INTO presentaciones (producto_id, nombre_presentacion, factor_conversion, precio_venta_usd, codigo_barras) VALUES (?, 'Unidad', 1.00, ?, ?)");
        $stmt_pres->execute([$producto_id, $precio_usd, $codigo_barras]);
        $presentacion_id = $pdo->lastInsertId();

        // 4. Initial Stock via Purchase
        if ($stock_actual > 0) {
            // Find or create "Inventario Inicial" provider
            $stmt_prov = $pdo->prepare("SELECT id FROM proveedores WHERE rif = 'J-00000000-0' LIMIT 1");
            $stmt_prov->execute();
            $prov_id = $stmt_prov->fetchColumn();
            if (!$prov_id) {
                $pdo->exec("INSERT INTO proveedores (nombre, rif) VALUES ('Inventario Inicial', 'J-00000000-0')");
                $prov_id = $pdo->lastInsertId();
            }

            // Get current Tasa
            $tasa = getTasaBcv($pdo);

            // Purchase header
            $total_usd = $stock_actual * $costo_usd;
            $total_bs = $total_usd * $tasa;
            $factura_numero = 'INV-INI-' . time();
            $stmt_comp  = $pdo->prepare("INSERT INTO compras (proveedor_id, factura_numero, tasa_bs_usd, total_usd, total_bs) VALUES (?, ?, ?, ?, ?)");
            $stmt_comp->execute([$prov_id, $factura_numero, $tasa, $total_usd, $total_bs]);
            $compra_id = $pdo->lastInsertId();

            // Purchase detail (triggers stock increase and avg cost update)
            $stmt_det = $pdo->prepare("INSERT INTO compra_detalles (compra_id, presentacion_id, cantidad, costo_unitario_usd, costo_total_usd) VALUES (?, ?, ?, ?, ?)");
            $stmt_det->execute([$compra_id, $presentacion_id, $stock_actual, $costo_usd, $total_usd]);
        }

        $pdo->commit();
        setFlash('success', 'Producto creado exitosamente.');
    } catch (\PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) {
            setFlash('error', 'El código de barras ya existe.');
        } else {
            setFlash('error', 'Error al crear producto: ' . $e->getMessage());
        }
    }

    header("Location: /ELPROFE/inventario");
    exit;
}

// Formulario de guardar presentación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_presentacion') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $producto_id = intval($_POST['producto_id']);
    $codigo = trim($_POST['codigo_barras']);
    $nombre_presentacion = trim($_POST['nombre_presentacion']);
    $factor = floatval($_POST['factor_conversion']);
    $precio = floatval($_POST['precio_venta_usd']);
    
    if (empty($codigo) || empty($nombre_presentacion) || $precio <= 0 || $factor <= 0 || $producto_id <= 0) {
        setFlash('error', 'Faltan datos o valores inválidos. El código de barras, nombre, precio y factor son obligatorios.');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO presentaciones (producto_id, nombre_presentacion, factor_conversion, precio_venta_usd, codigo_barras) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$producto_id, $nombre_presentacion, $factor, $precio, $codigo]);
            setFlash('success', 'Presentación registrada con éxito.');
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                setFlash('error', 'Error: El código de barras ya pertenece a otra presentación.');
            } else {
                setFlash('error', 'Error al crear presentación: ' . $e->getMessage());
            }
        }
    }
    header("Location: /ELPROFE/inventario");
    exit;
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-boxes-stacked me-2"></i> Inventario & Catálogo</h2>
    <div>
        <button class="btn btn-primary me-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalProductoCompleto">
            <i class="fa-solid fa-plus me-1"></i> 1. Nuevo Producto
        </button>
        <button class="btn btn-primary me-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPresentacion">
            <i class="fa-solid fa-barcode me-1"></i> 2. Añadir Presentación
        </button>
        <a href="/ELPROFE/compras" class="btn btn-outline-primary shadow-sm"><i class="fa-solid fa-truck-arrow-right me-1"></i> Comprar Mercancía</a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <div class="alert alert-info py-2 mb-4" style="font-size: 0.9rem;">
            <i class="fa-solid fa-circle-info"></i> Todo el inventario se muestra en su unidad mínima (Unidades). Las compras de cajas o bultos se descomponen automáticamente de acuerdo a su factor de conversión.
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="bg-light">
                    <tr>
                        <th width="8%" class="text-center">Foto</th>
                        <th width="32%">Producto Base</th>
                        <th width="30%">Presentaciones Activas</th>
                        <th width="12%" class="text-center">Stock (Unds)</th>
                        <th width="13%" class="text-end">Costo AVG Unit ($)</th>
                        <th width="5%" class="text-center"><i class="fa-solid fa-tools"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT p.id, p.nombre, p.foto, p.stock_actual, p.costo_promedio_usd,
                                         (SELECT GROUP_CONCAT(CONCAT(pr.codigo_barras, '@@', pr.nombre_presentacion, ' (', pr.factor_conversion, ' un) - $', pr.precio_venta_usd) SEPARATOR '||') 
                                          FROM presentaciones pr WHERE pr.producto_id = p.id) as presentaciones_lista
                                         FROM productos p ORDER BY p.nombre ASC");
                    $productos = $stmt->fetchAll();
                    
                    if (count($productos) > 0) {
                        foreach($productos as $p) {
                            $stockBadge = $p['stock_actual'] <= 10 ? 'bg-danger' : 'bg-success';
                            $imgSrc = $p['foto'] ? "/assets/img/productos/{$p['foto']}" : "https://ui-avatars.com/api/?name=".urlencode($p['nombre'])."&background=random&size=50";
                            
                            $pres_html = "";
                            if ($p['presentaciones_lista']) {
                                $pres_arr = explode('||', $p['presentaciones_lista']);
                                foreach($pres_arr as $pres_raw) {
                                    $dataArr = explode('@@', $pres_raw);
                                    $bcode = $dataArr[0] ?? '';
                                    $bdesc = $dataArr[1] ?? '';
                                    $tit = htmlspecialchars(addslashes($p['nombre'] . ' - ' . $bdesc));
                                    $pres_html .= "<span class='badge bg-light text-dark border me-1 mb-1 fw-normal'>".e($bdesc)." <a href='javascript:void(0)' onclick='abrirEtiqueta(\"".e($bcode)."\", \"".$tit."\")' class='text-primary ms-1' title='Crear Etiqueta'><i class='fa-solid fa-barcode'></i></a></span>";
                                }
                            } else {
                                $pres_html = "<span class='text-danger small'><i class='fa-solid fa-triangle-exclamation'></i> Cero presentaciones. Incomprable.</span>";
                            }
                            
                            echo "<tr>
                                    <td class='text-center'><img src='".e($imgSrc)."' class='rounded shadow-sm' style='width: 40px; height: 40px; object-fit: cover;'></td>
                                    <td><span class='fw-bold'>".e($p['nombre'])."</span></td>
                                    <td>{$pres_html}</td>
                                    <td class='text-center'><span class='badge {$stockBadge} px-3 py-2'>".floatval($p['stock_actual'])."</span></td>
                                    <td class='text-end'>$".formatMoney($p['costo_promedio_usd'])."</td>
                                    <td class='text-center'>
                                        <button class='btn btn-sm btn-outline-secondary btn-editar-foto' data-id='".e($p['id'])."' data-nombre='".e($p['nombre'])."' title='Editar Foto'>
                                            <i class='fa-solid fa-camera'></i>
                                        </button>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center text-muted py-5'><i class='fa-solid fa-box-open fa-3x mb-3 text-light'></i><h5 class='mb-0'>No hay productos registrados</h5></td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Foto Producto -->
<div class="modal fade" id="modalFoto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Actualizar Foto: <span id="producto-nombre-modal" class="text-primary"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="form-foto" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_foto">
            <input type="hidden" name="producto_id" id="modal-producto-id">
            <input type="hidden" name="csrf_token" id="csrf_token_foto" value="">
            
            <div class="mb-3 text-center">
                <i class="fa-solid fa-cloud-arrow-up fa-4x text-muted mb-3"></i>
                <input class="form-control form-control-lg" id="fotoFile" type="file" name="foto" accept="image/jpeg, image/png, image/webp" required>
                <div class="form-text mt-2">Formatos permitidos: JPG, PNG, WEBP. Max: 2MB.</div>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btn-upload"><i class="fa-solid fa-save"></i> Guardar Foto</button>
      </div>
    </div>
  </div>
</div>



<!-- Modal Nuevo Producto Completo -->
<div class="modal fade" id="modalProductoCompleto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold text-primary">Nuevo Producto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="/ELPROFE/inventario" enctype="multipart/form-data">
          <div class="modal-body">
            <input type="hidden" name="action" value="save_producto_completo">
            <?php echo csrfField(); ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small">Código de Barras *</label>
                    <input type="text" name="codigo_barras" class="form-control" required placeholder="Escanee o escriba...">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small">Marca</label>
                    <input type="text" name="marca" class="form-control" placeholder="Ej: Sony, Samsung...">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Descripción *</label>
                <input type="text" name="nombre" class="form-control" required placeholder="Nombre detallado del producto">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Categoría</label>
                <select name="categoria_id" class="form-select">
                    <option value="">Seleccione...</option>
                    <?php 
                    $cats = $pdo->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetchAll();
                    foreach($cats as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo e($c['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label text-muted small">Costo USD</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" min="0" name="costo_usd" id="np_costo" class="form-control" value="0.00">
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label text-muted small">Margen (%)</label>
                    <input type="number" step="0.01" min="0" id="np_margen" class="form-control" value="30">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label text-muted small">Precio USD *</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" min="0.01" name="precio_usd" id="np_precio" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small">Stock Actual (Inicial)</label>
                    <input type="number" step="any" min="0" name="stock_actual" class="form-control" value="0">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small">Stock Mínimo</label>
                    <input type="number" step="any" min="0" name="stock_minimo" class="form-control" value="5">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Imagen</label>
                <input type="file" name="foto" class="form-control" accept="image/jpeg, image/png, image/webp">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="exento_iva" value="1" id="np_exento">
                <label class="form-check-label" for="np_exento">
                    Producto Exento de IVA
                </label>
            </div>
          </div>
          <div class="modal-footer pb-3">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm"><i class="fa-solid fa-save"></i> Guardar Producto</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Nueva Presentación -->
<div class="modal fade" id="modalPresentacion" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold text-primary">2. Añadir Presentación a Producto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="/ELPROFE/inventario">
          <div class="modal-body">
            <input type="hidden" name="action" value="save_presentacion">
            <?php echo csrfField(); ?>
            <div class="mb-3">
                <label class="form-label text-muted small">Producto Base *</label>
                <select name="producto_id" class="form-select" required>
                    <option value="">-- Seleccione el papá --</option>
                    <?php 
                    $pd = $pdo->query("SELECT id, nombre FROM productos ORDER BY nombre")->fetchAll();
                    foreach($pd as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo e($p['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label text-muted small">Nombre Presentación *</label>
                    <input type="text" name="nombre_presentacion" class="form-control" required placeholder="Ej: Caja (12 un)">
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label text-muted small">Factor Modificador *</label>
                    <input type="number" step="1" name="factor_conversion" class="form-control" required placeholder="Ej: 12">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Código de Barras *</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-barcode"></i></span>
                    <input type="text" name="codigo_barras" class="form-control" required placeholder="Escanee la caja/bulto">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Precio Venta Público (USD) *</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" step="0.01" min="0.01" name="precio_venta_usd" class="form-control" required>
                </div>
            </div>
          </div>
          <div class="modal-footer pb-3">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm"><i class="fa-solid fa-save"></i> Guardar Variante</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Etiqueta Código de Barras -->
<div class="modal fade" id="modalEtiqueta" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h6 class="modal-title fw-bold"><i class="fa-solid fa-print"></i> Etiqueta Física</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-4 bg-white" id="area-impresion">
          <small class="d-block text-dark fw-bold mb-2" id="etiqueta-nombre" style="font-size: 0.75rem;"></small>
          <svg id="etiqueta-svg"></svg>
      </div>
      <div class="modal-footer pb-3 justify-content-center">
        <button type="button" class="btn btn-primary fw-bold shadow-sm w-100" onclick="imprimirEtiqueta()"><i class="fa-solid fa-print"></i> Mandar a Impresora</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script>
function abrirEtiqueta(codigo, descripcion) {
    $('#etiqueta-nombre').text(descripcion);
    JsBarcode("#etiqueta-svg", codigo, {
        format: "CODE128",
        lineColor: "#000",
        width: 2,
        height: 60,
        displayValue: true,
        fontSize: 14
    });
    new bootstrap.Modal(document.getElementById('modalEtiqueta')).show();
}

function imprimirEtiqueta() {
    let contenido = document.getElementById('area-impresion').innerHTML;
    let ventana = window.open('', '', 'width=400,height=300');
    ventana.document.write('<html><head><title>Imprimir Etiqueta</title>');
    ventana.document.write('<style>body{margin:0;padding:10px;text-align:center;font-family:sans-serif;} svg{width:100%;max-width:250px;}</style></head><body>');
    ventana.document.write(contenido);
    ventana.document.write('</body></html>');
    ventana.document.close();
    ventana.setTimeout(function(){
        ventana.print();
        ventana.close();
    }, 500);
}

$(document).ready(function() {

    // Calculation of Precio USD from Costo and Margen
    function calcPrecio() {
        let costo = parseFloat($('#np_costo').val()) || 0;
        let margen = parseFloat($('#np_margen').val()) || 0;
        let precio = costo + (costo * (margen / 100));
        $('#np_precio').val(precio.toFixed(2));
    }
    $('#np_costo, #np_margen').on('input', calcPrecio);

    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    $('#csrf_token_foto').val(csrfToken);

    $('.btn-editar-foto').on('click', function() {
        $('#modal-producto-id').val($(this).data('id'));
        $('#producto-nombre-modal').text($(this).data('nombre'));
        $('#fotoFile').val('');
        new bootstrap.Modal(document.getElementById('modalFoto')).show();
    });
    
    $('#btn-upload').click(function() {
        if(!$('#fotoFile').val()) {
            Swal.fire('Error', 'Seleccione una imagen.', 'error'); return;
        }
        var formData = new FormData($('#form-foto')[0]);
        $.ajax({
            url: '/ELPROFE/api/producto.php', type: 'POST', data: formData, contentType: false, processData: false,
            beforeSend: () => { Swal.showLoading(); },
            success: (res) => {
                if(res.success) Swal.fire('Éxito', 'Foto subida', 'success').then(() => location.reload());
                else Swal.fire('Error', res.message, 'error');
            }
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
