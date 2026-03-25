<?php
// pages/ticket.php - Renderizado de Ticket Térmico 58mm/80mm
require_once '../includes/db.php';
require_once '../includes/functions.php';
// Permitir imprimir desde el Dashboard sin login estricto si se desea compartir público? No, mejor asegurar.
// Pero si lo comparten por WhatsApp, debe ser visible públicamente? Sí! Para eso pasamos un hash u ocultamos el login.
// Pero la instrucción dice que se comparta por Whatsapp. Un PDF generado o enlace público.
// Para no complicar con hashes, por ahora dejaremos checkLogin() desactivado solo si viene un parametro "public=1" y validamos.
// Mejor lo dejamos abierto pero protegido si no saben el ID, o simplemente pedimos login. Si envían por whatsapp, el cliente no lo podrá abrir si pide login!
// Ah! El usuario dijo "que el comprobante pueda compartirse por whatsapp". Lo más común es compartir una captura o un PDF o texto.
// Podemos generar el PDF o solo un link web abierto (con ID encriptado).
// Lo mejor es hacer el archivo público si se pasa el ID correcto.

$id = intval($_GET['id'] ?? 0);
$is_demo = isset($_GET['demo']);

if (!$is_demo && $id <= 0) die("Proforma inválida");

// Datos de la empresa
$empresa_nombre = getConfig('empresa_nombre', $pdo) ?: 'ELPROFE POS';
$empresa_rif = getConfig('empresa_rif', $pdo) ?: 'J-00000000-0';
$empresa_dir = getConfig('empresa_direccion', $pdo) ?: '';
$empresa_iva_pct = floatval(getConfig('empresa_iva', $pdo) ?: 16.00);

if ($is_demo) {
    // Generar datos flotantes de demostración genérica
    $id = 999999;
    $proforma = [
        'id' => $id,
        'cliente_nombre' => 'Juan Pérez (DEMO)',
        'cedula_rif' => 'V-12345678',
        'vendedor' => 'Administrador Global',
        'tipo_documento' => 'FACTURA',
        'fecha_emision' => date('Y-m-d H:i:s'),
        'total_usd' => 25.50,
        'tasa_dia_usd_bs' => 36.50
    ];
    $detalles = [
        ['cantidad' => 2, 'producto_nombre' => 'Producto de Prueba A', 'nombre_presentacion' => 'Caja', 'precio_unitario_usd' => 10.00, 'subtotal_usd' => 20.00],
        ['cantidad' => 1, 'producto_nombre' => 'Producto de Prueba B', 'nombre_presentacion' => 'Unidad', 'precio_unitario_usd' => 5.50, 'subtotal_usd' => 5.50]
    ];
    $pagos = [
        ['metodo' => 'PAGO MÓVIL', 'monto_entregado_bs' => 930.75, 'monto_equivalente_usd' => 25.50]
    ];
} else {
    // Cargar Proforma Real
    $stmt = $pdo->prepare("
        SELECT p.*, c.nombre as cliente_nombre, c.cedula_rif, u.nombre as vendedor 
        FROM proformas p
        JOIN clientes c ON p.cliente_id = c.id
        JOIN usuarios u ON p.cajero_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $proforma = $stmt->fetch();

    if (!$proforma) die("Documento no encontrado");

    // Cargar Detalles Reales
    $stmtD = $pdo->prepare("
        SELECT pd.*, pres.nombre_presentacion, prod.nombre as producto_nombre
        FROM proforma_detalles pd
        JOIN presentaciones pres ON pd.presentacion_id = pres.id
        JOIN productos prod ON pres.producto_id = prod.id
        WHERE pd.proforma_id = ?
    ");
    $stmtD->execute([$id]);
    $detalles = $stmtD->fetchAll();

    // Cargar Pagos Reales
    $stmtP = $pdo->prepare("
        SELECT a.id, mp.nombre as metodo, pd.monto_entregado_bs, pd.monto_entregado_usd, pd.monto_equivalente_usd
        FROM abonos a
        JOIN pagos_detalles pd ON a.id = pd.abono_id
        JOIN metodos_pago mp ON pd.metodo_pago_id = mp.id
        WHERE a.proforma_id = ?
    ");
    $stmtP->execute([$id]);
    $pagos = $stmtP->fetchAll();
}

$tasa_dia = $proforma['tasa_dia_usd_bs'];

// Calculo de IVA "Al vuelo" (Dado que asumimos precios con IVA o base)
// Simplificaremos: el subtotal es la Base, y le extraemos o le sumamos el IVA dependiendo de la contabilidad.
// Vamos a considerar que el Total ya incluye IVA para mostrarlo como en SENIAT (Extrayendo la Base).
$total_con_iva = $proforma['total_usd'] * $tasa_dia;
$base_imponible = $total_con_iva / (1 + ($empresa_iva_pct / 100));
$monto_iva = $total_con_iva - $base_imponible;

$es_whatsapp = isset($_GET['wa']);
if ($es_whatsapp) {
    // Ya no lo procesamos por texto puro, delegamos a Javascript con auto-launch
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo str_pad($id, 6, "0", STR_PAD_LEFT); ?></title>
    <style>
        @page { margin: 0; }
        body { font-family: 'Courier New', Courier, monospace; font-size: 11px; margin: 0; padding: 10px; width: 300px; color: #000; }
        .center { text-align: center; }
        .right { text-align: right; }
        .left { text-align: left; }
        .bold { font-weight: bold; }
        .sep { border-bottom: 1px dashed #000; margin: 5px 0; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 2px 0; vertical-align: top; }
        h3, h4, p { margin: 2px 0; }
        .bg-gray { background-color: #eee; -webkit-print-color-adjust: exact; }
        @media print {
            body { width: 100%; margin: 0; padding: 5px; }
            .no-print { display: none !important; }
        }
        .btn { display: inline-block; padding: 10px 15px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px; margin-bottom: 10px; font-family: sans-serif; }
        .btn-success { background: #198754; }
        .btn-warning { background: #ffc107; color: #000; }
    </style>
</head>
<body>

<div class="no-print center" style="margin-bottom: 20px;">
    <button class="btn" onclick="window.print()"><i class="fa-solid fa-print"></i> 🖨️ Imprimir Ticket</button>
    <button class="btn btn-success" id="btn-wa" onclick="shareAsImage(<?php echo $id; ?>, 'btn-wa', 'ticket')">🖼️ Capturar WA(Imagen)</button>
    <a href="nota_entrega.php?id=<?php echo $id; ?>" class="btn btn-warning">📄 Ver Nota Entrega</a>
</div>

<div class="center bold">
    <h3>SENIAT</h3>
    <div>RIF <?php echo htmlspecialchars($empresa_rif); ?></div>
    <div><?php echo htmlspecialchars(strtoupper($empresa_nombre)); ?></div>
</div>
<div class="center" style="margin-bottom: 5px;">
    <?php echo nl2br(htmlspecialchars($empresa_dir)); ?>
    <div>CAJA 01</div>
</div>

<div class="sep"></div>

<div><span class="bold">Información del Cliente</span></div>
<div>Cliente: <?php echo htmlspecialchars($proforma['cliente_nombre']); ?></div>
<div>RIF/C.I.: <?php echo htmlspecialchars($proforma['cedula_rif']); ?></div>
<div>Vendedor: <?php echo htmlspecialchars($proforma['vendedor']); ?></div>

<div class="center bold" style="margin: 8px 0;">
    <h4><?php echo $proforma['tipo_documento'] === 'FACTURA' ? 'FACTURA' : 'PROFORMA / NOTA DE VENTA'; ?></h4>
</div>

<table class="table">
    <tr>
        <td class="left">FACTURA:</td>
        <td class="right"><?php echo str_pad($id, 8, "0", STR_PAD_LEFT); ?></td>
    </tr>
    <tr>
        <td class="left">FECHA:</td>
        <td class="right"><?php echo date('d-m-Y H:i', strtotime($proforma['fecha_emision'])); ?></td>
    </tr>
</table>

<table class="table" style="margin-top: 5px;">
    <thead>
        <tr class="bg-gray bold">
            <th class="left" width="50%">DESCRIPCIÓN</th>
            <th class="center" width="15%">CANT</th>
            <th class="right" width="35%">TOTAL(Bs)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($detalles as $d): 
            $subtotal_bs = $d['subtotal_usd'] * $tasa_dia;
            $precio_bs = $d['precio_unitario_usd'] * $tasa_dia;
        ?>
        <tr>
            <td colspan="3"><?php echo htmlspecialchars($d['producto_nombre'] . " " . $d['nombre_presentacion']); ?></td>
        </tr>
        <tr>
            <td class="left" style="padding-left: 5px;">(E) <?php echo number_format($precio_bs, 2, ',', '.'); ?></td>
            <td class="center"><?php echo round($d['cantidad'], 2); ?></td>
            <td class="right"><?php echo number_format($subtotal_bs, 2, ',', '.'); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="sep"></div>

<table class="table">
    <tr>
        <td class="left">EXENTO (E)</td>
        <td class="right">0,00</td>
    </tr>
    <tr>
        <td class="left">BI G (<?php echo $empresa_iva_pct; ?>%)</td>
        <td class="right"><?php echo number_format($base_imponible, 2, ',', '.'); ?></td>
    </tr>
    <tr>
        <td class="left">IVA G (<?php echo $empresa_iva_pct; ?>%)</td>
        <td class="right"><?php echo number_format($monto_iva, 2, ',', '.'); ?></td>
    </tr>
    <tr class="bold" style="font-size: 13px;">
        <td class="left">TOTAL FACTURA (Bs)</td>
        <td class="right"><?php echo number_format($total_con_iva, 2, ',', '.'); ?></td>
    </tr>
    <tr class="bold">
        <td class="left">TOTAL FACTURA ($)</td>
        <td class="right"><?php echo number_format($proforma['total_usd'], 2, ',', '.'); ?></td>
    </tr>
</table>

<div class="center bold" style="margin-top: 10px;">FORMA DE PAGO</div>
<table class="table">
    <?php if (empty($pagos)) { ?>
        <tr><td class="left">CRÉDITO (FIADO)</td><td class="right"><?php echo number_format($total_con_iva, 2, ',', '.'); ?></td></tr>
    <?php } else { 
        foreach ($pagos as $p) { ?>
        <tr>
            <td class="left"><?php echo strtoupper($p['metodo']); ?></td>
            <td class="right">Bs. <?php echo number_format($p['monto_entregado_bs'] > 0 ? $p['monto_entregado_bs'] : ($p['monto_equivalente_usd'] * $tasa_dia), 2, ',', '.'); ?></td>
        </tr>
    <?php } } ?>
</table>

<div class="sep"></div>
<div class="center bold" style="font-size: 14px;">
    TOTAL A COBRAR: <?php echo number_format($total_con_iva, 2, ',', '.'); ?>
</div>

<div class="center" style="margin-top: 15px;">
    GRACIAS POR SU COMPRA
</div>

<script>
    if(new URLSearchParams(window.location.search).has('print')) {
        setTimeout(() => { window.print(); }, 500);
    }
    if(new URLSearchParams(window.location.search).has('wa')) {
        setTimeout(() => { shareAsImage(<?php echo $id; ?>, 'btn-wa', 'ticket'); }, 500);
    }

    async function shareAsImage(id, btnId, tipo) {
        let btn = document.getElementById(btnId);
        let origHtml = btn.innerHTML;
        btn.innerHTML = '⏳ Proc...';
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

            // Ocultar area de botones
            let noPrint = document.querySelector('.no-print');
            noPrint.style.display = 'none';

            let canvas = await html2canvas(document.body, { backgroundColor: '#ffffff', scale: 2 });
            noPrint.style.display = 'block';

            let base64 = canvas.toDataURL('image/png');

            // Guardar en backend (Guardado Perpetuo exigido)
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
                    // Si el Dispositivo soporta compartir imagenes (Moviles/Chrome moderno)
                    await navigator.share({
                        title: `${tipo.toUpperCase()} #${id}`,
                        text: 'Comprobante Electrónico Adjunto',
                        files: [file]
                    });
                } else {
                    // Si es PC Antigua y no tiene Web Share, redirigir a Wa.me con URL pública.
                    let wa_url = "https://wa.me/?text=" + encodeURIComponent("Saludos! Adjunto comprobante virtual en este link perpetuo:\n\n" + json.url);
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
