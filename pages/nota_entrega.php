<?php
// pages/nota_entrega.php - Renderizado de Proforma/Factura A4
require_once '../includes/db.php';
require_once '../includes/functions.php';

$id = intval($_GET['id'] ?? 0);
$is_demo = isset($_GET['demo']);
 $shareToken = isset($_GET['share']) ? (string)$_GET['share'] : '';

if (!$is_demo && $id <= 0) die("Proforma inválida");

// Acceso seguro (sin login) solo si el token firmado es válido.
if (!$is_demo) {
    if (!validateShareLinkToken($id, $shareToken)) {
        checkLogin();
    }
}

// Datos de la empresa
$empresa_nombre = getConfig('empresa_nombre', $pdo) ?: 'ELPROFE POS';
$empresa_rif = getConfig('empresa_rif', $pdo) ?: 'J-00000000-0';
$empresa_dir = getConfig('empresa_direccion', $pdo) ?: '';
$empresa_tel = getConfig('empresa_telefono', $pdo) ?: '';
$empresa_iva_pct = floatval(getConfig('empresa_iva', $pdo) ?: 16.00);

if ($is_demo) {
    $id = 999999;
    $proforma = [
        'id' => $id,
        'cliente_nombre' => 'María Silva (DEMO)',
        'cedula_rif' => 'V-98765432',
        'cliente_dir' => 'Av. Principal, Edificio Prueba',
        'cliente_tel' => '0414-0000000',
        'vendedor' => 'Administrador Global',
        'tipo_documento' => 'NOTA DE VENTA',
        'estado' => 'PAGADO',
        'fecha_emision' => date('Y-m-d H:i:s'),
        'total_usd' => 45.00,
        'tasa_dia_usd_bs' => 36.50
    ];
    $detalles = [
        ['codigo_barras' => '750123456', 'cantidad' => 2, 'producto_nombre' => 'Producto Prueba 1', 'nombre_presentacion' => 'Unidad', 'precio_unitario_usd' => 15.00, 'subtotal_usd' => 30.00],
        ['codigo_barras' => '750987654', 'cantidad' => 3, 'producto_nombre' => 'Accesorios Demo 2', 'nombre_presentacion' => 'Pack', 'precio_unitario_usd' => 5.00, 'subtotal_usd' => 15.00]
    ];
} else {
    // Cargar Proforma
    $stmt = $pdo->prepare("
        SELECT p.*, c.nombre as cliente_nombre, c.cedula_rif, c.direccion as cliente_dir, c.telefono as cliente_tel, u.nombre as vendedor 
        FROM proformas p
        JOIN clientes c ON p.cliente_id = c.id
        JOIN usuarios u ON p.cajero_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $proforma = $stmt->fetch();

    if (!$proforma) die("Documento no encontrado");

    // Cargar Detalles
    $stmtD = $pdo->prepare("
        SELECT pd.*, pres.nombre_presentacion, pres.codigo_barras, prod.nombre as producto_nombre
        FROM proforma_detalles pd
        JOIN presentaciones pres ON pd.presentacion_id = pres.id
        JOIN productos prod ON pres.producto_id = prod.id
        WHERE pd.proforma_id = ?
    ");
    $stmtD->execute([$id]);
    $detalles = $stmtD->fetchAll();
}

$tasa_dia = $proforma['tasa_dia_usd_bs'];
$total_con_iva = $proforma['total_usd'] * $tasa_dia;
$base_imponible = $total_con_iva / (1 + ($empresa_iva_pct / 100));
$monto_iva = $total_con_iva - $base_imponible;

$titulo = $proforma['tipo_documento'] === 'FACTURA' ? 'FACTURA' : 'NOTA DE ENTREGA';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?> #<?php echo str_pad($id, 6, "0", STR_PAD_LEFT); ?></title>
    <meta name="csrf-token" content="<?php echo e(generateCsrfToken()); ?>">
    <style>
        :root { --brand-blue: #002157; --brand-red: #d3101e; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #eaedf1; color: #333; line-height: 1.5; padding: 30px 15px; }
        
        /* Contenedor Hoja A4 */
        .page { 
            width: 210mm; /* Forzamos ancho A4 para captura fiel */
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            padding: 15mm;
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
            position: relative;
            border-radius: 4px;
        }

        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; border-bottom: 3px solid var(--brand-blue); padding-bottom: 15px; }
        .header-logo { width: 120px; }
        .header-info { text-align: right; }
        .header-info h1 { font-size: 20px; color: var(--brand-blue); font-weight: 800; margin-bottom: 5px; }
        .header-info p { font-size: 13px; color: #555; margin: 2px 0; }

        .doc-meta { display: flex; justify-content: space-between; margin-bottom: 25px; background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 5px solid var(--brand-red); }
        .doc-meta h2 { font-size: 18px; color: var(--brand-red); font-weight: 700; margin: 0; }
        .doc-meta div { font-size: 14px; font-weight: 600; text-align: right; }

        .client-box { margin-bottom: 25px; }
        .client-box h3 { font-size: 14px; text-transform: uppercase; color: #777; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .grid-client { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px; }
        .grid-item { margin-bottom: 5px; }
        .grid-item span { font-weight: 700; color: #555; min-width: 100px; display: inline-block; }

        table.items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table.items-table th { background: var(--brand-blue); color: #fff; padding: 10px; font-size: 12px; text-transform: uppercase; text-align: left; }
        table.items-table td { padding: 10px; border-bottom: 1px solid #eee; font-size: 13px; }
        table.items-table tr:nth-child(even) { background: #fafafa; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .footer-area { display: grid; grid-template-columns: 1fr 250px; gap: 40px; }
        .obs-box { font-size: 12px; }
        .obs-content { width: 100%; height: 100px; border: 1px dashed #ccc; padding: 10px; border-radius: 4px; background: #fffcf0; resize: none; overflow: hidden; }
        
        .totals-box { font-size: 14px; }
        .total-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .total-row.grand-total { border-bottom: none; font-size: 18px; color: var(--brand-blue); font-weight: 800; padding-top: 15px; }
        
        .btn-panel { display: flex; gap: 15px; justify-content: center; margin-bottom: 30px; }
        .btn-ui { padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; border: none; font-size: 14px; transition: transform 0.2s; text-decoration: none; }
        .btn-ui:hover { transform: scale(1.03); }
        .btn-blue { background: #007bff; color: white; }
        .btn-green { background: #28a745; color: white; }
        .btn-cyan { background: #17a2b8; color: white; }

        @media screen and (max-width: 220mm) {
            body { padding: 0; background: #fff; }
            .page { width: 100%; min-height: auto; box-shadow: none; border-radius: 0; padding: 10px; }
            .header-logo { width: 80px; }
            .grid-client { grid-template-columns: 1fr; }
            .footer-area { grid-template-columns: 1fr; gap: 20px; }
            .totals-box { width: 100%; }
        }

        @media print {
            body { padding: 0; background: none; }
            .page { width: 100%; height: auto; margin: 0; padding: 0; box-shadow: none; }
            .no-print { display: none !important; }
            .btn-panel { display: none !important; }
        }
    </style>
</head>
<body>

<div class="btn-panel no-print">
    <button onclick="window.print()" class="btn-ui btn-blue">🖨️ Imprimir A4</button>
    <button id="btn-wa" onclick="shareAsImage(<?php echo $id; ?>, 'btn-wa', 'nota_entrega')" class="btn-ui btn-green">📱 WhatsApp Imagen</button>
    <a href="ticket.php?id=<?php echo $id; ?><?php echo $shareToken ? '&share=' . rawurlencode($shareToken) : ''; ?>" class="btn-ui btn-cyan">🧾 Ver Ticket Recibo</a>
</div>

<div class="page" id="invoice-content">
    <div class="header">
        <img src="../assets/img/logo.png" alt="Logotipo" class="header-logo">
        <div class="header-info">
            <h1><?php echo e(strtoupper($empresa_nombre)); ?></h1>
            <p><strong>RIF:</strong> <?php echo e($empresa_rif); ?></p>
            <p><?php echo e($empresa_dir); ?></p>
            <p><strong>Tel:</strong> <?php echo e($empresa_tel); ?></p>
        </div>
    </div>

    <div class="doc-meta">
        <h2><?php echo $titulo; ?> Nro. <?php echo str_pad($id, 6, "0", STR_PAD_LEFT); ?></h2>
        <div>
            FECHA DE EMISIÓN:<br>
            <?php echo date('d/m/Y', strtotime($proforma['fecha_emision'])); ?>
        </div>
    </div>

    <div class="client-box">
        <h3>Información del Cliente</h3>
        <div class="grid-client">
            <div class="grid-item"><span>RAZÓN SOCIAL:</span> <?php echo e($proforma['cliente_nombre']); ?></div>
            <div class="grid-item"><span>C.I. / R.I.F.:</span> <?php echo e($proforma['cedula_rif']); ?></div>
            <div class="grid-item"><span>DIRECCIÓN:</span> <?php echo e($proforma['cliente_dir']); ?></div>
            <div class="grid-item"><span>TELÉFONO:</span> <?php echo e($proforma['cliente_tel']); ?></div>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th width="15%">CÓDIGO</th>
                <th width="45%">DESCRIPCIÓN</th>
                <th width="10%" class="text-center">CANT.</th>
                <th width="15%" class="text-right">P.U. ($)</th>
                <th width="15%" class="text-right">TOTAL ($)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($detalles as $d): ?>
            <tr>
                <td class="text-center"><?php echo e($d['codigo_barras']); ?></td>
                <td><?php echo e($d['producto_nombre'] . " (" . $d['nombre_presentacion'] . ")"); ?></td>
                <td class="text-center"><?php echo round($d['cantidad'], 2); ?></td>
                <td class="text-right"><?php echo number_format($d['precio_unitario_usd'], 2, ',', '.'); ?></td>
                <td class="text-right"><?php echo number_format($d['subtotal_usd'], 2, ',', '.'); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php for($i = count($detalles); $i < 8; $i++): ?>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <div class="footer-area">
        <div class="obs-box">
            <strong>Observaciones:</strong><br>
            <div id="obs_box" class="obs-content" contenteditable="true" placeholder="Haga clic para escribir notas antes de imprimir..."></div>
        </div>
        <div class="totals-box">
            <div class="total-row">
                <span>SUBTOTAL:</span>
                <span>$ <?php echo number_format($proforma['total_usd'], 2, ',', '.'); ?></span>
            </div>
            <div class="total-row">
                <span>IVA (0%):</span>
                <span>$ 0,00</span>
            </div>
            <div class="total-row grand-total">
                <span>TOTAL A PAGAR:</span>
                <span>$ <?php echo number_format($proforma['total_usd'], 2, ',', '.'); ?></span>
            </div>
            <div style="font-size: 11px; text-align: right; margin-top: 10px; color: #777;">
                Equivalente aprox: Bs. <?php echo number_format($proforma['total_usd'] * $tasa_dia, 2, ',', '.'); ?><br>
                (Tasa: <?php echo number_format($tasa_dia, 2); ?>)
            </div>
        </div>
    </div>
</div>

<!-- Scripts al final para no bloquear el renderizado inicial -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/ELPROFE/assets/js/share_comprobante.js?v=<?php echo time(); ?>"></script>

<script>
    const idDoc = <?php echo $id; ?>;
    const obsBox = document.getElementById('obs_box');
    const cacheKey = 'obs_' + idDoc;

    if (localStorage.getItem(cacheKey)) {
        obsBox.textContent = localStorage.getItem(cacheKey);
    }
    
    obsBox.addEventListener('input', () => {
        localStorage.setItem(cacheKey, obsBox.textContent || '');
    });

    async function shareAsImage(id, btnId, tipo) {
        Swal.fire({
            title: 'Capturando comprobante',
            text: 'Preparando imagen de alta calidad...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const captureEl = document.getElementById('invoice-content');
            
            // Garantizamos que el elemento sea visible y tenga dimensiones para la captura
            const canvas = await html2canvas(captureEl, { 
                backgroundColor: '#ffffff', 
                scale: 2,
                useCORS: true,
                logging: false,
                windowWidth: 1200 // Simular ancho de escritorio para captura consistente
            });

            canvas.toBlob(async (blob) => {
                if (!blob) throw new Error('Fallo al generar archivo temporal.');
                
                // 1. Guardar copia en el servidor (Respaldo)
                const formData = new FormData();
                formData.append('id', id);
                formData.append('tipo', tipo);
                formData.append('image', canvas.toDataURL('image/png'));
                
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const res = await fetch('/ELPROFE/api/guardar_ticket.php', { 
                    method: 'POST', 
                    headers: { 'X-CSRF-Token': csrf },
                    body: formData 
                });
                const json = await res.json();
                
                Swal.close();

                // 2. Intentar flujo nativo de compartir
                const file = new File([blob], `${tipo}_${id}.png`, { type: 'image/png' });
                const shared = await elprofeTryShareImageFile(file, `Nota #${id}`, 'Comprobante ElProfe');
                
                if (!shared) {
                    elprofeFallbackShareComprobante(canvas, id, tipo, json.url);
                }
            }, 'image/png');

        } catch(e) {
            Swal.fire('Error', 'Fallo técnico: ' + e.message, 'error');
        }
    }
</script>
</body>
</html>
