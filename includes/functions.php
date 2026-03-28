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
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $ruta = parse_url($uri, PHP_URL_PATH) ?: $uri;
        $segmento = trim((string)preg_replace('#^/ELPROFE/?#', '', (string)$ruta), '/');
        $moduloIntentado = $segmento !== '' ? $segmento : 'inicio';

        // Registrar en bitácora para auditoría de seguridad.
        try {
            global $pdo;
            if (isset($pdo)) {
                registrarAccion($pdo, 'SEGURIDAD', 'ACCESO_DENEGADO', "Intento sin permisos al módulo '{$moduloIntentado}'.");
            }
        } catch (Exception $e) {}

        // Si es API, responder JSON; si es vista web, mostrar modal sin redirección.
        if (strpos($uri, '/ELPROFE/api/') !== false) {
            responseJson([
                'success' => false,
                'message' => "No tiene permisos para acceder a '{$moduloIntentado}'.",
                'modulo' => $moduloIntentado
            ], 403);
        }

        http_response_code(403);
        $moduloSafe = htmlspecialchars($moduloIntentado, ENT_QUOTES, 'UTF-8');
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Acceso restringido</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body style="margin:0;background:#0b1020;">
        <script>
            (function() {
                Swal.fire({
                    icon: 'warning',
                    title: 'Acceso restringido',
                    html: 'No tiene acceso al módulo <b><?php echo $moduloSafe; ?></b>.<br>Esta acción fue registrada en bitácora.',
                    allowOutsideClick: false,
                    confirmButtonText: 'Entendido'
                }).then(function() {
                    if (window.history.length > 1) window.history.back();
                    else window.location.href = '/ELPROFE/dashboard';
                });
            })();
        </script>
        </body>
        </html>
        <?php
        exit;
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

function marcarTasaComoActualizadaHoy($pdo): void {
    $hoy = date('Y-m-d');
    try {
        $stmtIns = $pdo->prepare("INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES ('tasa_fecha', ?, 'Fecha de última actualización de tasa')");
        $stmtIns->execute([$hoy]);
        $stmtUp = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'tasa_fecha'");
        $stmtUp->execute([$hoy]);
    } catch (Exception $e) {}
}

function tasaDelDiaVigente($pdo): bool {
    try {
        $tasa = floatval(getConfig('tasa_usd_bs', $pdo));
        if ($tasa <= 0) return false;
        $fechaTasa = trim((string)getConfig('tasa_fecha', $pdo));
        return $fechaTasa === date('Y-m-d');
    } catch (Exception $e) {
        return false;
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
        $geoCols = ensureBitacoraGeoColumns($pdo);
        $ip = getClientIp();
        $ubicacion = resolveIpLocationSnapshot($ip);
        $ex_int = $exito ? 1 : 0;
        if (!empty($geoCols['accesos'])) {
            $stmt = $pdo->prepare("INSERT INTO bitacora_accesos (usuario_id, username_intento, ip, dispositivo, exito, ubicacion) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$usuario_id, $username, $ip, $dispositivo, $ex_int, $ubicacion]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO bitacora_accesos (usuario_id, username_intento, ip, dispositivo, exito) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$usuario_id, $username, $ip, $dispositivo, $ex_int]);
        }
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

function isLocalOrPrivateIp(string $ip): bool {
    $ip = strtolower(trim($ip));
    if ($ip === '' || $ip === 'localhost' || $ip === '::1') return true;
    if (str_contains($ip, ':')) {
        return str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd') || str_starts_with($ip, 'fe80');
    }
    $parts = explode('.', $ip);
    if (count($parts) !== 4) return true;
    $a = intval($parts[0]);
    $b = intval($parts[1]);
    if ($a === 10 || $a === 127 || $a === 0) return true;
    if ($a === 192 && $b === 168) return true;
    if ($a === 169 && $b === 254) return true;
    if ($a === 172 && $b >= 16 && $b <= 31) return true;
    return false;
}

function getForwardedPublicIp(): ?string {
    $raw = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($raw === '') return null;
    $parts = explode(',', $raw);
    foreach ($parts as $ip) {
        $ip = trim($ip);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) && !isLocalOrPrivateIp($ip)) {
            return $ip;
        }
    }
    return null;
}

function lookupIpLocation(string $ipOrEmpty = ''): ?string {
    $target = $ipOrEmpty === '' ? 'https://ipapi.co/json/' : ('https://ipapi.co/' . rawurlencode($ipOrEmpty) . '/json/');
    $ch = curl_init($target);
    if (!$ch) return null;
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ELPROFE-POS/1.0 EVENT GEO');
    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $http >= 400) return null;

    $data = json_decode($raw, true);
    if (!is_array($data) || !empty($data['error'])) return null;
    $city = trim((string)($data['city'] ?? ''));
    $region = trim((string)($data['region'] ?? ''));
    $country = trim((string)($data['country_name'] ?? ''));
    $parts = array_values(array_filter([$city, $region, $country], fn($v) => $v !== ''));
    return $parts ? implode(', ', $parts) : null;
}

function resolveIpLocationSnapshot(string $ip): string {
    static $cache = [];
    $key = strtolower(trim($ip));
    if (isset($cache[$key])) return $cache[$key];

    if (!isLocalOrPrivateIp($ip)) {
        $publicLoc = lookupIpLocation($ip);
        $cache[$key] = $publicLoc ?: 'Ubicación no disponible';
        return $cache[$key];
    }

    $forwarded = getForwardedPublicIp();
    if ($forwarded) {
        $forwardedLoc = lookupIpLocation($forwarded);
        if ($forwardedLoc) {
            $cache[$key] = "Red local ({$forwardedLoc})";
            return $cache[$key];
        }
    }

    $wanLoc = lookupIpLocation('');
    if ($wanLoc) {
        $cache[$key] = "Red local ({$wanLoc})";
        return $cache[$key];
    }

    $cache[$key] = 'Red local / privada';
    return $cache[$key];
}

function hasTableColumn($pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

function ensureBitacoraGeoColumns($pdo): array {
    static $state = null;
    if (is_array($state)) return $state;

    $state = ['accesos' => false, 'acciones' => false];
    try {
        if (!hasTableColumn($pdo, 'bitacora_accesos', 'ubicacion')) {
            $pdo->exec("ALTER TABLE bitacora_accesos ADD COLUMN ubicacion VARCHAR(190) NULL AFTER ip");
        }
        $state['accesos'] = hasTableColumn($pdo, 'bitacora_accesos', 'ubicacion');
    } catch (Exception $e) {}

    try {
        if (!hasTableColumn($pdo, 'bitacora_acciones', 'ubicacion')) {
            $pdo->exec("ALTER TABLE bitacora_acciones ADD COLUMN ubicacion VARCHAR(190) NULL AFTER ip");
        }
        $state['acciones'] = hasTableColumn($pdo, 'bitacora_acciones', 'ubicacion');
    } catch (Exception $e) {}

    return $state;
}

function registrarAccion($pdo, $modulo, $accion, $detalle = '') {
    try {
        if (!isset($_SESSION['user_id'])) return;
        $geoCols = ensureBitacoraGeoColumns($pdo);
        $ip = getClientIp();
        $ubicacion = resolveIpLocationSnapshot($ip);
        $payload = [
            'detalle' => (string)$detalle,
            'metodo_http' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'ubicacion_evento' => $ubicacion,
        ];
        $detalleJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!empty($geoCols['acciones'])) {
            $stmt = $pdo->prepare("INSERT INTO bitacora_acciones (usuario_id, modulo, accion, detalle, ip, ubicacion) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $modulo, $accion, $detalleJson ?: (string)$detalle, $ip, $ubicacion]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO bitacora_acciones (usuario_id, modulo, accion, detalle, ip) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $modulo, $accion, $detalleJson ?: (string)$detalle, $ip]);
        }
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
