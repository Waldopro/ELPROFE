<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-boxes-stacked me-2"></i> Inventario</h2>
    <div>
        <a href="compras.php" class="btn btn-outline-primary"><i class="fa-solid fa-plus me-1"></i> Añadir Stock (Via Compras)</a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="bg-light">
                    <tr>
                        <th width="15%">Código</th>
                        <th width="35%">Producto</th>
                        <th width="15%" class="text-center">Stock</th>
                        <th width="15%" class="text-end">Costo Promedio (USD)</th>
                        <th width="15%" class="text-end">Precio Venta (USD)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT codigo_barras, nombre, stock_actual, costo_promedio_usd, precio_venta_usd FROM productos ORDER BY nombre ASC");
                    $productos = $stmt->fetchAll();
                    
                    if (count($productos) > 0) {
                        foreach($productos as $p) {
                            $stockBadge = $p['stock_actual'] <= 5 ? 'bg-danger' : 'bg-success';
                            echo "<tr>
                                    <td><kbd>{$p['codigo_barras']}</kbd></td>
                                    <td><span class='fw-bold'>".htmlspecialchars($p['nombre'])."</span></td>
                                    <td class='text-center'><span class='badge {$stockBadge} px-3 py-2'>".floatval($p['stock_actual'])."</span></td>
                                    <td class='text-end'>$".formatMoney($p['costo_promedio_usd'])."</td>
                                    <td class='text-end fw-bold text-primary'>$".formatMoney($p['precio_venta_usd'])."</td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center text-muted py-5'><i class='fa-solid fa-box-open fa-3x mb-3 text-light'></i><h5 class='mb-0'>No hay productos en inventario</h5></td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
