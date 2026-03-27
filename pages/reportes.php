<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
restrictAdmin();
require_once '../includes/header.php';

$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');
$tipo_reporte = $_GET['tipo_reporte'] ?? 'ventas';

$registros = [];
// Asumimos un margen general para base imponible o definimos IVA si en este negocio aplica
// Actualmente el sistema usa exento_iva, pero no lo tiene implementado en líneas de ventas de la factura_numero.
// Simplificamos base imponible y el IVA como 16% asumiendo todos gravados (como base de ejemplo).
$iva_tasa = 16.00;

if ($tipo_reporte === 'ventas') {
    $stmt = $pdo->prepare("
        SELECT f.fecha_emision as fecha, f.factura_numero as documento, c.nombre as cliente_nombre, c.cedula_rif as cliente_rif, 
               f.tasa_dia_usd_bs, f.total_usd,
               (f.total_usd * f.tasa_dia_usd_bs) as total_bs
        FROM proformas f
        JOIN clientes c ON f.cliente_id = c.id
        WHERE f.tipo_documento = 'FACTURA' AND MONTH(f.fecha_emision) = ? AND YEAR(f.fecha_emision) = ? AND f.estado != 'ANULADO'
        ORDER BY f.fecha_emision ASC, f.factura_numero ASC
    ");
    $stmt->execute([$mes, $anio]);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("
        SELECT c.fecha, c.factura_numero as documento, p.nombre as proveedor_nombre, p.rif as proveedor_rif,
               c.total_bs
        FROM compras c
        JOIN proveedores p ON c.proveedor_id = p.id
        WHERE MONTH(c.fecha) = ? AND YEAR(c.fecha) = ? AND c.estado = 'PROCESADA'
        ORDER BY c.fecha ASC
    ");
    $stmt->execute([$mes, $anio]);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Emisor data
$empresa_nombre = getConfig('empresa_nombre', $pdo) ?: "ELPROFE C.A.";
$empresa_rif = getConfig('empresa_rif', $pdo) ?: "J-00000000-0";
?>

<div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
    <h2 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-book me-2"></i> Reportes y Libros</h2>
    <div>
        <button class="btn btn-outline-success shadow-sm me-2" onclick="exportToExcel('tablaReporte', '<?php echo $tipo_reporte === 'ventas' ? 'Libro_Ventas' : 'Libro_Compras'; ?>_<?php echo $anio.'_'.$mes; ?>.xls')">
            <i class="fa-solid fa-file-excel me-1"></i> Excel
        </button>
        <button class="btn btn-outline-danger shadow-sm" onclick="window.print()">
            <i class="fa-solid fa-file-pdf me-1"></i> PDF
        </button>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 d-print-none">
    <div class="card-body">
        <form method="GET" action="/ELPROFE/reportes" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">Tipo de Reporte</label>
                <select name="tipo_reporte" class="form-select">
                    <option value="ventas" <?php echo $tipo_reporte === 'ventas' ? 'selected' : ''; ?>>Libro de Ventas SENIAT</option>
                    <option value="compras" <?php echo $tipo_reporte === 'compras' ? 'selected' : ''; ?>>Libro de Compras SENIAT</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Mes</label>
                <select name="mes" class="form-select">
                    <?php for($i=1; $i<=12; $i++): $pad = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                        <option value="<?php echo $pad; ?>" <?php echo $mes === $pad ? 'selected' : ''; ?>><?php echo $pad; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Año</label>
                <input type="number" name="anio" class="form-control" value="<?php echo htmlspecialchars($anio); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-filter me-1"></i> Generar</button>
            </div>
        </form>
    </div>
</div>

<!-- Contenido del Reporte -->
<div class="card shadow-sm border-0">
    <div class="card-body p-4 text-center d-none d-print-block">
        <h4 class="fw-bold mb-1">LIBRO DE <?php echo strtoupper($tipo_reporte); ?></h4>
        <p class="mb-0">Periodo: <?php echo $mes . ' / ' . $anio; ?></p>
        <p class="mb-0 fw-bold"><?php echo e($empresa_nombre); ?> - RIF: <?php echo e($empresa_rif); ?></p>
    </div>
    
    <div class="card-body p-0 mt-3">
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle fs-7 mb-0 text-center" id="tablaReporte">
                <thead class="bg-light align-middle">
                    <tr>
                        <th rowspan="2">Nro<br>Operación</th>
                        <th rowspan="2">Fecha<br>Documento</th>
                        <th rowspan="2">RIF / CI</th>
                        <th rowspan="2">Nombre o Razón Social</th>
                        <th rowspan="2">Número<br>Factura</th>
                        <th rowspan="2">Total<br>Ventas (Bs)</th>
                        <th rowspan="2">Ventas<br>Exentas (Bs)</th>
                        <th colspan="3">Ventas Internas Gravadas (Bs)</th>
                    </tr>
                    <tr>
                        <th>Base Imponible</th>
                        <th>% Alicuota</th>
                        <th>Impuesto (IVA)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_general = 0;
                    $total_base = 0;
                    $total_iva = 0;
                    $op_num = 1;

                    if (count($registros) > 0) {
                        foreach($registros as $r) {
                            $fecha = date('d/m/Y', strtotime($r['fecha']));
                            $doc = $r['documento'];
                            
                            if ($tipo_reporte === 'ventas') {
                                $identificacion = $r['cliente_rif'];
                                $nombre = $r['cliente_nombre'];
                                $total_bs = $r['total_bs'];
                            } else {
                                $identificacion = $r['proveedor_rif'];
                                $nombre = $r['proveedor_nombre'];
                                $total_bs = $r['total_bs'];
                            }
                            
                            // Cálculos matemáticos aproximados del SENIAT
                            // Asumimos un IVA general (Monto/(1+IVA) = Base)
                            $base_imponible = $total_bs / (1 + ($iva_tasa / 100));
                            $monto_iva = $total_bs - $base_imponible;
                            
                            $total_general += $total_bs;
                            $total_base += $base_imponible;
                            $total_iva += $monto_iva;

                            echo "<tr>
                                    <td>{$op_num}</td>
                                    <td>{$fecha}</td>
                                    <td>".e($identificacion)."</td>
                                    <td class='text-start'>".e($nombre)."</td>
                                    <td>".e($doc)."</td>
                                    <td class='text-end fw-bold'>".formatMoney($total_bs)."</td>
                                    <td class='text-end'>0.00</td>
                                    <td class='text-end'>".formatMoney($base_imponible)."</td>
                                    <td>".number_format($iva_tasa, 2)."%</td>
                                    <td class='text-end'>".formatMoney($monto_iva)."</td>
                                  </tr>";
                            $op_num++;
                        }
                        
                        echo "<tr class='bg-light fw-bold'>
                                <td colspan='5' class='text-end'>TOTALES:</td>
                                <td class='text-end'>".formatMoney($total_general)."</td>
                                <td class='text-end'>0.00</td>
                                <td class='text-end'>".formatMoney($total_base)."</td>
                                <td></td>
                                <td class='text-end'>".formatMoney($total_iva)."</td>
                              </tr>";
                    } else {
                        echo "<tr><td colspan='10' class='text-center text-muted py-5'>No hay registros para este periodo.</td></tr>";
                    }
                    ?>
                </tbody>
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
}
.fs-7 { font-size: 0.85rem; }
</style>

<script>
function exportToExcel(tableID, filename = ''){
    var downloadLink;
    var dataType = 'application/vnd.ms-excel';
    var tableSelect = document.getElementById(tableID);
    var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
    
    // Configurar descarga
    filename = filename?filename+'.xls':'excel_data.xls';
    
    // Crear enlace
    downloadLink = document.createElement("a");
    document.body.appendChild(downloadLink);
    
    if(navigator.msSaveOrOpenBlob){
        var blob = new Blob(['\ufeff', tableHTML], {
            type: dataType
        });
        navigator.msSaveOrOpenBlob( blob, filename);
    }else{
        downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
        downloadLink.download = filename;
        downloadLink.click();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
