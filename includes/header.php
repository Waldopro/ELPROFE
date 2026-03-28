<?php
// includes/header.php
require_once 'db.php';
require_once 'functions.php';

// Si la página que incluye este archivo requiere login (comúnmente todas menos login.php)
if (!defined('NO_LOGIN_REQUIRED')) {
    checkLogin();
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generateCsrfToken(); ?>">
    <title>ELPROFE - Sistema POS</title>
    <script>
      (function() {
        try {
          var saved = localStorage.getItem('theme');
          var theme = (saved === 'light' || saved === 'dark') ? saved : 'dark';
          document.documentElement.setAttribute('data-bs-theme', theme);
        } catch (e) {
          document.documentElement.setAttribute('data-bs-theme', 'dark');
        }
      })();
    </script>
    
    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="57x57" href="/ELPROFE/assets/img/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="/ELPROFE/assets/img/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="/ELPROFE/assets/img/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="/ELPROFE/assets/img/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="/ELPROFE/assets/img/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/ELPROFE/assets/img/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="/ELPROFE/assets/img/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/ELPROFE/assets/img/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/ELPROFE/assets/img/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192"  href="/ELPROFE/assets/img/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/ELPROFE/assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/ELPROFE/assets/img/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/ELPROFE/assets/img/favicon-16x16.png">
    <link rel="shortcut icon" href="/ELPROFE/assets/img/favicon.ico" type="image/x-icon">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/ELPROFE/manifest.json">
    <meta name="msapplication-TileColor" content="#002157">
    <meta name="msapplication-TileImage" content="/ELPROFE/assets/img/ms-icon-144x144.png">
    <meta name="theme-color" content="#002157">
    
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/ELPROFE/assets/css/style.css">
    
    <!-- jQuery en el HEAD (Requerido por modulos) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="d-flex flex-column min-vh-100">

<?php if (isset($_SESSION['user_id'])): ?>
<?php
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  $isActive = function(string $needle) use ($uri): bool {
      return strpos($uri, $needle) !== false;
  };
?>

<div class="elprofe-app">
  <!-- Topbar -->
  <div class="elprofe-topbar shadow-sm">
    <div class="container-fluid px-3">
      <div class="d-flex align-items-center justify-content-between py-2">
        <a class="elprofe-brand fw-bold d-flex align-items-center text-decoration-none" href="/ELPROFE/dashboard">
          <img src="/ELPROFE/assets/img/logo.png" alt="Logo" width="38" height="38" class="me-2 rounded-circle p-1 elprofe-logo-shell">
          <span>ELPROFE</span>
        </a>

        <div class="d-flex align-items-center gap-2">
          <!-- Indicador de Tasa Solo Lectura -->
          <span class="badge bg-dark text-white border border-secondary p-2 fs-6 shadow-sm elprofe-tasa d-none d-md-inline-flex">
            Tasa: Bs. <span id="tasa-actual"><?php echo number_format(getConfig('tasa_usd_bs', $pdo), 2); ?></span>
            <small class="ms-1 text-primary fw-bold">[<?php echo getConfig('tasa_tipo', $pdo) ?: 'FIJA'; ?>]</small>
          </span>

          <div class="dropdown">
            <button class="btn btn-outline-light elprofe-topbar-icon-btn position-relative" id="notifications-btn" title="Notificaciones" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Abrir notificaciones">
              <i class="fa-solid fa-bell"></i>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notifications-count">0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0 elprofe-notify-menu" id="notifications-menu">
              <div class="elprofe-notify-header d-flex align-items-center justify-content-between px-3 py-2">
                <strong>Notificaciones</strong>
                <div class="d-flex align-items-center gap-2">
                  <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="notifications-refresh" title="Actualizar notificaciones" aria-label="Actualizar notificaciones">
                    <i class="fa-solid fa-rotate"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="notifications-mark-read" title="Marcar como vistas" aria-label="Marcar notificaciones como vistas">
                    <i class="fa-solid fa-check-double"></i>
                  </button>
                  <small class="text-muted" id="notifications-time">--:--</small>
                </div>
              </div>
              <div id="notifications-list" class="elprofe-notify-list">
                <div class="px-3 py-3 text-muted small">Cargando notificaciones...</div>
              </div>
              <div class="elprofe-notify-footer px-3 py-2 border-top">
                <div class="d-flex justify-content-between align-items-center gap-2">
                  <a href="/ELPROFE/inventario" class="small fw-semibold">Ir a inventario</a>
                  <a href="/ELPROFE/proformas" class="small fw-semibold">Ir a cobranza</a>
                </div>
              </div>
            </div>
          </div>

          <!-- Theme Toggle -->
          <button class="btn btn-outline-light elprofe-topbar-icon-btn" id="theme-toggle" title="Cambiar Tema (Alt+T)" aria-label="Cambiar tema">
            <i class="fa-solid fa-moon"></i>
          </button>

          <!-- Mobile menu button -->
          <button class="btn btn-outline-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#elprofeSidebarOffcanvas" aria-controls="elprofeSidebarOffcanvas" aria-label="Abrir menú">
            <i class="fa-solid fa-bars"></i>
          </button>

          <!-- User Dropdown -->
          <div class="dropdown">
            <a class="text-white text-decoration-none dropdown-toggle elprofe-user-link" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa-solid fa-user-circle fa-lg me-1"></i>
              <span class="d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="/ELPROFE/perfil"><i class="fa-solid fa-id-badge"></i> Mi Perfil</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="/ELPROFE/logout"><i class="fa-solid fa-sign-out-alt"></i> Salir</a></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="elprofe-body d-flex">
    <!-- Sidebar (desktop) -->
    <nav class="elprofe-sidebar d-none d-lg-flex flex-column" aria-label="Menú principal">
      <div class="elprofe-sidebar-inner">
        <div class="elprofe-nav-section">
          <div class="elprofe-nav-section-title">Caja & Ventas</div>
          <a href="/ELPROFE/dashboard" class="elprofe-nav-link <?php echo $isActive('/dashboard') ? 'active' : ''; ?>">
            <i class="fa-solid fa-house"></i>
            <span>Inicio</span>
            <span class="elprofe-nav-shortcut ms-auto">F1</span>
          </a>
          <a href="/ELPROFE/mi_caja" class="elprofe-nav-link <?php echo $isActive('/mi_caja') ? 'active' : ''; ?>">
            <i class="fa-solid fa-cash-register"></i>
            <span>Mi Caja</span>
          </a>
          <a href="/ELPROFE/ventas" class="elprofe-nav-link <?php echo $isActive('/ventas') ? 'active' : ''; ?>">
            <i class="fa-solid fa-cart-shopping"></i>
            <span>Ventas</span>
            <span class="elprofe-nav-shortcut ms-auto">F2</span>
          </a>
          <a href="/ELPROFE/proformas" class="elprofe-nav-link <?php echo $isActive('/proformas') ? 'active' : ''; ?>">
            <i class="fa-solid fa-file-invoice"></i>
            <span>Cobranza</span>
            <span class="elprofe-nav-pill ms-auto">Fiados</span>
          </a>
          <a href="/ELPROFE/clientes" class="elprofe-nav-link <?php echo $isActive('/clientes') ? 'active' : ''; ?>">
            <i class="fa-solid fa-users"></i>
            <span>Clientes</span>
          </a>
          <a href="/ELPROFE/acerca" class="elprofe-nav-link <?php echo $isActive('/acerca') ? 'active' : ''; ?>">
            <i class="fa-solid fa-circle-info"></i>
            <span>Acerca de</span>
          </a>
          <a href="/ELPROFE/manual" class="elprofe-nav-link <?php echo $isActive('/manual') ? 'active' : ''; ?>">
            <i class="fa-solid fa-book-open-reader"></i>
            <span>Manual de Usuario</span>
          </a>
        </div>

        <?php if (isAdmin()): ?>
        <div class="elprofe-nav-section">
          <div class="elprofe-nav-section-title">Inventario & Compras</div>
          <a href="/ELPROFE/inventario" class="elprofe-nav-link <?php echo $isActive('/inventario') ? 'active' : ''; ?>">
            <i class="fa-solid fa-boxes-stacked"></i>
            <span>Inventario</span>
            <span class="elprofe-nav-shortcut ms-auto">F3</span>
          </a>
          <a href="/ELPROFE/categorias" class="elprofe-nav-link <?php echo $isActive('/categorias') ? 'active' : ''; ?>">
            <i class="fa-solid fa-tags"></i>
            <span>Categorías</span>
          </a>
          <a href="/ELPROFE/compras" class="elprofe-nav-link <?php echo $isActive('/compras') ? 'active' : ''; ?>">
            <i class="fa-solid fa-truck-fast"></i>
            <span>Compras</span>
            <span class="elprofe-nav-shortcut ms-auto">F4</span>
          </a>
          <a href="/ELPROFE/proveedores" class="elprofe-nav-link <?php echo $isActive('/proveedores') ? 'active' : ''; ?>">
            <i class="fa-solid fa-truck-field"></i>
            <span>Proveedores</span>
          </a>
        </div>

        <div class="elprofe-nav-section">
          <div class="elprofe-nav-section-title">Administración</div>
          <a href="/ELPROFE/reportes" class="elprofe-nav-link <?php echo $isActive('/reportes') ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-line"></i>
            <span>Reportes / Libros</span>
          </a>
          <a href="/ELPROFE/caja" class="elprofe-nav-link <?php echo $isActive('/caja') ? 'active' : ''; ?>">
            <i class="fa-solid fa-cash-register"></i>
            <span>Auditoría Caja</span>
          </a>
          <a href="/ELPROFE/bitacora" class="elprofe-nav-link <?php echo $isActive('/bitacora') ? 'active' : ''; ?>">
            <i class="fa-solid fa-shield-halved"></i>
            <span>Bitácora</span>
          </a>
          <a href="/ELPROFE/usuarios" class="elprofe-nav-link <?php echo $isActive('/usuarios') ? 'active' : ''; ?>">
            <i class="fa-solid fa-users-gear"></i>
            <span>Usuarios</span>
          </a>
          <a href="/ELPROFE/configuracion" class="elprofe-nav-link <?php echo $isActive('/configuracion') ? 'active' : ''; ?>">
            <i class="fa-solid fa-gears"></i>
            <span>Configuración</span>
          </a>
        </div>
        <?php endif; ?>
      </div>
    </nav>

    <!-- Offcanvas (mobile) -->
    <div class="elprofe-offcanvas d-lg-none">
      <div class="offcanvas offcanvas-start elprofe-offcanvas-menu" tabindex="-1" id="elprofeSidebarOffcanvas" aria-labelledby="elprofeSidebarOffcanvasLabel">
        <div class="offcanvas-header">
          <div class="d-flex align-items-center">
            <img src="/ELPROFE/assets/img/logo.png" alt="Logo" width="34" height="34" class="me-2 rounded-circle p-1 elprofe-logo-shell">
            <div>
              <div id="elprofeSidebarOffcanvasLabel" class="fw-bold">ELPROFE</div>
              <div class="small text-muted">Menú</div>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body p-0">
          <nav class="elprofe-sidebar elprofe-sidebar-mobile" aria-label="Menú principal móvil">
            <div class="elprofe-sidebar-inner">
              <div class="elprofe-nav-section">
                <div class="elprofe-nav-section-title">Caja & Ventas</div>
                <a href="/ELPROFE/dashboard" class="elprofe-nav-link <?php echo $isActive('/dashboard') ? 'active' : ''; ?>">
                  <i class="fa-solid fa-house"></i>
                  <span>Inicio</span>
                  <span class="elprofe-nav-shortcut ms-auto">F1</span>
                </a>
                <a href="/ELPROFE/mi_caja" class="elprofe-nav-link <?php echo $isActive('/mi_caja') ? 'active' : ''; ?>">
                  <i class="fa-solid fa-cash-register"></i>
                  <span>Mi Caja</span>
                </a>
                <a href="/ELPROFE/ventas" class="elprofe-nav-link <?php echo $isActive('/ventas') ? 'active' : ''; ?>">
                  <i class="fa-solid fa-cart-shopping"></i>
                  <span>Ventas</span>
                  <span class="elprofe-nav-shortcut ms-auto">F2</span>
                </a>
                <a href="/ELPROFE/proformas" class="elprofe-nav-link <?php echo $isActive('/proformas') ? 'active' : ''; ?>">
                  <i class="fa-solid fa-file-invoice"></i>
                  <span>Cobranza</span>
                  <span class="elprofe-nav-pill ms-auto">Fiados</span>
                </a>
                <a href="/ELPROFE/clientes" class="elprofe-nav-link <?php echo $isActive('/clientes') ? 'active' : ''; ?>">
                  <i class="fa-solid fa-users"></i>
                  <span>Clientes</span>
                </a>
                <a href="/ELPROFE/acerca" class="elprofe-nav-link <?php echo $isActive('/acerca') ? 'active' : ''; ?>">
                  <i class="fa-solid fa-circle-info"></i>
                  <span>Acerca de</span>
                </a>
                <a href="/ELPROFE/manual" class="elprofe-nav-link <?php echo $isActive('/manual') ? 'active' : ''; ?>">
                  <i class="fa-solid fa-book-open-reader"></i>
                  <span>Manual de Usuario</span>
                </a>
              </div>

              <?php if (isAdmin()): ?>
              <div class="elprofe-nav-section">
                <div class="elprofe-nav-section-title">Inventario & Compras</div>
                <a href="/ELPROFE/inventario" class="elprofe-nav-link <?php echo $isActive('/inventario') ? 'active' : ''; ?>">
                  <i class="fa-solid fa-boxes-stacked"></i>
                  <span>Inventario</span>
                  <span class="elprofe-nav-shortcut ms-auto">F3</span>
                </a>
                <a href="/ELPROFE/categorias" class="elprofe-nav-link <?php echo $isActive('/categorias') ? 'active' : ''; ?>">
                  <i class="fa-solid fa-tags"></i>
                  <span>Categorías</span>
                </a>
                <a href="/ELPROFE/compras" class="elprofe-nav-link <?php echo $isActive('/compras') ? 'active' : ''; ?>">
                  <i class="fa-solid fa-truck-fast"></i>
                  <span>Compras</span>
                  <span class="elprofe-nav-shortcut ms-auto">F4</span>
                </a>
                <a href="/ELPROFE/proveedores" class="elprofe-nav-link <?php echo $isActive('/proveedores') ? 'active' : ''; ?>">
                  <i class="fa-solid fa-truck-field"></i>
                  <span>Proveedores</span>
                </a>
              </div>

              <div class="elprofe-nav-section">
                <div class="elprofe-nav-section-title">Administración</div>
                <a href="/ELPROFE/reportes" class="elprofe-nav-link <?php echo $isActive('/reportes') ? 'active' : ''; ?>">
                  <i class="fa-solid fa-chart-line"></i>
                  <span>Reportes / Libros</span>
                </a>
                <a href="/ELPROFE/caja" class="elprofe-nav-link <?php echo $isActive('/caja') ? 'active' : ''; ?>">
                  <i class="fa-solid fa-cash-register"></i>
                  <span>Auditoría Caja</span>
                </a>
                <a href="/ELPROFE/bitacora" class="elprofe-nav-link <?php echo $isActive('/bitacora') ? 'active' : ''; ?>">
                  <i class="fa-solid fa-shield-halved"></i>
                  <span>Bitácora</span>
                </a>
                <a href="/ELPROFE/usuarios" class="elprofe-nav-link <?php echo $isActive('/usuarios') ? 'active' : ''; ?>">
                  <i class="fa-solid fa-users-gear"></i>
                  <span>Usuarios</span>
                </a>
                <a href="/ELPROFE/configuracion" class="elprofe-nav-link <?php echo $isActive('/configuracion') ? 'active' : ''; ?>">
                  <i class="fa-solid fa-gears"></i>
                  <span>Configuración</span>
                </a>
              </div>
              <?php endif; ?>
            </div>
          </nav>
        </div>
      </div>
    </div>

    <main class="container-fluid flex-grow-1 px-3 py-3">
<?php else: ?>
<main class="container d-flex flex-column justify-content-center align-items-center flex-grow-1">
<?php endif; ?>
