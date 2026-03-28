<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
restrictAdmin();

$mes = $_GET['mes'] ?? date('m');
$anio = $_GET['anio'] ?? date('Y');
$formato = strtolower(trim((string)($_GET['formato'] ?? 'pdf')));
if (!in_array($formato, ['pdf','excel'], true)) $formato = 'pdf';

$stmtV = $pdo->prepare("\n    SELECT f.id, f.fecha_emision as fecha, f.factura_numero as documento, f.numero_control, c.nombre as cliente_nombre, c.cedula_rif as cliente_rif,\n           f.tasa_dia_usd_bs, f.total_usd, f.exento_bs, f.base_imponible_bs, f.iva_bs,\n           (f.total_usd * f.tasa_dia_usd_bs) as total_bs\n    FROM proformas f\n    JOIN clientes c ON f.cliente_id = c.id\n    WHERE f.tipo_documento = 'FACTURA' AND MONTH(f.fecha_emision) = ? AND YEAR(f.fecha_emision) = ? AND f.estado != 'ANULADO'\n    ORDER BY f.fecha_emision ASC, f.factura_numero ASC\n");
$stmtV->execute([$mes, $anio]);
$ventas = $stmtV->fetchAll(PDO::FETCH_ASSOC);

$stmtC = $pdo->prepare("\n    SELECT c.fecha, c.factura_numero as documento, c.numero_control, p.nombre as proveedor_nombre, p.rif as proveedor_rif,\n           c.total_bs, c.exento_bs, c.base_imponible_bs, c.iva_bs\n    FROM compras c\n    JOIN proveedores p ON c.proveedor_id = p.id\n    WHERE MONTH(c.fecha) = ? AND YEAR(c.fecha) = ? AND c.estado = 'PROCESADA'\n    ORDER BY c.fecha ASC\n");
$stmtC->execute([$mes, $anio]);
$compras = $stmtC->fetchAll(PDO::FETCH_ASSOC);

$empresa_nombre = getConfig('empresa_nombre', $pdo) ?: 'ELPROFE C.A.';
$empresa_rif = getConfig('empresa_rif', $pdo) ?: 'J-00000000-0';

$tv=0; $bv=0; $iv=0;
foreach($ventas as $r){ $tv+=(float)$r['total_bs']; $bv+=(float)$r['base_imponible_bs']; $iv+=(float)$r['iva_bs']; }
$tc=0; $bc=0; $ic=0;
foreach($compras as $r){ $tc+=(float)$r['total_bs']; $bc+=(float)$r['base_imponible_bs']; $ic+=(float)$r['iva_bs']; }

if ($formato === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Libro_Fiscal_' . $anio . '_' . $mes . '.xls"');
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Libro Fiscal <?php echo e($mes . '/' . $anio); ?></title>
<style>
body{font-family:Arial,Helvetica,sans-serif;margin:20px;color:#111}
.header{margin-bottom:18px}
.header h2{margin:0 0 4px 0}
.meta{font-size:12px;color:#444}
.section{margin-top:20px}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{border:1px solid #333;padding:6px 8px}
th{background:#eef2f7}
.right{text-align:right}
.total{font-weight:bold;background:#f8fafc}
.badge{display:inline-block;padding:2px 6px;background:#111827;color:#fff;border-radius:6px;font-size:11px}
@media print{.no-print{display:none}}
</style>
</head>
<body>
<div class="header">
  <h2>Libro Fiscal Profesional</h2>
  <div class="meta"><?php echo e($empresa_nombre); ?> · RIF: <?php echo e($empresa_rif); ?></div>
  <div class="meta">Periodo: <?php echo e($mes . '/' . $anio); ?> · Generado: <?php echo e(date('d/m/Y H:i')); ?></div>
</div>

<div class="section">
  <h3>Libro de Ventas <span class="badge">FACTURAS</span></h3>
  <table>
    <thead><tr><th>Fecha</th><th>Factura</th><th>RIF/CI</th><th>Cliente</th><th class="right">Total Bs</th><th class="right">Base Bs</th><th class="right">IVA Bs</th></tr></thead>
    <tbody>
      <?php if (!$ventas): ?>
      <tr><td colspan="7" style="text-align:center;color:#666">Sin facturas en el periodo.</td></tr>
      <?php endif; ?>
      <?php foreach($ventas as $r): ?>
      <tr>
        <td><?php echo e(date('d/m/Y', strtotime((string)$r['fecha']))); ?></td>
        <td><?php echo e($r['documento']); ?></td>
        <td><?php echo e($r['cliente_rif']); ?></td>
        <td><?php echo e($r['cliente_nombre']); ?></td>
        <td class="right"><?php echo number_format((float)$r['total_bs'],2,'.',','); ?></td>
        <td class="right"><?php echo number_format((float)$r['base_imponible_bs'],2,'.',','); ?></td>
        <td class="right"><?php echo number_format((float)$r['iva_bs'],2,'.',','); ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="total"><td colspan="4" class="right">TOTAL VENTAS</td><td class="right"><?php echo number_format($tv,2,'.',','); ?></td><td class="right"><?php echo number_format($bv,2,'.',','); ?></td><td class="right"><?php echo number_format($iv,2,'.',','); ?></td></tr>
    </tbody>
  </table>
</div>

<div class="section">
  <h3>Libro de Compras <span class="badge">PROCESADAS</span></h3>
  <table>
    <thead><tr><th>Fecha</th><th>Factura</th><th>RIF</th><th>Proveedor</th><th class="right">Total Bs</th><th class="right">Base Bs</th><th class="right">IVA Bs</th></tr></thead>
    <tbody>
      <?php if (!$compras): ?>
      <tr><td colspan="7" style="text-align:center;color:#666">Sin compras en el periodo.</td></tr>
      <?php endif; ?>
      <?php foreach($compras as $r): ?>
      <tr>
        <td><?php echo e(date('d/m/Y', strtotime((string)$r['fecha']))); ?></td>
        <td><?php echo e($r['documento']); ?></td>
        <td><?php echo e($r['proveedor_rif']); ?></td>
        <td><?php echo e($r['proveedor_nombre']); ?></td>
        <td class="right"><?php echo number_format((float)$r['total_bs'],2,'.',','); ?></td>
        <td class="right"><?php echo number_format((float)$r['base_imponible_bs'],2,'.',','); ?></td>
        <td class="right"><?php echo number_format((float)$r['iva_bs'],2,'.',','); ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="total"><td colspan="4" class="right">TOTAL COMPRAS</td><td class="right"><?php echo number_format($tc,2,'.',','); ?></td><td class="right"><?php echo number_format($bc,2,'.',','); ?></td><td class="right"><?php echo number_format($ic,2,'.',','); ?></td></tr>
    </tbody>
  </table>
</div>

<?php if ($formato === 'pdf'): ?>
<div class="no-print" style="margin-top:18px"><button onclick="window.print()">Imprimir / Guardar PDF</button></div>
<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),250));</script>
<?php endif; ?>
</body>
</html>
