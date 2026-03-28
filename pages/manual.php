<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
require_once '../includes/header.php';

$rol = strtoupper((string)($_SESSION['user_role'] ?? 'CAJERO'));
$esAdmin = $rol === 'ADMIN';

$seccionesAdmin = [
    [
        'titulo' => 'Inicio y Dashboard',
        'pasos' => [
            'Revise estado de caja, ventas del día y alertas de inventario en tiempo real.',
            'Use accesos rápidos para abrir ventas o registrar compras.',
            'Valide la tasa activa antes de operaciones multimoneda.'
        ]
    ],
    [
        'titulo' => 'Inventario y Compras',
        'pasos' => [
            'Registre productos y presentaciones con factor de conversión correcto.',
            'Realice compras para incrementar stock base por producto.',
            'Supervise alertas de stock bajo/sin stock desde notificaciones.'
        ]
    ],
    [
        'titulo' => 'Ventas y Créditos',
        'pasos' => [
            'Procese ventas de contado o crédito con documento SENIAT según caso.',
            'En créditos, gestione abonos desde Cobranza y valide tasa del día para Bs.',
            'Use "En Espera" para reservar stock y evitar choque entre dispositivos.'
        ]
    ],
    [
        'titulo' => 'Auditoría y Control',
        'pasos' => [
            'Revise Auditoría de Caja para entradas/salidas por método y sesión.',
            'Consulte Bitácora para trazabilidad completa de acciones.',
            'Administre usuarios, permisos y configuración general del sistema.'
        ]
    ],
];

$seccionesCajero = [
    [
        'titulo' => 'Apertura de Caja',
        'pasos' => [
            'Ingrese a Mi Caja y abra su sesión antes de vender o cobrar.',
            'Registre montos iniciales USD/Bs según efectivo disponible.',
            'Confirme que la caja quedó en estado ABIERTA.'
        ]
    ],
    [
        'titulo' => 'Proceso de Venta POS',
        'pasos' => [
            'Busque producto por código, barras o catálogo.',
            'Seleccione modalidad de cobro: USD, Bs o Mixto.',
            'Emita documento y entregue ticket/nota según operación.'
        ]
    ],
    [
        'titulo' => 'Créditos y Cobranza',
        'pasos' => [
            'Puede ver y cobrar únicamente los créditos creados por su usuario.',
            'Registre abonos parciales por método de pago correcto.',
            'Verifique saldo restante y estado del documento después de cada abono.'
        ]
    ],
    [
        'titulo' => 'Cierre de Caja',
        'pasos' => [
            'Revise movimientos en Mi Caja antes de cerrar.',
            'Declare montos de cierre USD/Bs y notas si aplica.',
            'Ejecute cierre al finalizar turno para consolidar su jornada.'
        ]
    ],
];

$secciones = $esAdmin ? $seccionesAdmin : $seccionesCajero;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary elprofe-panel-title"><i class="fa-solid fa-book-open-reader me-2"></i> Manual de Usuario</h2>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
        Rol activo: <?php echo e($esAdmin ? 'Administrador' : 'Cajero'); ?>
    </span>
</div>

<div class="alert alert-info border-0 shadow-sm">
    <i class="fa-solid fa-circle-info me-2"></i>
    Este manual está filtrado por rol para mostrar solo las funciones que corresponden a tu perfil.
</div>

<div class="row g-4">
    <?php foreach ($secciones as $idx => $sec): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm elprofe-soft-card h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><?php echo e(($idx + 1) . '. ' . $sec['titulo']); ?></h5>
                    <ol class="mb-0 ps-3">
                        <?php foreach ($sec['pasos'] as $paso): ?>
                            <li class="mb-2"><?php echo e($paso); ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm elprofe-soft-card mt-4">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-2"><i class="fa-solid fa-headset me-2"></i> Buenas Prácticas Operativas</h5>
        <ul class="mb-0">
            <li>Trabaja siempre con caja abierta para no perder trazabilidad de movimientos.</li>
            <li>No compartas usuarios entre turnos; cada acción queda registrada en bitácora.</li>
            <li>Si usas múltiples dispositivos, utiliza "En Espera" para reservar stock y evitar sobreventa.</li>
            <li>Revisa notificaciones al iniciar y cerrar turno para resolver alertas pendientes.</li>
        </ul>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
