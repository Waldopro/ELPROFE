<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
restrictAdmin();
require_once '../includes/header.php';

$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');

$meses = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

// Fetch Ventas (FACTURAS)
$stmtV = $pdo->prepare("
    SELECT f.id, f.fecha_emision as fecha, f.factura_numero as documento, f.numero_control, c.nombre as cliente_nombre, c.cedula_rif as cliente_rif, 
           f.tasa_dia_usd_bs, f.total_usd, f.exento_bs, f.base_imponible_bs, f.iva_bs,
           (f.total_usd * f.tasa_dia_usd_bs) as total_bs
    FROM proformas f
    JOIN clientes c ON f.cliente_id = c.id
    WHERE f.tipo_documento = 'FACTURA' AND MONTH(f.fecha_emision) = ? AND YEAR(f.fecha_emision) = ? AND f.estado != 'ANULADO'
    ORDER BY f.fecha_emision ASC, f.factura_numero ASC
");
$stmtV->execute([$mes, $anio]);
$ventas = $stmtV->fetchAll(PDO::FETCH_ASSOC);

// Fetch Compras
$stmtC = $pdo->prepare("
    SELECT c.fecha, c.factura_numero as documento, c.numero_control, p.nombre as proveedor_nombre, p.rif as proveedor_rif,
           c.total_bs, c.exento_bs, c.base_imponible_bs, c.iva_bs
    FROM compras c
    JOIN proveedores p ON c.proveedor_id = p.id
    WHERE MONTH(c.fecha) = ? AND YEAR(c.fecha) = ? AND c.estado = 'PROCESADA'
    ORDER BY c.fecha ASC
");
$stmtC->execute([$mes, $anio]);
$compras = $stmtC->fetchAll(PDO::FETCH_ASSOC);

$iva_tasa = 16.00;
$empresa_nombre = getConfig('empresa_nombre', $pdo) ?: "ELPROFE C.A.";
$empresa_rif = getConfig('empresa_rif', $pdo) ?: "J-00000000-0";
?>

<div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
    <h2 class="fw-bold mb-0 text-body">Reportes y Libro de Ventas</h2>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-success btn-sm px-3 shadow-xs" href="/ELPROFE/export_reportes?mes=<?php echo urlencode((string)$mes); ?>&anio=<?php echo urlencode((string)$anio); ?>&formato=excel">
            <i class="fa-solid fa-file-excel me-1"></i> Excel
        </a>
        <a class="btn btn-outline-danger btn-sm px-3 shadow-xs" href="/ELPROFE/export_reportes?mes=<?php echo urlencode((string)$mes); ?>&anio=<?php echo urlencode((string)$anio); ?>&formato=pdf" target="_blank" rel="noopener">
            <i class="fa-solid fa-file-pdf me-1"></i> PDF
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 d-print-none bg-body">
    <div class="card-body p-4">
        <form method="GET" action="/ELPROFE/reportes" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-body">Mes</label>
                <select name="mes" class="form-select border">
                    <?php foreach($meses as $m_val => $m_lbl): ?>
                        <option value="<?php echo $m_val; ?>" <?php echo $mes === $m_val ? 'selected' : ''; ?>><?php echo $m_lbl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-body">Año</label>
                <select name="anio" class="form-select border">
                    <?php 
                    $currY = date('Y');
                    for($y=$currY; $y>=$currY-5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $anio == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100 fw-bold py-2"><i class="fa-solid fa-filter me-1"></i> Filtrar</button>
            </div>
        </form>
    </div>
</div>

<ul class="nav nav-tabs mb-0 d-print-none border-bottom-0" id="pills-tab" role="tablist">
  <li class="nav-item">
    <button class="nav-link active fw-bold px-4" data-bs-toggle="tab" data-bs-target="#tab-ventas">Ventas</button>
  </li>
  <li class="nav-item">
    <button class="nav-link fw-bold px-4" data-bs-toggle="tab" data-bs-target="#tab-compras">Compras</button>
  </li>
</ul>

<div class="tab-content bg-body border border-top-0 rounded-bottom shadow-sm mb-5" id="pills-tabContent">
  <!-- Libro de Ventas -->
  <div class="tab-pane fade show active p-4" id="tab-ventas">
      <div class="text-center mb-4">
          <h4 class="fw-bold mb-0">LIBRO DE VENTAS</h4>
          <div class="text-muted small">Periodo: <?php echo $mes; ?> / <?php echo $anio; ?></div>
          <div class="text-muted small fw-bold mt-1 text-uppercase"><?php echo e($empresa_nombre); ?> · RIF: <?php echo e($empresa_rif); ?></div>
          <div class="text-muted small">Montos fiscales expresados en Bolívares (Bs).</div>
      </div>
      
      <div class="table-responsive">
          <table class="table table-hover align-middle datatable" id="tablaVentas">
              <thead class="table-dark-custom shadow-sm">
                  <tr>
                      <th>Fecha</th>
                      <th>Factura</th>
                      <th>RIF/CI</th>
                      <th>Cliente</th>
                      <th class="text-end">Total (Bs)</th>
                      <th class="text-end">Base (Bs)</th>
                      <th class="text-end">IVA (16.00%)</th>
                      <th class="text-end">IGTF (0.00%)</th>
                  </tr>
              </thead>
              <tbody>
                  <?php 
                  $tv=0; $bv=0; $iv=0;
                  foreach($ventas as $r): 
                      $tv+=$r['total_bs']; $bv+=$r['base_imponible_bs']; $iv+=$r['iva_bs'];
                      $share = generateShareLinkToken((int)$r['id']);
                  ?>
                  <tr>
                      <td><?php echo date('d/m/Y', strtotime($r['fecha'])); ?></td>
                      <td class="fw-bold">
                          <a href="/ELPROFE/pages/nota_entrega.php?id=<?php echo $r['id']; ?>&share=<?php echo $share; ?>" target="_blank" class="report-link">
                              <?php echo e($r['documento']); ?>
                          </a>
                      </td>
                      <td class="text-muted"><?php echo e($r['cliente_rif'] ?: 'NA'); ?></td>
                      <td><?php echo e($r['cliente_nombre']); ?></td>
                      <td class="text-end fw-bold"><?php echo formatMoney($r['total_bs']); ?></td>
                      <td class="text-end"><?php echo formatMoney($r['base_imponible_bs']); ?></td>
                      <td class="text-end"><?php echo formatMoney($r['iva_bs']); ?></td>
                      <td class="text-end text-muted">0.00</td>
                  </tr>
                  <?php endforeach; ?>
              </tbody>
              <tfoot class="border-top-2">
                  <tr class="fw-bold">
                      <td colspan="4" class="text-end py-3 text-muted">TOTALES GENERALES:</td>
                      <td class="text-end py-3 text-success"><?php echo formatMoney($tv); ?></td>
                      <td class="text-end py-3 text-success"><?php echo formatMoney($bv); ?></td>
                      <td class="text-end py-3 text-success"><?php echo formatMoney($iv); ?></td>
                      <td class="text-end py-3">0.00</td>
                  </tr>
              </tfoot>
          </table>
      </div>
  </div>

  <!-- Libro de Compras -->
  <div class="tab-pane fade p-4" id="tab-compras">
      <div class="text-center mb-4">
          <h4 class="fw-bold mb-0">LIBRO DE COMPRAS</h4>
          <div class="text-muted small">Periodo: <?php echo $mes; ?> / <?php echo $anio; ?></div>
          <div class="text-muted small fw-bold mt-1 text-uppercase"><?php echo e($empresa_nombre); ?> · RIF: <?php echo e($empresa_rif); ?></div>
          <div class="text-muted small">Montos fiscales expresados en Bolívares (Bs).</div>
      </div>
      <div class="table-responsive">
          <table class="table table-hover align-middle datatable" id="tablaCompras">
              <thead class="table-dark-custom shadow-sm">
                  <tr>
                      <th>Fecha</th>
                      <th>Factura #</th>
                        <th>RIF</th>
                      <th>Proveedor</th>
                      <th class="text-end">Total (Bs)</th>
                      <th class="text-end">Base (Bs)</th>
                      <th class="text-end">IVA (Bs)</th>
                      <th class="text-end">Retención</th>
                  </tr>
              </thead>
              <tbody>
                  <?php 
                  $tc=0; $bc=0; $ic=0;
                  foreach($compras as $r): 
                      $tc+=$r['total_bs']; $bc+=$r['base_imponible_bs']; $ic+=$r['iva_bs'];
                  ?>
                  <tr>
                      <td><?php echo date('d/m/Y', strtotime($r['fecha'])); ?></td>
                      <td class="fw-bold"><?php echo e($r['documento']); ?></td>
                      <td class="text-muted"><?php echo e($r['proveedor_rif']); ?></td>
                      <td><?php echo e($r['proveedor_nombre']); ?></td>
                      <td class="text-end fw-bold"><?php echo formatMoney($r['total_bs']); ?></td>
                      <td class="text-end"><?php echo formatMoney($r['base_imponible_bs']); ?></td>
                      <td class="text-end"><?php echo formatMoney($r['iva_bs']); ?></td>
                      <td class="text-end text-muted">0.00</td>
                  </tr>
                  <?php endforeach; ?>
              </tbody>
              <tfoot>
                  <tr class="fw-bold">
                      <td colspan="4" class="text-end py-3 text-muted">TOTALES GENERALES:</td>
                      <td class="text-end py-3 text-success"><?php echo formatMoney($tc); ?></td>
                      <td class="text-end py-3 text-success"><?php echo formatMoney($bc); ?></td>
                      <td class="text-end py-3 text-success"><?php echo formatMoney($ic); ?></td>
                      <td class="text-end py-3">0.00</td>
                  </tr>
              </tfoot>
          </table>
      </div>
  </div>
</div>

<style>
@media print {
    @page { size: landscape; margin: 1cm; }
    body { background: white !important; font-size: 10px; }
    .card { border: none !important; box-shadow: none !important; }
    .table-bordered th, .table-bordered td { border: 1px solid #000 !important; color: #000 !important; }
    .bg-light { background-color: #f8f9fa !important; }
    .btn, .nav, .sidebar, .header, .footer, #filtros-area, .breadcrumb { display: none !important; }
}
.fs-7 { font-size: 0.85rem; }
.report-link {
    color: inherit;
    text-decoration: none;
    border-bottom: 1px dashed rgba(59, 130, 246, 0.35);
}
.report-link:hover {
    border-bottom-color: rgba(59, 130, 246, 0.7);
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
    letter-spacing: 0.05em;
    padding: 15px 12px;
}
</style>

<?php require_once '../includes/footer.php'; ?>
