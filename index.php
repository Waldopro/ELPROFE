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

<div class="row w-100 justify-content-center align-items-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-lg p-5 border-0 bg-transparent" style="backdrop-filter: blur(10px); background: rgba(255,255,255,0.05) !important;">
            <div class="card-body">
                <div class="text-center mb-4">
                    <i class="fa-solid fa-graduation-cap fa-4x text-primary shadow-sm mb-3"></i>
                    <h2 class="fw-bold mb-0">ELPROFE</h2>
                    <p class="text-muted">Punto de Venta</p>
                </div>
                
                <?php if($error): ?>
                    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo e($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Usuario" required autofocus>
                        <label for="username"><i class="fa-solid fa-user"></i> Usuario</label>
                    </div>
                    <div class="form-floating mb-4">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
                        <label for="password"><i class="fa-solid fa-lock"></i> Contraseña</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow-sm">Ingresar al Sistema <i class="fa-solid fa-arrow-right"></i></button>
                    
                    <div class="text-center mt-3 text-muted small">
                        <kbd>admin</kbd> / <kbd>123456</kbd>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
