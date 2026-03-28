<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
restrictAdmin();

// Formulario de catalogar producto completo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_producto_completo') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $codigo_interno = trim($_POST['codigo_interno'] ?? '');
    $codigo_barras = trim($_POST['codigo_barras'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $categoria_id = intval($_POST['categoria_id'] ?? 0);
    $costo_usd = floatval($_POST['costo_usd'] ?? 0);
    $precio_usd = floatval($_POST['precio_usd'] ?? 0);
    $stock_minimo = floatval($_POST['stock_minimo'] ?? 0);
    $exento_iva = isset($_POST['exento_iva']) ? 1 : 0;
    
    if (empty($nombre) || empty($codigo_interno) || $precio_usd <= 0) {
        setFlash('error', 'Faltan datos obligatorios (Nombre, Código Único, Precio).');
        header("Location: /ELPROFE/inventario");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Insert product
        $stmt_prod = $pdo->prepare("INSERT INTO productos (codigo_interno, codigo_barras, nombre, marca, categoria_id, stock_minimo, exento_iva, costo_promedio_usd, stock_actual) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)"); 
        // stock_actual is 0 initially, will be updated by purchase if > 0
        $stmt_prod->execute([$codigo_interno, $codigo_barras, $nombre, $marca, $categoria_id > 0 ? $categoria_id : null, $stock_minimo, $exento_iva, $costo_usd]);
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

        $pdo->commit();
        registrarAccion($pdo, 'INVENTARIO', 'CREAR', "Producto creado: $nombre ($codigo_barras)");
        setFlash('success', 'Producto creado exitosamente.');
    } catch (\PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) {
            setFlash('error', 'El código único ya existe.');
        } else {
            setFlash('error', 'Error al crear producto: ' . $e->getMessage());
        }
    }

    header("Location: /ELPROFE/inventario");
    exit;
}

// Eliminar producto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar_producto') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $id = intval($_POST['producto_id']);
    try {
        $pdo->prepare("DELETE FROM productos WHERE id = ?")->execute([$id]);
        setFlash('success', 'Producto eliminado con éxito.');
    } catch (\PDOException $e) {
        setFlash('error', 'No se puede eliminar porque tiene ventas o compras asociadas.');
    }
    header("Location: /ELPROFE/inventario");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_producto') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $id = intval($_POST['producto_id']);
    $codigo_interno = trim($_POST['codigo_interno'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $categoria_id = intval($_POST['categoria_id'] ?? 0);
    $costo_usd = floatval($_POST['costo_usd'] ?? 0);
    $precio_usd = floatval($_POST['precio_usd'] ?? 0);
    $stock_minimo = floatval($_POST['stock_minimo'] ?? 0);
    $exento_iva = isset($_POST['exento_iva']) ? 1 : 0;
    
    if ($id > 0 && !empty($nombre) && !empty($codigo_interno)) {
        $codigo_barras = trim($_POST['codigo_barras'] ?? '');
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE productos SET codigo_interno=?, codigo_barras=?, nombre=?, marca=?, categoria_id=?, costo_promedio_usd=?, stock_minimo=?, exento_iva=? WHERE id=?");
            $stmt->execute([$codigo_interno, $codigo_barras, $nombre, $marca, $categoria_id > 0 ? $categoria_id : null, $costo_usd, $stock_minimo, $exento_iva, $id]);
            
            $stmt_u = $pdo->prepare("UPDATE presentaciones SET precio_venta_usd=? WHERE producto_id=? AND factor_conversion=1 LIMIT 1");
            $stmt_u->execute([$precio_usd, $id]);

            $pdo->commit();
            registrarAccion($pdo, 'INVENTARIO', 'MODIFICAR', "Producto modificado ID: $id -> $nombre");
            setFlash('success', 'Producto actualizado con éxito.');
        } catch (\PDOException $e) {
            $pdo->rollBack();
            setFlash('error', 'Error al actualizar: ' . $e->getMessage());
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
            registrarAccion($pdo, 'INVENTARIO', 'CREAR_VARIANTE', "Presentación añadida: $nombre_presentacion ($codigo) a Producto ID $producto_id");
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

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center align-items-start mb-4 gap-3">
    <div class="d-flex align-items-center">
        <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-boxes-stacked me-2"></i> Inventario & Catálogo</h2>
    </div>
    <div class="dropdown d-flex gap-2 flex-wrap">
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalProductoCompleto">
            <i class="fa-solid fa-plus me-1"></i> <span class="d-none d-sm-inline">1.</span> Nuevo Producto
        </button>
        <button class="btn btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPresentacion">
            <i class="fa-solid fa-barcode me-1"></i> <span class="d-none d-sm-inline">2.</span> Añadir Presentación
        </button>
        <a href="/ELPROFE/compras" class="btn btn-outline-secondary shadow-sm">
            <i class="fa-solid fa-truck-arrow-right me-1"></i> <span class="d-none d-sm-inline">Comprar</span>
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <div class="alert alert-info py-2 mb-4" style="font-size: 0.9rem;">
            <i class="fa-solid fa-circle-info"></i> Todo el inventario se muestra en su unidad mínima (Unidades). Las compras de cajas o bultos se descomponen automáticamente de acuerdo a su factor de conversión.
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label text-muted small fw-bold">Filtrar por Categoría</label>
                <select id="filtro-categoria" class="form-select border-0 bg-light shadow-sm">
                    <option value="">Todas las Categorías</option>
                    <?php 
                    $catList = $pdo->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC")->fetchAll();
                    foreach($catList as $c): ?>
                        <option value="<?php echo e($c['nombre']); ?>"><?php echo e($c['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8 d-flex align-items-end justify-content-md-end">
                <div class="input-group shadow-sm border-0 rounded-3 overflow-hidden" style="max-width: 400px; height: 38px;">
                    <span class="input-group-text bg-light text-muted border-0"><i class="fa-solid fa-search"></i></span>
                    <input type="text" class="form-control border-0 bg-light custom-search" placeholder="Buscar por código o descripción..." style="font-size: 0.95rem;">
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle datatable" id="tablaInventario">
                <thead class="table-dark-custom">
                    <tr>
                        <th class="ps-3">ID / Cód. Único</th>
                        <th>Barras</th>
                        <th>Foto</th>
                        <th>Descripción</th>
                        <th>Categoría</th>
                        <th>Costo $</th>
                        <th>Precio $</th>
                        <th>Precio Bs</th>
                        <th>Margen %</th>
                        <th>Stock</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $tasa = getConfig('tasa_usd_bs', $pdo);
                    $stmt = $pdo->query("
                        SELECT p.id, p.codigo_interno, p.codigo_barras, p.nombre, p.marca, p.foto, p.stock_actual, p.stock_minimo, p.costo_promedio_usd, p.categoria_id, p.exento_iva,
                               c.nombre as categoria_nombre,
                               (SELECT precio_venta_usd FROM presentaciones WHERE producto_id = p.id ORDER BY factor_conversion ASC LIMIT 1) as precio_usd
                        FROM productos p 
                        LEFT JOIN categorias c ON p.categoria_id = c.id
                        ORDER BY p.nombre ASC
                    ");
                    $productos = $stmt->fetchAll();
                    
                    if (count($productos) > 0) {
                        foreach($productos as $p) {
                            $stockBadge = $p['stock_actual'] <= 5 ? 'bg-danger text-white' : 'bg-success text-white';
                            $imgSrc = $p['foto'] ? "/ELPROFE/assets/img/productos/{$p['foto']}" : "";
                            
                            $imgHtml = $imgSrc ? "<img src='".e($imgSrc)."' class='rounded shadow-sm' style='width: 32px; height: 32px; object-fit: cover; cursor: pointer;' onclick='verImagen(\"".e($imgSrc)."\", \"".e($p['nombre'])."\")' title='Ver Imagen'>" : "<div class='bg-light rounded d-flex align-items-center justify-content-center' style='width: 32px; height: 32px; cursor: pointer;' onclick='verImagen(null, \"".e($p['nombre'])."\")'><i class='fa-regular fa-image text-muted'></i></div>";
                             
                             $costo = floatval($p['costo_promedio_usd']);
                            $precio = floatval($p['precio_usd']);
                            $margen = $costo > 0 ? (($precio - $costo) / $costo) * 100 : 0;
                            $precio_bs = $precio * $tasa;
                            ?>
                            <tr>
                                <td class="ps-3 fw-bold"><?php echo e($p['codigo_interno']); ?></td>
                                <td class="font-monospace small"><?php echo e($p['codigo_barras']); ?></td>
                                <td>
                                    <?php if($imgSrc): ?>
                                      <img src="<?php echo e($imgSrc); ?>" class="rounded shadow-sm" 
                                           style="width: 32px; height: 32px; object-fit: cover; cursor: pointer;" 
                                           onclick="verImagen('<?php echo e($imgSrc); ?>', '<?php echo e($p['nombre']); ?>')" 
                                           title="Ver Imagen">
                                    <?php else: ?>
                                      <div class="bg-light rounded d-flex align-items-center justify-content-center btn-editar-foto" 
                                           data-id="<?php echo e($p['id']); ?>" data-nombre="<?php echo e($p['nombre']); ?>"
                                           style="width: 32px; height: 32px; cursor: pointer;" 
                                           title="Cargar Foto">
                                           <i class="fa-regular fa-image text-muted"></i>
                                      </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo e($p['nombre']); ?></div>
                                    <small class="text-muted"><?php echo e($p['marca']); ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo e($p['categoria_nombre'] ?: 'General'); ?></span></td>
                                <td>$<?php echo formatMoney($costo); ?></td>
                                <td class="fw-bold text-success">$<?php echo formatMoney($precio); ?></td>
                                <td class="text-muted">Bs <?php echo formatMoney($precio_bs); ?></td>
                                <td><span class="fw-bold" style="color: #666;"><?php echo number_format($margen, 1); ?>%</span></td>
                                <td><span class="badge <?php echo $stockBadge; ?> px-2 py-1"><?php echo floatval($p['stock_actual']); ?> und</span></td>
                                <td class="text-nowrap pe-3">
                                    <div class="d-flex justify-content-end gap-2 action-btns">
                                        <button class="btn btn-outline-primary btn-edit-prod" 
                                            data-id="<?php echo e($p['id']); ?>" data-nombre="<?php echo e($p['nombre']); ?>" 
                                            data-marca="<?php echo e($p['marca']); ?>" data-categoria="<?php echo e($p['categoria_id'] ?? ''); ?>"
                                            data-codigo="<?php echo e($p['codigo_interno']); ?>" data-barras="<?php echo e($p['codigo_barras']); ?>"
                                            data-costo="<?php echo (float)$costo; ?>" data-precio="<?php echo (float)$precio; ?>"
                                            data-margen="<?php echo (float)$margen; ?>" data-stock="<?php echo (float)$p['stock_actual']; ?>"
                                            data-stockmin="<?php echo (float)$p['stock_minimo']; ?>" data-exento="<?php echo (int)$p['exento_iva']; ?>"
                                            title="Editar">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        
                                        <button type="button" onclick="abrirEtiqueta('<?php echo e($p['codigo_barras']); ?>', '<?php echo htmlspecialchars(addslashes($p['nombre'])); ?>')" 
                                            class="btn btn-outline-barcode" title="Código de Barras">
                                            <i class="fa-solid fa-barcode"></i>
                                        </button>

                                        <form method="POST" action="/ELPROFE/inventario" onsubmit="return confirm('¿Desea eliminar este producto?')">
                                            <input type="hidden" name="action" value="eliminar_producto">
                                            <input type="hidden" name="producto_id" value="<?php echo e($p['id']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Eliminar">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                        }
                    } else {
                        echo "<tr><td colspan='11' class='text-center text-muted py-5'><i class='fa-solid fa-box-open fa-3x mb-3 text-light'></i><h5 class='mb-0'>No hay productos registrados</h5></td></tr>";
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
                    <label class="form-label text-muted small">Código Único (Interno) *</label>
                    <input type="text" name="codigo_interno" class="form-control" required placeholder="Ejem: P-001">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small">Código de Barras</label>
                    <div class="input-group">
                        <input type="text" name="codigo_barras" id="np_barras" class="form-control" placeholder="Escanear o generar...">
                        <button class="btn btn-outline-secondary" type="button" onclick="generarBarcode('np_barras')"><i class="fa-solid fa-wand-magic-sparkles"></i></button>
                    </div>
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
                <div class="col-md-12 mb-3">
                    <label class="form-label text-muted small">Stock Mínimo</label>
                    <input type="number" step="any" min="0" name="stock_minimo" class="form-control" value="5">
                    <small class="text-muted">El stock inicial y cualquier ingreso de inventario se realiza exclusivamente desde el módulo <strong>Compras</strong>.</small>
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
                <label class="form-label text-muted small">Código de Barras Opcional *</label>
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

    function calcPrecioEdit() {
        let costo = parseFloat($('#epd_costo').val()) || 0;
        let margen = parseFloat($('#epd_margen').val()) || 0;
        let precio = costo + (costo * (margen / 100));
        $('#epd_precio').val(precio.toFixed(2));
    }
    $('#epd_costo, #epd_margen').on('input', calcPrecioEdit);

    // Filtro por categoría dinámico
    $('#filtro-categoria').on('change', function() {
        var val = $(this).val();
        window.table.column(4).search(val ? '^' + val + '$' : '', true, false).draw();
    });

    $('.btn-edit-prod').on('click', function() {
        $('#epd_id').val($(this).data('id'));
        $('#epd_codigo').val($(this).data('codigo'));
        $('#epd_barras').val($(this).data('barras'));
        $('#epd_nombre').val($(this).data('nombre'));
        $('#epd_marca').val($(this).data('marca'));
        $('#epd_categoria').val($(this).data('categoria'));
        $('#epd_costo').val($(this).data('costo'));
        $('#epd_precio').val($(this).data('precio'));
        $('#epd_margen').val($(this).data('margen'));
        $('#epd_stock').val($(this).data('stock'));
        $('#epd_stockmin').val($(this).data('stockmin'));
        $('#epd_exento').prop('checked', $(this).data('exento') === 1);
        var m = new bootstrap.Modal(document.getElementById('modalEditProducto'));
        m.show();
    });
});

function verImagen(src, nombre) {
    if(!src) {
        Swal.fire({
            title: nombre,
            text: 'Este producto no tiene una imagen cargada.',
            icon: 'info',
            confirmButtonText: 'Entendido'
        });
        return;
    }
    $('#modal-preview-img').attr('src', src);
    $('#modal-preview-title').text(nombre);
    new bootstrap.Modal(document.getElementById('modalVerImagen')).show();
}

function generarBarcode(targetId) {
    // Generar EAN-13 ficticio empezando por 7 (Prefijo interno sugerido)
    let code = '7' + Array.from({length: 11}, () => Math.floor(Math.random() * 10)).join('');
    // Dígito de control simple o completar a 13
    let sum = 0;
    for (let i = 0; i < 12; i++) {
        sum += parseInt(code[i]) * (i % 2 === 0 ? 1 : 3);
    }
    let checkDigit = (10 - (sum % 10)) % 10;
    document.getElementById(targetId).value = code + checkDigit;
}
</script>

<div class="modal fade" id="modalEditProducto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Editar Producto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="/ELPROFE/inventario">
          <div class="modal-body">
            <input type="hidden" name="action" value="edit_producto">
            <input type="hidden" name="producto_id" id="epd_id">
            <?php echo csrfField(); ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small">Código Único *</label>
                    <input type="text" name="codigo_interno" id="epd_codigo" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small">Código de Barras</label>
                    <div class="input-group">
                        <input type="text" name="codigo_barras" id="epd_barras" class="form-control">
                        <button class="btn btn-outline-secondary" type="button" onclick="generarBarcode('epd_barras')"><i class="fa-solid fa-wand-magic-sparkles"></i></button>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small">Marca</label>
                    <input type="text" name="marca" id="epd_marca" class="form-control">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Descripción *</label>
                <input type="text" name="nombre" id="epd_nombre" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Categoría</label>
                <select name="categoria_id" id="epd_categoria" class="form-select">
                    <option value="">General</option>
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
                        <input type="number" step="0.01" min="0" name="costo_usd" id="epd_costo" class="form-control" value="0.00">
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label text-muted small">Margen (%)</label>
                    <input type="number" step="0.01" min="0" id="epd_margen" class="form-control" value="0.00">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label text-muted small">Precio USD *</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" min="0.01" name="precio_usd" id="epd_precio" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small">Stock Actual</label>
                    <input type="number" step="any" min="0" id="epd_stock" class="form-control" value="0" readonly>
                    <small class="text-muted">Solo lectura. Para ingresar stock usa Compras.</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small">Stock Mínimo</label>
                    <input type="number" step="any" min="0" name="stock_minimo" id="epd_stockmin" class="form-control" value="0">
                </div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="exento_iva" value="1" id="epd_exento">
                <label class="form-check-label" for="epd_exento">
                    Producto Exento de IVA
                </label>
            </div>
          </div>
          <div class="modal-footer pb-3">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">Guardar Producto</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Vista Previa de Imagen -->
<div class="modal fade" id="modalVerImagen" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg border-0 bg-transparent">
      <div class="modal-body p-0 text-center">
        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 shadow" data-bs-dismiss="modal" style="z-index:1100;"></button>
        <img id="modal-preview-img" src="" class="img-fluid rounded shadow" style="max-height: 80vh; border: 5px solid white;">
        <div class="bg-dark text-white p-2 small rounded-bottom" id="modal-preview-title"></div>
      </div>
    </div>
  </div>
</div>

<style>
/* Ajustes para tabla en móvil */
@media (max-width: 768px) {
    #tablaInventario { font-size: 0.85rem; }
    #tablaInventario img { width: 40px !important; height: 40px !important; }
    .action-btns .btn { padding: 4px 8px; font-size: 0.8rem; }
    .card-body { padding: 1rem !important; }
}

/* Forzar encabezados legibles en modo oscuro */
.table-dark-custom {
    background-color: var(--bs-body-bg);
}
[data-bs-theme="dark"] .table-dark-custom th {
    background-color: #1a1d21 !important;
    color: #e9ecef !important;
    border-bottom: 2px solid #323539 !important;
}
[data-bs-theme="light"] .table-dark-custom th {
    background-color: #f8f9fa !important;
    color: #495057 !important;
    border-bottom: 2px solid #dee2e6 !important;
}

.table-dark-custom th {
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.02em;
    padding: 12px 8px;
}

</style>

<?php require_once '../includes/footer.php'; ?>
