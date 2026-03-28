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
 $shareToken = isset($_GET['share']) ? (string)$_GET['share'] : '';

if (!$is_demo && $id <= 0) die("Proforma inválida");

// Acceso seguro:
// - Si se abre desde el POS (con token firmado), no requiere login.
// - Si no hay token válido, se exige sesión.
if (!$is_demo) {
    if (!validateShareLinkToken($id, $shareToken)) {
        checkLogin();
    }
}

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
$tipoDocumento = strtoupper((string)($proforma['tipo_documento'] ?? 'PROFORMA'));
$titulo = $tipoDocumento === 'FACTURA' ? 'FACTURA' : 'NOTA DE ENTREGA';

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
    <meta name="csrf-token" content="<?php echo e(generateCsrfToken()); ?>">
    <style>
        :root { --brand-blue: #002157; --brand-red: #d3101e; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { size: 58mm auto; margin: 0; }
        html, body { width: 58mm; overflow-x: hidden; }
        body { 
            font-family: 'Courier New', Courier, monospace; 
            font-size: 11px; 
            background: #fff; 
            color: #000; 
            padding: 4mm 2mm; 
        }
        
        .ticket-wrapper { width: 54mm; margin: 0 auto; background: #fff; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .sep { border-bottom: 1px dashed #000; margin: 6px 0; }
        
        .header img { width: 50px; height: auto; margin-bottom: 5px; }
        .header h3 { font-size: 14px; margin-bottom: 2px; }
        .header div { font-size: 10px; line-height: 1.2; }

        .info-section { margin: 10px 0; font-size: 10px; }
        .info-section div { margin-bottom: 2px; }

        .items-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .items-table th { border-bottom: 1px solid #000; padding: 4px 0; text-align: left; font-size: 10px; }
        .items-table td { padding: 4px 0; vertical-align: top; font-size: 10px; }
        
        .total-area { margin-top: 10px; }
        .total-row { display: flex; justify-content: space-between; padding: 2px 0; }
        .total-row.grand { font-size: 13px; font-weight: bold; border-top: 1px double #000; padding-top: 5px; margin-top: 5px; }

        .btn-panel { 
            position: fixed; top: 10px; right: 10px; z-index: 9999; 
            display: flex; flex-direction: column; gap: 8px; 
        }
        .btn-ui { 
            padding: 10px 15px; border-radius: 6px; font-weight: 700; cursor: pointer; border: none; 
            font-size: 12px; font-family: sans-serif; text-decoration: none; text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .btn-blue { background: #007bff; color: white; }
        .btn-green { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #000; }

        @media print {
            .btn-panel { display: none !important; }
            body { padding: 0; margin: 0; }
        }
    </style>
</head>
<body>

<div class="btn-panel no-print">
    <button onclick="window.print()" class="btn-ui btn-blue">🖨️ Imprimir</button>
    <button id="btn-wa" onclick="shareAsImage(<?php echo $id; ?>, 'btn-wa', 'ticket')" class="btn-ui btn-green">📱 WhatsApp</button>
    <a href="nota_entrega.php?id=<?php echo $id; ?><?php echo $shareToken ? '&share=' . rawurlencode($shareToken) : ''; ?>" class="btn-ui btn-warning">📄 Nota A4</a>
</div>

<div class="ticket-wrapper" id="ticket-capture">
    <div class="header center">
        <img src="../assets/img/logo.png" alt="Logo">
        <h3><?php echo e(strtoupper($empresa_nombre)); ?></h3>
        <div>RIF: <?php echo e($empresa_rif); ?></div>
        <div><?php echo e($empresa_dir); ?></div>
    </div>

    <div class="sep"></div>

    <div class="info-section">
        <div><span class="bold">DOC:</span> <?php echo $titulo; ?> #<?php echo str_pad($id, 6, "0", STR_PAD_LEFT); ?></div>
        <div><span class="bold">FECHA:</span> <?php echo date('d/m/Y H:i', strtotime($proforma['fecha_emision'])); ?></div>
        <div><span class="bold">CLIENTE:</span> <?php echo e($proforma['cliente_nombre']); ?></div>
        <div><span class="bold">RIF/CI:</span> <?php echo e($proforma['cedula_rif']); ?></div>
    </div>

    <div class="sep"></div>

    <table class="items-table">
        <thead>
            <tr>
                <th width="45%">DESC</th>
                <th width="20%" class="center">CANT</th>
                <th width="35%" class="right">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($detalles as $d): ?>
            <tr>
                <td colspan="3"><?php echo e($d['producto_nombre']); ?></td>
            </tr>
            <tr>
                <td><?php echo e($d['nombre_presentacion']); ?></td>
                <td class="center"><?php echo round($d['cantidad'], 2); ?></td>
                <td class="right"><?php echo number_format($d['subtotal_usd'], 2, ',', '.'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="sep"></div>

    <div class="total-area">
        <div class="total-row grand">
            <span>TOTAL REF $:</span>
            <span><?php echo number_format($proforma['total_usd'], 2, ',', '.'); ?></span>
        </div>
        <div class="total-row" style="font-size: 11px; margin-top: 5px;">
            <span>TOTAL BS (TASA <?php echo number_format($tasa_dia, 2); ?>):</span>
            <span class="bold"><?php echo number_format($total_con_iva, 2, ',', '.'); ?></span>
        </div>
    </div>

    <div class="sep"></div>
    
    <div class="center" style="margin-top: 10px; font-size: 10px;">
        GRACIAS POR SU COMPRA<br>
        *** COPIA DIGITAL ***
    </div>
</div>

<!-- Scripts al final -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/ELPROFE/assets/js/share_comprobante.js?v=<?php echo time(); ?>"></script>

<script>
    async function shareAsImage(id, btnId, tipo) {
        Swal.fire({
            title: 'Generando Ticket',
            text: 'Preparando imagen térmica...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const captureEl = document.getElementById('ticket-capture');
            const canvas = await html2canvas(captureEl, { 
                backgroundColor: '#ffffff', 
                scale: 3, // Mayor escala para tickets térmicos (fuentes monoespaciadas)
                useCORS: true,
                logging: false,
                width: 250 // Ancho aproximado de 58mm en píxeles @ 96dpi
            });

            canvas.toBlob(async (blob) => {
                if (!blob) throw new Error('Error al procesar imagen.');

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

                const file = new File([blob], `${tipo}_${id}.png`, { type: 'image/png' });
                const shared = await elprofeTryShareImageFile(file, `Ticket #${id}`, 'Recibo de Venta ElProfe');
                
                if (!shared) {
                    elprofeFallbackShareComprobante(canvas, id, tipo, json.url);
                }
            }, 'image/png');

        } catch(e) {
            Swal.fire('Error', 'Fallo al capturar: ' + e.message, 'error');
        }
    }
</script>
</body>
</html>
