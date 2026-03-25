<?php
// pages/nota_entrega.php - Renderizado de Proforma/Factura A4
require_once '../includes/db.php';
require_once '../includes/functions.php';

$id = intval($_GET['id'] ?? 0);
$is_demo = isset($_GET['demo']);

if (!$is_demo && $id <= 0) die("Proforma inválida");

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
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #000; background: #fff; }
        .page { max-width: 800px; margin: 0 auto; background: white; border: 1px solid #ddd; padding: 40px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2, h3, p { margin: 0; padding: 0; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .header-logo { font-size: 32px; font-weight: bold; }
        .header-info { text-align: right; }
        .doc-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .doc-title h2 { color: #cc0000; font-size: 20px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.border th, table.border td { border: 1px solid #000; padding: 6px 10px; font-size: 12px; }
        table.border th { background-color: #f8f9fa; font-weight: bold; text-align: center; }
        .td-label { background-color: #f8f9fa; font-weight: bold; width: 20%; }
        
        .totals { float: right; width: 40%; }
        .totals table th, .totals table td { border: 1px solid #000; padding: 6px; font-size: 13px; }
        .totals table th { text-align: right; background-color: #f8f9fa; }
        .totals table td { text-align: right; font-weight: bold; }
        
        .footer-signatures { display: flex; justify-content: space-between; margin-top: 60px; font-size: 12px; font-weight: bold; }
        .signature-line { border-bottom: 1px solid #000; width: 300px; display: inline-block; margin-left: 10px; }
        
        .btn-print { display: block; width: 200px; margin: 0 auto 20px auto; padding: 10px; background: #0d6efd; color: white; text-align: center; font-weight: bold; text-decoration: none; border-radius: 5px; cursor: pointer; border: none; }
        
        @media print {
            body { padding: 0; background: none; }
            .page { border: none; box-shadow: none; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="no-print" style="margin: 0 auto 20px auto; max-width: 800px; text-align: center; display: flex; gap: 10px; justify-content: center;">
    <button onclick="window.print()" class="btn-print" style="margin: 0; flex: 1; max-width: 250px;">🖨️ Imprimir PDF / A4</button>
    <button id="btn-wa" onclick="shareAsImage(<?php echo $id; ?>, 'btn-wa', 'nota_entrega')" class="btn-print" style="margin: 0; flex: 1; max-width: 250px; background: #198754;">📱 Compartir Imagen WA</button>
    <a href="ticket.php?id=<?php echo $id; ?>" class="btn-print" style="margin: 0; flex: 1; max-width: 250px; background: #0dcaf0; color: #000;">🧾 Ver Ticket (58mm)</a>
</div>
<div class="page">
    <div class="header">
        <div class="header-logo">LOGO</div>
        <div class="header-info">
            <h2 style="font-size: 22px;"><?php echo htmlspecialchars(strtoupper($empresa_nombre)); ?> CA RIF: <?php echo htmlspecialchars($empresa_rif); ?></h2>
            <p style="font-size: 14px; margin-top: 5px;"><?php echo htmlspecialchars($empresa_dir); ?></p>
            <p style="font-size: 14px;">Tel: <?php echo htmlspecialchars($empresa_tel); ?></p>
        </div>
    </div>
    
    <div class="doc-title">
        <h2><?php echo $titulo; ?> Nro. <?php echo str_pad($id, 6, "0", STR_PAD_LEFT); ?></h2>
        <div style="font-size: 14px; font-weight: bold;">
            Fecha de Emisión: <?php echo date('d/m/Y', strtotime($proforma['fecha_emision'])); ?>
        </div>
    </div>
    
    <table class="border">
        <tr>
            <td class="td-label">Nombre o Razón social.</td>
            <td colspan="3"><?php echo htmlspecialchars($proforma['cliente_nombre']); ?></td>
            <td class="td-label" style="width: 15%;">C.I. / R.I.F.</td>
            <td style="width: 25%;"><?php echo htmlspecialchars($proforma['cedula_rif']); ?></td>
        </tr>
        <tr>
            <td class="td-label">Domicilio Fiscal</td>
            <td colspan="5"><?php echo htmlspecialchars($proforma['cliente_dir']); ?></td>
        </tr>
        <tr>
            <td class="td-label">Teléfono:</td>
            <td colspan="3"><?php echo htmlspecialchars($proforma['cliente_tel']); ?></td>
            <td class="td-label">Condiciones Pago</td>
            <td><?php echo $proforma['tipo_documento'] === 'PROFORMA' && $proforma['estado'] === 'PENDIENTE' ? 'CRÉDITO' : 'CONTADO'; ?></td>
        </tr>
    </table>
    
    <table class="border">
        <thead>
            <tr>
                <th width="15%">Código</th>
                <th width="40%">Concepto o Descripción</th>
                <th width="10%">Unidad</th>
                <th width="10%">Cant.</th>
                <th width="12%">P.U. ($)</th>
                <th width="13%">Total ($)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($detalles as $d): ?>
            <tr>
                <td style="text-align: center;"><?php echo htmlspecialchars($d['codigo_barras']); ?></td>
                <td><?php echo htmlspecialchars($d['producto_nombre'] . " " . $d['nombre_presentacion']); ?></td>
                <td style="text-align: center;">Und</td>
                <td style="text-align: center;"><?php echo round($d['cantidad'], 2); ?></td>
                <td style="text-align: right;"><?php echo number_format($d['precio_unitario_usd'], 2, ',', '.'); ?></td>
                <td style="text-align: right;"><?php echo number_format($d['subtotal_usd'], 2, ',', '.'); ?></td>
            </tr>
            <?php endforeach; ?>
            <!-- Add empty rows if needed to pad the table visually, mimicking the original -->
            <?php for($i = 0; $i < (10 - count($detalles)); $i++): ?>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            <?php endfor; ?>
        </tbody>
    </table>
    
    <div style="display: flex; justify-content: space-between;">
        <div style="width: 55%;">
            <table class="border">
                <tr><td class="td-label">Observaciones:</td></tr>
                <tr><td style="height: 60px; vertical-align: top;">
                    * Tasa de Cambio BCV Aplicada: <?php echo number_format($tasa_dia, 4, ',', '.'); ?> Bs/$<br>
                    * Monto Total Bs: <?php echo number_format($total_con_iva, 2, ',', '.'); ?> Bs
                </td></tr>
            </table>
        </div>
        <div class="totals">
            <table>
                <tr>
                    <th width="60%">Total General $</th>
                    <td><?php echo number_format($proforma['total_usd'], 2, ',', '.'); ?></td>
                </tr>
                <tr>
                    <th>Exento de IVA $</th>
                    <td>0,00</td>
                </tr>
                <tr>
                    <th>Total a Pagar $</th>
                    <td><?php echo number_format($proforma['total_usd'], 2, ',', '.'); ?></td>
                </tr>
            </table>
        </div>
    </div>
    <div style="clear: both;"></div>
    
    <div class="footer-signatures">
        <div>AUTORIZADO POR: <span class="signature-line"></span></div>
        <div>RECIBIDO POR / C.I.: <span class="signature-line"></span></div>
    </div>
    
    <div style="text-align: center; color: #cc0000; font-size: 11px; font-weight: bold; margin-top: 20px;">
        Original - Habilitada para Amparar el Traslado de Bienes
    </div>
</div>

<script>
    if(new URLSearchParams(window.location.search).has('print')) {
        setTimeout(() => { window.print(); }, 500);
    }

    async function shareAsImage(id, btnId, tipo) {
        let btn = document.getElementById(btnId);
        let origHtml = btn.innerHTML;
        btn.innerHTML = '⏳ Procesando...';
        btn.disabled = true;

        try {
            if(typeof html2canvas === 'undefined') {
                await new Promise((resolve) => {
                    let script = document.createElement('script');
                    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
                    script.onload = resolve;
                    document.head.appendChild(script);
                });
            }

            // Ocultar area de botones superior
            let noPrint = document.querySelector('.no-print');
            noPrint.style.display = 'none';

            let canvas = await html2canvas(document.body, { backgroundColor: '#ffffff', scale: 2 });
            noPrint.style.display = 'flex'; // restaurar

            let base64 = canvas.toDataURL('image/png');

            let formData = new FormData();
            formData.append('id', id);
            formData.append('tipo', tipo);
            formData.append('image', base64);
            
            let res = await fetch('/ELPROFE/api/guardar_ticket.php', { method: 'POST', body: formData });
            let json = await res.json();
            
            if(!json.success) throw new Error(json.message);

            canvas.toBlob(async (blob) => {
                let file = new File([blob], `${tipo}_${id}.png`, { type: 'image/png' });
                
                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                    await navigator.share({
                        title: `Documento #${id}`,
                        text: 'Adjunto Documento Mercantil',
                        files: [file]
                    });
                } else {
                    let wa_url = "https://wa.me/?text=" + encodeURIComponent("Saludos! Adjunto comprobante virtual en este link:\n\n" + json.url);
                    window.open(wa_url, "_blank");
                }
            });

        } catch(e) {
            alert('Error al generar la imagen. ' + e.message);
        } finally {
            btn.innerHTML = origHtml;
            btn.disabled = false;
        }
    }
</script>
</body>
</html>
