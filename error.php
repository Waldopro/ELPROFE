<?php
$code = $_GET['code'] ?? '404';
$messages = [
    '403' => 'Acceso Denegado. No tienes permisos suficentes para interactuar con este módulo.',
    '404' => 'Página No Encontrada. El módulo o la ruta que intentas visitar no existe.',
    '500' => 'Error Interno del Servidor. Hubo un problema procesando el archivo de base de datos o lógica.'
];
$title = "Error $code";
$message = $messages[$code] ?? 'Error desconocido del sistema.';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELPROFE - <?php echo $title; ?></title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa; /* Color fallback */
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .error-container {
            text-align: center;
            padding: 3rem;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            max-width: 600px;
            width: 90%;
            animation: fadeIn 0.8s ease-out;
        }
        .error-code {
            font-size: 8rem;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 0;
            background: linear-gradient(to right, #ef4444, #f97316);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0px 10px 20px rgba(0,0,0,0.5);
        }
        .icon-floating {
            font-size: 4rem;
            color: #ef4444;
            margin-bottom: 1rem;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-15px); } 100% { transform: translateY(0px); } }
    </style>
</head>
<body>

    <div class="error-container">
        <i class="fa-solid fa-triangle-exclamation icon-floating"></i>
        <h1 class="error-code"><?php echo htmlspecialchars($code); ?></h1>
        <h3 class="fw-bold mt-4 mb-2 text-white">Oops! Algo salió mal.</h3>
        <p class="text-white-50 fs-5 mb-5"><?php echo htmlspecialchars($message); ?></p>
        <a href="/ELPROFE/dashboard" class="btn btn-primary btn-lg rounded-pill px-5 py-3 fw-bold shadow">
            <i class="fa-solid fa-house me-2"></i> Volver al Inicio
        </a>
    </div>

</body>
</html>
