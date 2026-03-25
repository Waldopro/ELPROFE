<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-truck-fast me-2"></i> Módulo de Compras</h2>
    <div>
        <button class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i> Registrar Nueva Compra</button>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <div class="alert alert-info border-0 rounded-3 shadow-sm" role="alert" style="background: rgba(var(--bs-info-rgb), 0.1);">
            <i class="fa-solid fa-circle-info me-2"></i> El ingreso de mercancía está <strong>estrictamente ligado a este módulo</strong>. Las cantidades se suman al inventario automáticamente al procesar la factura de proveedor.
        </div>
        
        <div class="table-responsive mt-4">
            <table class="table table-hover align-middle">
                <thead class="bg-light">
                    <tr>
                        <th width="10%">ID</th>
                        <th width="20%">Fecha</th>
                        <th width="30%">Cédula / RIF Proveedor</th>
                        <th width="20%" class="text-end">Total USD</th>
                        <th width="20%" class="text-center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT c.id, c.fecha, c.total_usd, c.estado, p.rif, p.nombre FROM compras c JOIN proveedores p ON c.proveedor_id = p.id ORDER BY c.fecha DESC LIMIT 10");
                    $compras = $stmt->fetchAll();
                    
                    if (count($compras) > 0) {
                        foreach($compras as $c) {
                            $badge = $c['estado'] === 'PROCESADA' ? 'bg-success' : 'bg-warning text-dark';
                            echo "<tr>
                                    <td># ".str_pad($c['id'], 5, '0', STR_PAD_LEFT)."</td>
                                    <td>{$c['fecha']}</td>
                                    <td><span class='fw-bold'>".htmlspecialchars($c['nombre'])."</span><br><small class='text-muted'>{$c['rif']}</small></td>
                                    <td class='text-end'>$".formatMoney($c['total_usd'])."</td>
                                    <td class='text-center'><span class='badge {$badge}'>{$c['estado']}</span></td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center text-muted py-5'><i class='fa-solid fa-clipboard-list fa-3x mb-3 text-light'></i><h5 class='mb-0'>No hay compras registradas</h5></td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
