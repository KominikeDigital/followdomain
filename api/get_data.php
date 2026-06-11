<?php
declare(strict_types=1);

ini_set('expose_php', '0');
header_remove('X-Powered-By');

function tldixExtensionIsHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    $forwardedProto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    if ($forwardedProto === 'https') {
        return true;
    }

    $forwardedSsl = strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '');
    return $forwardedSsl === 'on';
}

function tldixExtensionApplyCorsHeaders(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigin = false;

    if (in_array($origin, ['https://tldix.com', 'https://www.tldix.com'], true)) {
        $allowedOrigin = true;
    }

    if (preg_match('/^chrome-extension:\/\/[a-p]{32}$/', $origin)) {
        $allowedOrigin = true;
    }

    if ($origin !== '' && $allowedOrigin) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-TLDIX-Extension-Session');
}

function tldixExtensionJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function tldixExtensionError(string $message, int $status = 400, string $code = 'error'): void
{
    tldixExtensionJson([
        'success' => false,
        'code' => $code,
        'error' => $message,
    ], $status);
}

function tldixExtensionDaysUntil(?string $date): ?int
{
    if (!$date) {
        return null;
    }

    $timestamp = strtotime($date);
    if (!$timestamp) {
        return null;
    }

    return (int)floor(($timestamp - time()) / 86400);
}

function tldixExtensionItemStatus(?int $daysLeft): array
{
    if ($daysLeft === null) {
        return ['status' => 'unknown', 'label' => 'Tarih yok', 'expired' => false];
    }

    if ($daysLeft < 0) {
        return ['status' => 'danger', 'label' => 'Süresi doldu', 'expired' => true];
    }

    if ($daysLeft <= 7) {
        return ['status' => 'danger', 'label' => $daysLeft . ' gün', 'expired' => false];
    }

    if ($daysLeft <= 30) {
        return ['status' => 'warning', 'label' => $daysLeft . ' gün', 'expired' => false];
    }

    return ['status' => 'success', 'label' => $daysLeft . ' gün', 'expired' => false];
}

function tldixExtensionDomainDetailUrl(string $domain): string
{
    return 'https://tldix.com/domain/' . rawurlencode($domain);
}

function tldixExtensionBuildCounts(PDO $pdo, int $userId): array
{
    $domainCountStmt = $pdo->prepare('SELECT COUNT(*) FROM user_domains WHERE user_id = ?');
    $domainCountStmt->execute([$userId]);
    $domainCount = (int)$domainCountStmt->fetchColumn();

    $hostingCountStmt = $pdo->prepare('SELECT COUNT(*) FROM user_hostings WHERE user_id = ?');
    $hostingCountStmt->execute([$userId]);
    $hostingCount = (int)$hostingCountStmt->fetchColumn();

    $favoriteCountStmt = $pdo->prepare('SELECT COUNT(*) FROM user_domains WHERE user_id = ? AND is_favorite = 1');
    $favoriteCountStmt->execute([$userId]);
    $favoriteCount = (int)$favoriteCountStmt->fetchColumn();

    $expiringSoon = 0;
    $expiringStmt = $pdo->prepare("
        SELECT d.expiration_date
        FROM user_domains ud
        LEFT JOIN domains d ON ud.domain_name = d.domain_name
        WHERE ud.user_id = ?
    ");
    $expiringStmt->execute([$userId]);

    foreach ($expiringStmt->fetchAll() as $row) {
        $daysLeft = tldixExtensionDaysUntil($row['expiration_date'] ?? null);
        if ($daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 30) {
            $expiringSoon++;
        }
    }

    return [
        'domains' => $domainCount,
        'hosting' => $hostingCount,
        'ssl' => $domainCount,
        'favorites' => $favoriteCount,
        'expiring_soon' => $expiringSoon,
    ];
}

function tldixExtensionFetchDomains(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT ud.id, ud.domain_name, ud.is_favorite, ud.created_at,
               d.expiration_date, d.registration_date, d.registrar, d.last_checked
        FROM user_domains ud
        LEFT JOIN domains d ON ud.domain_name = d.domain_name
        WHERE ud.user_id = ?
        ORDER BY (d.expiration_date IS NULL) ASC, d.expiration_date ASC, ud.domain_name ASC
    ");
    $stmt->execute([$userId]);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $daysLeft = tldixExtensionDaysUntil($row['expiration_date'] ?? null);
        $status = tldixExtensionItemStatus($daysLeft);
        $domain = (string)$row['domain_name'];

        $items[] = [
            'id' => (int)$row['id'],
            'type' => 'domain',
            'name' => $domain,
            'domain_name' => $domain,
            'subtitle' => $row['registrar'] ?: 'Registrar bilgisi yok',
            'date' => $row['expiration_date'] ? formatDate($row['expiration_date'], 'd M Y') : 'N/A',
            'expiration_date' => $row['expiration_date'],
            'daysLeft' => $daysLeft,
            'days_left' => $daysLeft,
            'status' => $status['status'],
            'statusText' => $status['label'],
            'expired' => $status['expired'],
            'isFavorite' => (bool)$row['is_favorite'],
            'detailUrl' => tldixExtensionDomainDetailUrl($domain),
            'lastChecked' => $row['last_checked'],
        ];
    }

    return $items;
}

function tldixExtensionFetchHostings(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT id, hosting_provider, domain_name, expiration_date, created_at
        FROM user_hostings
        WHERE user_id = ?
        ORDER BY (expiration_date IS NULL) ASC, expiration_date ASC, domain_name ASC
    ");
    $stmt->execute([$userId]);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $daysLeft = tldixExtensionDaysUntil($row['expiration_date'] ?? null);
        $status = tldixExtensionItemStatus($daysLeft);

        $items[] = [
            'id' => (int)$row['id'],
            'type' => 'hosting',
            'name' => (string)$row['domain_name'],
            'domain_name' => (string)$row['domain_name'],
            'subtitle' => (string)$row['hosting_provider'],
            'provider' => (string)$row['hosting_provider'],
            'date' => $row['expiration_date'] ? formatDate($row['expiration_date'], 'd M Y') : 'N/A',
            'expiration_date' => $row['expiration_date'],
            'daysLeft' => $daysLeft,
            'days_left' => $daysLeft,
            'status' => $status['status'],
            'statusText' => $status['label'],
            'expired' => $status['expired'],
            'detailUrl' => tldixExtensionDomainDetailUrl((string)$row['domain_name']),
        ];
    }

    return $items;
}

function tldixExtensionInspectSsl(string $domain): array
{
    if (!function_exists('openssl_x509_parse')) {
        return ['ok' => false, 'error' => 'OpenSSL aktif değil'];
    }

    $host = preg_replace('/:\d+$/', '', strtolower(trim($domain)));
    if ($host === '' || !preg_match('/^[a-z0-9.-]+$/i', $host)) {
        return ['ok' => false, 'error' => 'Geçersiz domain'];
    }

    $context = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'SNI_enabled' => true,
            'peer_name' => $host,
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $client = @stream_socket_client(
        'ssl://' . $host . ':443',
        $errno,
        $errstr,
        2,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$client) {
        return ['ok' => false, 'error' => $errstr ?: 'SSL bağlantısı kurulamadı'];
    }

    $params = stream_context_get_params($client);
    fclose($client);

    $certificate = $params['options']['ssl']['peer_certificate'] ?? null;
    if (!$certificate) {
        return ['ok' => false, 'error' => 'Sertifika okunamadı'];
    }

    $parsed = openssl_x509_parse($certificate);
    if (!$parsed || empty($parsed['validTo_time_t'])) {
        return ['ok' => false, 'error' => 'Sertifika tarihi okunamadı'];
    }

    return [
        'ok' => true,
        'expires_at' => date('Y-m-d H:i:s', (int)$parsed['validTo_time_t']),
        'issuer' => $parsed['issuer']['O'] ?? ($parsed['issuer']['CN'] ?? ''),
    ];
}

function tldixExtensionFetchSsl(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT ud.id, ud.domain_name
        FROM user_domains ud
        WHERE ud.user_id = ?
        ORDER BY ud.domain_name ASC
        LIMIT 10
    ");
    $stmt->execute([$userId]);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $domain = (string)$row['domain_name'];
        $ssl = tldixExtensionInspectSsl($domain);

        if ($ssl['ok']) {
            $daysLeft = tldixExtensionDaysUntil($ssl['expires_at']);
            $status = tldixExtensionItemStatus($daysLeft);
            $date = formatDate($ssl['expires_at'], 'd M Y');
        } else {
            $daysLeft = null;
            $status = ['status' => 'unknown', 'label' => 'Kontrol edilemedi', 'expired' => false];
            $date = 'N/A';
        }

        $items[] = [
            'id' => (int)$row['id'],
            'type' => 'ssl',
            'name' => $domain,
            'domain_name' => $domain,
            'subtitle' => $ssl['ok'] ? ($ssl['issuer'] ?: 'SSL sertifikası') : ($ssl['error'] ?? 'SSL kontrol edilemedi'),
            'date' => $date,
            'expiration_date' => $ssl['expires_at'] ?? null,
            'daysLeft' => $daysLeft,
            'days_left' => $daysLeft,
            'status' => $status['status'],
            'statusText' => $status['label'],
            'expired' => $status['expired'],
            'detailUrl' => tldixExtensionDomainDetailUrl($domain),
        ];
    }

    return $items;
}

tldixExtensionApplyCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    tldixExtensionError('Bu endpoint yalnızca GET isteklerini kabul eder.', 405, 'method_not_allowed');
}

session_cache_limiter('');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => tldixExtensionIsHttpsRequest(),
    'httponly' => true,
    'samesite' => tldixExtensionIsHttpsRequest() ? 'None' : 'Lax',
]);

$sessionFromExtension = trim((string)($_SERVER['HTTP_X_TLDIX_EXTENSION_SESSION'] ?? ''));
if ($sessionFromExtension !== '' && preg_match('/^[a-zA-Z0-9,-]{16,128}$/', $sessionFromExtension)) {
    session_id($sessionFromExtension);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$lang = $_SESSION['lang'] ?? 'en';
if (!in_array($lang, ['en', 'tr', 'es', 'de'], true)) {
    $lang = 'en';
}

$root = dirname(__DIR__);
$config = require $root . '/config.php';
$translations = require $root . '/languages/' . $lang . '.php';

$pdo = require_once $root . '/includes/db.php';

try {
    $dbSettings = $pdo->query('SELECT * FROM settings')->fetchAll();
    foreach ($dbSettings as $setting) {
        $config[$setting['key_name']] = $setting['val_value'];
    }
} catch (Throwable $e) {
    // Settings are optional during first install.
}

require_once $root . '/includes/whois.php';
require_once $root . '/includes/functions.php';
require_once $root . '/includes/auth.php';

if (!isLoggedIn()) {
    tldixExtensionError('Oturum bulunamadı. Lütfen önce TLDix.com üzerinde giriş yapın.', 401, 'not_authenticated');
}

$userId = (int)$_SESSION['user_id'];
$type = strtolower(trim((string)($_GET['type'] ?? 'domains')));
$allowedTypes = ['summary', 'domains', 'hosting', 'ssl'];

if (!in_array($type, $allowedTypes, true)) {
    tldixExtensionError('Geçersiz veri tipi.', 400, 'invalid_type');
}

$currentUser = getCurrentUser($pdo);
$counts = tldixExtensionBuildCounts($pdo, $userId);
$data = [];

if ($type === 'domains') {
    $data = tldixExtensionFetchDomains($pdo, $userId);
} elseif ($type === 'hosting') {
    $data = tldixExtensionFetchHostings($pdo, $userId);
} elseif ($type === 'ssl') {
    $data = tldixExtensionFetchSsl($pdo, $userId);
}

tldixExtensionJson([
    'success' => true,
    'type' => $type,
    'generated_at' => date('c'),
    'user' => [
        'id' => $currentUser ? (int)$currentUser['id'] : $userId,
        'username' => $currentUser['username'] ?? ($_SESSION['username'] ?? ''),
        'email' => $currentUser['email'] ?? '',
        'plan' => getUserPlan($pdo, $userId),
    ],
    'counts' => $counts,
    'data' => $data,
]);
