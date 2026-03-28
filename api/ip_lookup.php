<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
checkLogin();

$ip = trim((string)($_GET['ip'] ?? ''));
if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
    responseJson(['success' => false, 'message' => 'IP inválida'], 400);
}

function elprofeLookupIpApi(string $ipOrEmpty = ''): ?array {
    $target = $ipOrEmpty === '' ? 'https://ipapi.co/json/' : ('https://ipapi.co/' . rawurlencode($ipOrEmpty) . '/json/');
    $ch = curl_init($target);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ELPROFE-POS/1.0 IP Lookup');
    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $http >= 400) return null;
    $data = json_decode($raw, true);
    if (!is_array($data) || !empty($data['error'])) return null;
    return $data;
}

// Rango privado/local: no consultar servicio externo.
if (
    $ip === '127.0.0.1' ||
    $ip === '::1' ||
    str_starts_with($ip, '10.') ||
    str_starts_with($ip, '192.168.') ||
    str_starts_with($ip, '169.254.')
) {
    $wan = elprofeLookupIpApi('');
    if (is_array($wan)) {
        $city = trim((string)($wan['city'] ?? 'N/D'));
        $region = trim((string)($wan['region'] ?? 'N/D'));
        $country = trim((string)($wan['country_name'] ?? 'N/D'));
        responseJson([
            'success' => true,
            'location' => "Red local ({$city}, {$region} - {$country})",
            'source' => 'local_wan'
        ]);
    }
    responseJson([
        'success' => true,
        'location' => 'Red local / privada',
        'source' => 'local'
    ]);
}

if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip)) {
    $wan = elprofeLookupIpApi('');
    if (is_array($wan)) {
        $city = trim((string)($wan['city'] ?? 'N/D'));
        $region = trim((string)($wan['region'] ?? 'N/D'));
        $country = trim((string)($wan['country_name'] ?? 'N/D'));
        responseJson([
            'success' => true,
            'location' => "Red local ({$city}, {$region} - {$country})",
            'source' => 'local_wan'
        ]);
    }
    responseJson([
        'success' => true,
        'location' => 'Red local / privada',
        'source' => 'local'
    ]);
}

if (str_contains($ip, ':')) {
    $ipLower = strtolower($ip);
    if (str_starts_with($ipLower, 'fc') || str_starts_with($ipLower, 'fd') || str_starts_with($ipLower, 'fe80')) {
        $wan = elprofeLookupIpApi('');
        if (is_array($wan)) {
            $city = trim((string)($wan['city'] ?? 'N/D'));
            $region = trim((string)($wan['region'] ?? 'N/D'));
            $country = trim((string)($wan['country_name'] ?? 'N/D'));
            responseJson([
                'success' => true,
                'location' => "Red local ({$city}, {$region} - {$country})",
                'source' => 'local_wan'
            ]);
        }
        responseJson([
            'success' => true,
            'location' => 'Red local / privada',
            'source' => 'local'
        ]);
    }
}

$data = elprofeLookupIpApi($ip);
if (!is_array($data)) {
    responseJson([
        'success' => true,
        'location' => 'Ubicación no disponible',
        'source' => 'fallback'
    ]);
}

$city = trim((string)($data['city'] ?? 'N/D'));
$region = trim((string)($data['region'] ?? 'N/D'));
$country = trim((string)($data['country_name'] ?? 'N/D'));

responseJson([
    'success' => true,
    'location' => "{$city}, {$region} ({$country})",
    'source' => 'ipapi'
]);
