<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
restrictAdmin();

$fechaServidor = $pdo->query("SELECT DATE(NOW())")->fetchColumn() ?: date('Y-m-d');
$desde = $_GET['desde'] ?? $fechaServidor;
$hasta = $_GET['hasta'] ?? $fechaServidor;
$usuario_id = $_GET['usuario_id'] ?? '';
$metodo_id = $_GET['metodo_id'] ?? '';
$tipo = $_GET['tipo'] ?? '';
$formato = strtolower(trim((string)($_GET['formato'] ?? 'pdf')));
if (!in_array($formato, ['pdf','excel'], true)) $formato = 'pdf';

$where_clauses = ["DATE(m.fecha) BETWEEN :desde AND :hasta"];
$params = [':desde' => $desde, ':hasta' => $hasta];
if ($usuario_id !== '') { $where_clauses[] = "s.usuario_id = :usuario_id"; $params[':usuario_id'] = $usuario_id; }
if ($metodo_id !== '') { $where_clauses[] = "m.metodo_pago_id = :metodo_id"; $params[':metodo_id'] = $metodo_id; }
if ($tipo !== '') { $where_clauses[] = "m.tipo_movimiento = :tipo"; $params[':tipo'] = $tipo; }
$where_sql = implode(' AND ', $where_clauses);

$stmt_totales = $pdo->prepare("\n    SELECT mp.moneda_base,\n           COALESCE(SUM(CASE WHEN m.tipo_movimiento = 'ENTRADA' THEN m.monto_usd ELSE -m.monto_usd END), 0) as balance_usd,\n           COALESCE(SUM(CASE WHEN m.tipo_movimiento = 'ENTRADA' THEN m.monto_bs ELSE -m.monto_bs END), 0) as balance_bs\n    FROM movimientos_caja m\n    JOIN metodos_pago mp ON m.metodo_pago_id = mp.id\n    LEFT JOIN sesiones_caja s ON m.sesion_caja_id = s.id\n    WHERE $where_sql\n    GROUP BY mp.moneda_base\n");
$stmt_totales->execute($params);
$totales = $stmt_totales->fetchAll(PDO::FETCH_ASSOC);
$total_usd = 0; $total_bs = 0;
foreach($totales as $t){ if($t['moneda_base']==='USD') $total_usd += (float)$t['balance_usd']; else $total_bs += (float)$t['balance_bs']; }

$stmt_mov = $pdo->prepare("\n    SELECT m.*, mp.nombre as metodo_nombre, u.nombre as cajero_nombre, u.username, s.id as id_sesion\n    FROM movimientos_caja m\n    JOIN metodos_pago mp ON m.metodo_pago_id = mp.id\n    LEFT JOIN sesiones_caja s ON m.sesion_caja_id = s.id\n    LEFT JOIN usuarios u ON s.usuario_id = u.id\n    WHERE $where_sql\n    ORDER BY m.fecha DESC\n    LIMIT 5000\n");
$stmt_mov->execute($params);
$movimientos = $stmt_mov->fetchAll(PDO::FETCH_ASSOC);

if ($formato === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Auditoria_Caja_' . date('Ymd_His') . '.xls"');
}
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Auditoría de Caja</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;margin:20px;color:#111}
.header h2{margin:0 0 6px 0}
.meta{font-size:12px;color:#444}
.kpis{display:flex;gap:10px;margin:14px 0}.kpi{border:1px solid #333;padding:8px 10px;border-radius:6px;font-size:13px}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{border:1px solid #333;padding:6px 8px}
th{background:#eef2f7}.right{text-align:right}.in{color:#0f766e}.out{color:#b91c1c}
@media print{.no-print{display:none}}
</style></head>
<body>
<div class="header">
  <h2>Auditoría de Caja Profesional</h2>
  <div class="meta">Rango: <?php echo e($desde); ?> a <?php echo e($hasta); ?> · Generado: <?php echo e(date('d/m/Y H:i')); ?></div>
</div>
<div class="kpis">
  <div class="kpi"><strong>Balance USD:</strong> $<?php echo number_format($total_usd,2,'.',','); ?></div>
  <div class="kpi"><strong>Balance Bs:</strong> Bs <?php echo number_format($total_bs,2,'.',','); ?></div>
</div>
<table>
  <thead><tr><th>Fecha</th><th>Cajero/Sesión</th><th>Método</th><th>Referencia</th><th>Tipo</th><th class="right">USD</th><th class="right">Bs</th></tr></thead>
  <tbody>
    <?php if (!$movimientos): ?>
    <tr><td colspan="7" style="text-align:center;color:#666">Sin movimientos para los filtros.</td></tr>
    <?php endif; ?>
    <?php foreach($movimientos as $m): ?>
    <?php $isIn = ((string)$m['tipo_movimiento'] === 'ENTRADA'); ?>
    <tr>
      <td><?php echo e($m['fecha']); ?></td>
      <td><?php echo e(($m['cajero_nombre'] ?: 'Sistema') . ' (#' . ($m['id_sesion'] ?: '-') . ')'); ?></td>
      <td><?php echo e($m['metodo_nombre']); ?></td>
      <td><?php echo e(($m['referencia_tabla'] ?: '-') . ' #' . ($m['referencia_id'] ?: '-')); ?></td>
      <td><?php echo e($m['tipo_movimiento']); ?></td>
      <td class="right <?php echo $isIn ? 'in' : 'out'; ?>"><?php echo ($isIn ? '+' : '-') . '$' . number_format((float)$m['monto_usd'],2,'.',','); ?></td>
      <td class="right <?php echo $isIn ? 'in' : 'out'; ?>"><?php echo ($isIn ? '+' : '-') . 'Bs ' . number_format((float)$m['monto_bs'],2,'.',','); ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php if ($formato === 'pdf'): ?>
<div class="no-print" style="margin-top:14px"><button onclick="window.print()">Imprimir / Guardar PDF</button></div>
<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),250));</script>
<?php endif; ?>
</body></html>
