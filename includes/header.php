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
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/ELPROFE/assets/css/style.css">
</head>
<body class="d-flex flex-column min-vh-100">

<?php if (isset($_SESSION['user_id'])): ?>
<!-- Navbar para vista logueada -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-4">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="/ELPROFE/dashboard.php"><i class="fa-solid fa-graduation-cap"></i> ELPROFE</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link" href="/ELPROFE/dashboard"><i class="fa-solid fa-house"></i> Inicio (F1)</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/ELPROFE/ventas"><i class="fa-solid fa-cart-shopping"></i> Ventas (F2)</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/ELPROFE/proformas"><i class="fa-solid fa-file-invoice"></i> Cobranza (Fiados)</a>
        </li>
        <?php if (isAdmin()): ?>
        <li class="nav-item">
          <a class="nav-link" href="/ELPROFE/inventario"><i class="fa-solid fa-boxes-stacked"></i> Inventario (F3)</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/ELPROFE/compras"><i class="fa-solid fa-truck-fast"></i> Compras (F4)</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/ELPROFE/proveedores"><i class="fa-solid fa-truck-field"></i> Proveedores</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/ELPROFE/caja"><i class="fa-solid fa-cash-register"></i> Auditoría Caja</a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
          <a class="nav-link" href="/ELPROFE/clientes"><i class="fa-solid fa-users"></i> Clientes</a>
        </li>
      </ul>
      <div class="d-flex align-items-center">
        <!-- Indicador de Tasa -->
        <span class="badge bg-light text-dark me-3 p-2 fs-6">
            Tasa: Bs. <span id="tasa-actual"><?php echo number_format(getConfig('tasa_usd_bs', $pdo), 2); ?></span>
            <?php if (isAdmin()): ?>
            <button class="btn btn-sm btn-outline-dark ms-2 pt-0 pb-0 px-1" title="Actualizar Tasa" onclick="actualizarTasa()"><i class="fa-solid fa-pencil"></i></button>
            <?php endif; ?>
        </span>
        <!-- Theme Toggle -->
        <button class="btn btn-outline-light me-3" id="theme-toggle" title="Cambiar Tema (Alt+T)">
            <i class="fa-solid fa-moon"></i>
        </button>
        <!-- User Dropdown -->
        <div class="dropdown">
          <a class="text-white text-decoration-none dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa-solid fa-user-circle fa-lg"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="/ELPROFE/logout"><i class="fa-solid fa-sign-out-alt"></i> Salir</a></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</nav>
<main class="container-fluid flex-grow-1 px-4">
<?php else: ?>
<main class="container d-flex flex-column justify-content-center align-items-center flex-grow-1">
<?php endif; ?>
