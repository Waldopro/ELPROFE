<?php
// includes/functions.php
// Nota: usamos un nombre y ruta de cookie de sesión únicos para evitar
// que este sistema "comparta" PHPSESSID con otras apps web del mismo dominio.
session_name('ELPROFESESSID');

$secure = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
$cookieParams = session_get_cookie_params();
$domain = $cookieParams['domain'] ?? '';

$sessionCookieParams = [
    'lifetime' => $cookieParams['lifetime'] ?? 0,
    'path' => '/ELPROFE',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
];

if (!empty($domain)) {
    $sessionCookieParams['domain'] = $domain;
}

session_set_cookie_params($sessionCookieParams);
// Usar cookies únicamente (reduce superficie de sesión por URL).
ini_set('session.use_only_cookies', '1');

session_start();

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if (strpos($uri, '/ELPROFE/api/') !== false) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Sesión expirada. Inicie sesión nuevamente.'
            ]);
            exit;
        }
        header("Location: /ELPROFE/");
        exit;
    }
}

// Verificar si es administrador
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'ADMIN';
}

// Bloquear el paso a cajeros
function restrictAdmin() {
    if (!isAdmin()) {
        http_response_code(403);
        die("Acceso Denegado: Esta zona es exclusiva para administradores.");
    }
}

// ==========================================
// MULTICAJA: SESIONES DE CAJA
// ==========================================
function getCajaAbiertaId($pdo): ?int {
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = $pdo->prepare("SELECT id FROM sesiones_caja WHERE usuario_id = ? AND estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

function requireCajaAbierta($pdo, string $redirectTo = '/ELPROFE/mi_caja'): void {
    $id = getCajaAbiertaId($pdo);
    if (!$id) {
        setFlash('error', "Debes abrir tu caja antes de operar. Ve a 'Mi Caja'.");
        header("Location: {$redirectTo}");
        exit;
    }
}

// Retorna JSON y termina la ejecución
function responseJson($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Formatear montos
function formatMoney($amount) {
    return number_format($amount, 2, '.', ',');
}

// ==========================================
// BITACORA Y AUDITORIA
// ==========================================
function registrarAcceso($pdo, $usuario_id, $username, $dispositivo, $exito) {
    try {
        $ip = getClientIp();
        $ex_int = $exito ? 1 : 0;
        $stmt = $pdo->prepare("INSERT INTO bitacora_accesos (usuario_id, username_intento, ip, dispositivo, exito) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$usuario_id, $username, $ip, $dispositivo, $ex_int]);
    } catch(Exception $e) {} // Silent fail on logs
}

function getClientIp(): string {
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['HTTP_X_REAL_IP'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $ipRaw) {
        if (!$ipRaw) continue;
        $parts = explode(',', (string)$ipRaw);
        foreach ($parts as $ip) {
            $ip = trim($ip);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '127.0.0.1';
}

function registrarAccion($pdo, $modulo, $accion, $detalle = '') {
    try {
        if (!isset($_SESSION['user_id'])) return;
        $ip = getClientIp();
        $payload = [
            'detalle' => (string)$detalle,
            'ruta' => (string)($_SERVER['REQUEST_URI'] ?? ''),
            'metodo_http' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'referer' => (string)($_SERVER['HTTP_REFERER'] ?? ''),
            'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ];
        $detalleJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $pdo->prepare("INSERT INTO bitacora_acciones (usuario_id, modulo, accion, detalle, ip) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $modulo, $accion, $detalleJson ?: (string)$detalle, $ip]);
    } catch(Exception $e) {} // Silent fail on logs
}

// ==========================================
// SEGURIDAD: XSS y CSRF
// ==========================================

// Escapar salidas para prevenir XSS
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Generar token CSRF si no existe
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Renderizar input oculto con CSRF
function csrfField() {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" id="csrf_token" value="' . e($token) . '">';
}

// Verificar token CSRF (Para peticiones POST)
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die("Acción no permitida (Error CSRF).");
    }
}

// ==========================================
// SESIONES Y ALERTAS (FLASH MESSAGES)
// ==========================================
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function displayFlash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: '".e($f['type'])."',
                    title: '".e($f['message'])."',
                    showConfirmButton: false,
                    timer: 4000
                });
            });
        </script>";
    }
}

// ==========================================
// ENLACES COMPARTIBLES (Tickets / Proformas)
// - Evita enumeración por ID sin autenticación.
// - Usa un token firmado (HMAC) válido por tiempo.
// ==========================================
function elprofeShareLinkSecret(): string {
    $secret = getenv('ELPROFE_SHARE_LINK_SECRET');
    if (!$secret && isset($_ENV['ELPROFE_SHARE_LINK_SECRET'])) {
        $secret = (string)$_ENV['ELPROFE_SHARE_LINK_SECRET'];
    }

    // Fallback por seguridad baja: si no configuras una secret real,
    // al menos evitamos que cualquiera use un ID cualquiera sin token.
    return $secret ?: 'ELPROFE_SHARE_LINK_SECRET_CHANGE_ME';
}

function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode(string $data): string|false {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    $data = strtr($data, '-_', '+/');
    return base64_decode($data, true);
}

function generateShareLinkToken(int $proformaId, int $ttlSeconds = 604800): string {
    // ttlSeconds por defecto: 7 días
    $ts = time();
    $payload = $proformaId . ':' . $ts . ':' . $ttlSeconds;
    $sig = hash_hmac('sha256', $payload, elprofeShareLinkSecret());
    return base64UrlEncode($payload) . '.' . $sig;
}

function validateShareLinkToken(int $proformaId, ?string $token): bool {
    if (!$token) return false;
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) return false;

    [$payloadEnc, $sigProvided] = $parts;
    $payload = base64UrlDecode($payloadEnc);
    if ($payload === false) return false;

    $expected = hash_hmac('sha256', $payload, elprofeShareLinkSecret());
    if (!hash_equals($expected, $sigProvided)) return false;

    $payloadParts = explode(':', $payload);
    if (count($payloadParts) !== 3) return false;
    [$id, $ts, $ttl] = $payloadParts;

    $id = intval($id);
    $ts = intval($ts);
    $ttl = intval($ttl);
    if ($id !== $proformaId) return false;
    if ($ttl <= 0) return false;

    $now = time();
    if ($now < $ts) return false;
    if (($now - $ts) > $ttl) return false;

    return true;
}
