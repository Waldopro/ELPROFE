<?php
define('NO_LOGIN_REQUIRED', true);
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header("Location: /ELPROFE/dashboard");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    
    if (empty($user) || empty($pass)) {
        $error = 'Por favor, ingrese usuario y contraseña.';
    } else {
        $stmt = $pdo->prepare("SELECT id, password, nombre, rol FROM usuarios WHERE username = ?");
        $stmt->execute([$user]);
        $row = $stmt->fetch();
        
        if ($row && password_verify($pass, $row['password'])) {
            // Evitar fijación de sesión: regenerar ID justo al iniciar sesión.
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['nombre'];
            $_SESSION['user_role'] = $row['rol'];
            
            registrarAcceso($pdo, $row['id'], $user, $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', true);
            registrarAccion($pdo, 'SISTEMA', 'LOGIN_EXITO', "Usuario ingresó al sistema.");
            
            // Actualizar Tasa BCV automáticamente en background en el primer login
            try {
                require_once 'api/bcv_scraper.php';
            } catch (Exception $e) {
                // Silencioso, no abortar login si el scraping falla
            }
            
            header("Location: /ELPROFE/dashboard");
            exit;
        } else {
            registrarAcceso($pdo, null, $user, $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', false);
            $error = 'Credenciales inválidas.';
        }
    }
}
require_once 'includes/header.php';
?>

<section class="elprofe-login-wrap w-100 position-relative">
    <div class="position-absolute top-0 end-0 p-3 z-3">
        <button class="btn btn-link text-body-secondary p-2" id="theme-toggle" title="Cambiar Tema" aria-label="Cambiar tema">
            <i class="fa-solid fa-moon fs-4"></i>
        </button>
    </div>

    <div class="elprofe-login-bg-shape elprofe-login-bg-shape-a"></div>
    <div class="elprofe-login-bg-shape elprofe-login-bg-shape-b"></div>

    <div class="row g-0 justify-content-center w-100" style="max-width: 1100px;">
        <!-- Panel Lateral (Solo Escritorio) -->
        <div class="col-lg-6 d-none d-lg-block">
            <div class="elprofe-login-side h-100 d-flex flex-column justify-content-center">
                <div class="elprofe-login-side-inner">
                    <h1 class="fw-black mb-3 display-4">ELPROFE POS</h1>
                    <p class="mb-5 opacity-75 fs-5">Control total de inventario y facturación en una plataforma profesional y segura.</p>
                    <div class="d-grid gap-3">
                        <div class="elprofe-login-bullet">
                            <i class="fa-solid fa-circle-check"></i> 
                            <span>Ventas y crédito multimoneda</span>
                        </div>
                        <div class="elprofe-login-bullet">
                            <i class="fa-solid fa-circle-check"></i> 
                            <span>Bitácora y auditoría por usuario</span>
                        </div>
                        <div class="elprofe-login-bullet">
                            <i class="fa-solid fa-circle-check"></i> 
                            <span>Reportes financieros en tiempo real</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card de Login -->
        <div class="col-lg-6 col-12">
            <div class="card elprofe-login-card h-100 border-0 shadow-lg">
                <div class="card-body p-4 p-md-5 d-flex flex-column justify-content-center">
                    <div class="text-center mb-4">
                        <div class="elprofe-login-logo-wrap mb-4">
                            <img src="/ELPROFE/assets/img/logo.png" alt="Logo" class="rounded-circle shadow-sm elprofe-logo-shell p-2">
                        </div>
                        <h2 class="fw-bold mb-1">Bienvenido</h2>
                        <p class="text-body-secondary small">Ingresa tus credenciales para continuar</p>
                    </div>

                    <?php if($error): ?>
                        <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo e($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold">Usuario</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                <input type="text" class="form-control form-control-lg" id="username" name="username" placeholder="Ingresa tu usuario" required autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="Ingresa tu contraseña" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggle-pass" aria-label="Mostrar u ocultar contraseña">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow-sm fs-5">Ingresar al Sistema <i class="fa-solid fa-arrow-right"></i></button>

                       
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('toggle-pass');
    const input = document.getElementById('password');
    if (!btn || !input) return;
    btn.addEventListener('click', function() {
        const isPwd = input.type === 'password';
        input.type = isPwd ? 'text' : 'password';
        btn.innerHTML = isPwd ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
