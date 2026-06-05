<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Load configurations
$config = require __DIR__ . '/config.php';

// Connect DB
$pdo = require_once __DIR__ . '/includes/db.php';

// Load helpers
require_once __DIR__ . '/includes/whois.php';
require_once __DIR__ . '/includes/functions.php';

// Start session for anonymous rate limits
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fallback for getallheaders in non-Apache environments
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// Check for API key (GET parameter or X-API-Key header)
$apiKey = isset($_GET['api_key']) ? trim($_GET['api_key']) : '';
if (empty($apiKey)) {
    $headers = getallheaders();
    if (isset($headers['X-API-Key'])) {
        $apiKey = trim($headers['X-API-Key']);
    } elseif (isset($headers['x-api-key'])) {
        $apiKey = trim($headers['x-api-key']);
    }
}

$user = null;
$limit = 10; // Default anonymous limit
$plan = 'anonymous';

if (!empty($apiKey)) {
    // Authenticate user
    $stmtUser = $pdo->prepare("SELECT * FROM users WHERE api_key = ?");
    $stmtUser->execute([$apiKey]);
    $user = $stmtUser->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid API key.'
        ]);
        exit;
    }
    
    $plan = normalizePlanKey($user['api_plan'] ?? 'free');
    $limit = getPlanApiDailyLimit($plan);
}

// Check Rate Limits
$todayStr = date('Y-m-d');
if ($user) {
    $queriesToday = (int)$user['api_queries_today'];
    $lastQueryDate = $user['last_api_query_date'];
    
    if ($lastQueryDate !== $todayStr) {
        $queriesToday = 0;
    }
    
    if ($limit !== null && $queriesToday >= $limit) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Daily API limit exceeded. Your plan (' . strtoupper($plan) . ') is limited to ' . $limit . ' queries per day.'
        ]);
        exit;
    }
    
    // Increment queries count
    $queriesToday++;
    $stmtCount = $pdo->prepare("UPDATE users SET api_queries_today = ?, last_api_query_date = ? WHERE id = ?");
    $stmtCount->execute([$queriesToday, $todayStr, $user['id']]);
} else {
    // Anonymous limits check
    if (!isset($_SESSION['anon_api_queries'])) {
        $_SESSION['anon_api_queries'] = 0;
        $_SESSION['anon_api_date'] = $todayStr;
    }
    if ($_SESSION['anon_api_date'] !== $todayStr) {
        $_SESSION['anon_api_queries'] = 0;
        $_SESSION['anon_api_date'] = $todayStr;
    }
    $_SESSION['anon_api_queries']++;
    
    if ($_SESSION['anon_api_queries'] > $limit) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Daily anonymous API limit exceeded (' . $limit . ' queries/day). Please register and use an API key for higher limits.'
        ]);
        exit;
    }
}

$domainName = isset($_GET['domain']) ? cleanDomainName($_GET['domain']) : '';

if (empty($domainName)) {
    echo json_encode([
        'success' => false,
        'error' => 'Domain parameter is required.'
    ]);
    exit;
}

$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === 'true';

try {
    $domain = getOrUpdateDomain($pdo, $domainName, $forceRefresh);
    
    if (!$domain) {
        echo json_encode([
            'success' => false,
            'error' => 'Could not retrieve domain information.'
        ]);
        exit;
    }
    
    if (isset($domain['registered']) && $domain['registered'] === false) {
        echo json_encode([
            'success' => true,
            'domain' => $domainName,
            'registered' => false,
            'message' => 'Domain is available / unregistered.'
        ]);
        exit;
    }
    
    // Convert comma-separated string to arrays
    $statusArray = array_filter(array_map('trim', explode(',', $domain['status'] ?? '')));
    $nsArray = array_filter(array_map('trim', explode(',', $domain['nameservers'] ?? '')));
    
    echo json_encode([
        'success' => true,
        'domain' => $domain['domain_name'],
        'registered' => true,
        'registration_date' => $domain['registration_date'],
        'expiration_date' => $domain['expiration_date'],
        'last_changed_date' => $domain['last_changed_date'],
        'last_checked' => $domain['last_checked'],
        'registrar' => $domain['registrar'],
        'follower_count' => (int)$domain['follower_count'],
        'status' => array_values($statusArray),
        'nameservers' => array_values($nsArray)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
