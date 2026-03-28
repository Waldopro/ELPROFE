<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
checkLogin();
require_once '../includes/header.php';

$empresaNombre = getConfig('empresa_nombre', $pdo) ?: 'ELPROFE POS';
$empresaRif = getConfig('empresa_rif', $pdo) ?: 'J-00000000-0';
$tasaTipo = getConfig('tasa_tipo', $pdo) ?: 'FIJA';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0 text-primary elprofe-panel-title"><i class="fa-solid fa-circle-info me-2"></i> Acerca de</h2>
    <span class="badge bg-dark border border-secondary px-3 py-2">Versión POS Profesional</span>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm elprofe-soft-card h-100">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-3"><?php echo e($empresaNombre); ?> - Sistema POS</h4>
                <p class="text-muted mb-3">
                    Sistema de Punto de Venta profesional enfocado en operación diaria de tienda:
                    ventas multimoneda, crédito/cobranza, inventario, auditoría de caja y reportes gerenciales.
                </p>
                <p class="text-muted mb-4">
                    Diseñado para trabajar con múltiples usuarios y dispositivos, controlando reservas de stock en tiempo real
                    para evitar sobreventas y mantener trazabilidad completa de cada operación.
                </p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 border h-100">
                            <div class="small text-muted mb-1">RIF Empresa</div>
                            <div class="fw-bold"><?php echo e($empresaRif); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 border h-100">
                            <div class="small text-muted mb-1">Modo de Tasa</div>
                            <div class="fw-bold"><?php echo e($tasaTipo); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 border h-100">
                            <div class="small text-muted mb-1">Desarrollado por</div>
                            <div class="fw-bold">Oswaldo Monasterio</div>
                            <div class="small text-muted">Waldo Dev</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 border h-100">
                            <div class="small text-muted mb-1">Tecnologías Base</div>
                            <div class="fw-bold">PHP, JavaScript, SQL</div>
                            <div class="small text-muted">Bootstrap 5, jQuery, SweetAlert2, MariaDB/MySQL</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm elprofe-soft-card h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-shield-halved me-2"></i> Arquitectura y Enfoque</h5>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item bg-transparent px-0">
                        <strong>Backend:</strong> PHP procedural por módulos, validaciones de negocio y control de permisos por rol.
                    </li>
                    <li class="list-group-item bg-transparent px-0">
                        <strong>Frontend:</strong> Bootstrap 5 + JavaScript/jQuery con vistas responsivas y flujo POS en tiempo real.
                    </li>
                    <li class="list-group-item bg-transparent px-0">
                        <strong>Base de Datos:</strong> MariaDB/MySQL con bitácora de accesos/acciones y control de caja.
                    </li>
                    <li class="list-group-item bg-transparent px-0">
                        <strong>Seguridad:</strong> CSRF, manejo de sesión, bitácora y alertas de acceso denegado.
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm elprofe-soft-card h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-2"><i class="fa-solid fa-bullseye me-2"></i> Misión</h5>
                <p class="mb-0 text-muted">
                    Entregar una plataforma POS robusta, clara y segura que simplifique las operaciones diarias
                    de venta, cobranza e inventario, permitiendo a cada rol trabajar con precisión y trazabilidad.
                </p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm elprofe-soft-card h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-2"><i class="fa-solid fa-eye me-2"></i> Visión</h5>
                <p class="mb-0 text-muted">
                    Ser una solución POS de referencia para comercios que necesitan control operativo real,
                    integración multimoneda y administración confiable de múltiples usuarios y dispositivos.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
