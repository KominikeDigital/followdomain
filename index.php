<?php
// Start session for admin/user auth
session_start();


// Handle Language Selection Change
if (isset($_GET['lang'])) {
    $langParam = trim($_GET['lang']);
    if (in_array($langParam, ['en', 'tr', 'es', 'de'])) {
        $_SESSION['lang'] = $langParam;
    }
    // Redirect back to clean URL to remove the query string parameter from visual browser bar
    $cleanUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $queryParams = $_GET;
    unset($queryParams['lang']);
    if (!empty($queryParams)) {
        $cleanUrl .= '?' . http_build_query($queryParams);
    }
    header("Location: " . $cleanUrl);
    exit;
}

// Determine active language (default is English 'en')
$lang = $_SESSION['lang'] ?? 'en';
if (!in_array($lang, ['en', 'tr', 'es', 'de'])) {
    $lang = 'en';
}

// Load translations map
$translations = require __DIR__ . '/languages/' . $lang . '.php';

// Enable error reporting for debug (cPanel friendly)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep off by default, log to error_log

// Load configuration
$config = require __DIR__ . '/config.php';

// Connect database and initialize PDO
$pdo = require_once __DIR__ . '/includes/db.php';

// Override config with database settings
try {
    $dbSettings = $pdo->query("SELECT * FROM settings")->fetchAll();
    foreach ($dbSettings as $s) {
        $config[$s['key_name']] = $s['val_value'];
    }
} catch (Exception $e) {
    // Fail silently if table doesn't exist yet
}

// Include WHOIS and helper functions
require_once __DIR__ . '/includes/whois.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$isUser = isLoggedIn();

// Define BASE_PATH dynamically
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$base_path = rtrim(dirname($script_name), '/\\');
define('BASE_PATH', $base_path);

// Basic routing with Request URI fallback
$route = 'home';
if (isset($_GET['route']) && $_GET['route'] !== '') {
    $route = $_GET['route'];
} else {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($request_uri, PHP_URL_PATH) ?? '';
    
    // Remove base path from path
    if (BASE_PATH !== '') {
        if (strpos($path, BASE_PATH) === 0) {
            $path = substr($path, strlen(BASE_PATH));
        }
    }
    
    $path = trim($path, '/');
    
    if ($path === '' || $path === 'index.php') {
        $route = 'home';
    } else {
        if ($path === 'login') {
            $route = 'login';
        } elseif ($path === 'register') {
            $route = 'register';
        } elseif ($path === 'logout') {
            $route = 'logout';
        } elseif ($path === 'panel') {
            $route = 'panel';
        } elseif ($path === 'panel/domains') {
            $route = 'panel_domains';
        } elseif ($path === 'panel/domains/export') {
            $route = 'panel_domains_export';
        } elseif ($path === 'panel/hosting') {
            $route = 'panel_hosting';
        } elseif ($path === 'panel/integrations') {
            $route = 'panel_integrations';
        } elseif ($path === 'panel/integrations/upgrade') {
            $route = 'panel_integrations_upgrade';
        } elseif ($path === 'trending') {
            $route = 'trending';
        } elseif ($path === 'expiring') {
            $route = 'expiring';
        } elseif ($path === 'docs') {
            $route = 'docs';
        } elseif ($path === 'admin') {
            header("Location: " . url(""));
            exit;
        } elseif ($path === 'checkout') {
            $route = 'checkout';
        } elseif ($path === 'webhook/whop') {
            $route = 'webhook_whop';
        } elseif ($path === 'domains-for-sale') {
            $route = 'domains_for_sale';
        } elseif ($path === 'go') {
            $route = 'go';
        } elseif ($path === 'privacy-policy') {
            $route = 'privacy';
        } elseif ($path === 'terms-of-service') {
            $route = 'terms';
        } elseif ($path === 'manage-secure-panel') {
            $route = 'admin';
        } elseif ($path === 'forgot-password') {
            $route = 'forgot_password';
        } elseif ($path === 'verify-email') {
            $route = 'verify_email';
        } elseif ($path === 'blog') {
            $route = 'blog_list';
        } elseif (preg_match('/^blog\/category\/([a-zA-Z0-9\-]+)$/', $path, $matches)) {
            $route = 'blog_category';
            $categorySlug = $matches[1];
        } elseif (preg_match('/^blog\/([a-zA-Z0-9\-]+)$/', $path, $matches)) {
            $route = 'blog_detail';
            $_GET['slug'] = $matches[1];
        } elseif ($path === 'cron') {
            $route = 'cron';
        } elseif (preg_match('/^domain\/([a-zA-Z0-9\.\-]+)$/', $path, $matches)) {
            $route = 'domain';
            $_GET['name'] = $matches[1];
        } elseif ($path === 'social-search') {
            $route = 'social_search';
        } elseif ($path === 'social-search/ajax') {
            $route = 'social_search_ajax';
        } elseif ($path === 'domain-search') {
            $route = 'domain_search';
        } elseif ($path === 'domain-search/ajax') {
            $route = 'domain_search_ajax';
        } elseif ($path === 'admin/test-smtp-live' || $path === 'manage-secure-panel/test-smtp-live') {
            $route = 'admin_test_smtp_live';
        }
    }
}

// Handle Home Page Search redirect
if ($route === 'home' && isset($_GET['q'])) {
    $q = trim($_GET['q']);
    if ($q !== '') {
        if (strpos($q, '.') === false) {
            header("Location: " . url("domain-search?q=" . urlencode($q)));
            exit;
        } else {
            $searchDomain = cleanDomainName($q);
            if ($searchDomain) {
                header("Location: " . url("domain/" . urlencode($searchDomain)));
                exit;
            }
        }
    }
}

// Global page variables
$pageTitle = $config['site_title'];
$pageDesc = $config['site_description'];

// Handle specific page logic before loading template headers
switch ($route) {
    case 'webhook_whop':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'method_not_allowed']);
            exit;
        }

        $rawPayload = file_get_contents('php://input') ?: '';
        $result = processWhopWebhook($pdo, $rawPayload, getRequestHeadersLower());
        http_response_code($result['status']);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;

    case 'login':
    case 'register':
        if (isLoggedIn()) {
            header("Location: " . url("panel"));
            exit;
        }
        
        $authError = null;
        $authSuccess = null;
        
        // Handle Login Submission
        if (isset($_POST['submit_login'])) {
            $res = loginUser($pdo, $_POST['username_or_email'], $_POST['password']);
            if ($res['success']) {
                header("Location: " . url("panel"));
                exit;
            } else {
                $authError = $res['message'];
                if (!empty($res['needs_verification']) && !empty($res['verification_token'])) {
                    $resent = sendUserVerificationEmail($res['email'], $res['username'], $res['verification_token']);
                    if ($resent) {
                        $authError = 'E-posta adresinizi doğrulamanız gerekiyor. Doğrulama bağlantısını tekrar gönderdik.';
                    } else {
                        $authError = 'E-posta adresinizi doğrulamanız gerekiyor ancak doğrulama e-postası gönderilemedi. Lütfen site yöneticisiyle iletişime geçin.';
                        sendAdminRegistrationNotification($res['email'], $res['username'], false, $res['verification_token']);
                    }
                }
            }
        }
        
        // Handle Register Submission
        if (isset($_POST['submit_register'])) {
            $plan = trim($_POST['plan'] ?? 'free');
            $res = registerUser($pdo, $_POST['username'], $_POST['email'], $_POST['password'], $plan);
            if ($res['success']) {
                $userEmail = trim($_POST['email']);
                $userName = trim($_POST['username']);
                $token = $res['verification_token'];
                
                try {
                    $mailSent = sendUserVerificationEmail($userEmail, $userName, $token);
                    sendAdminRegistrationNotification($userEmail, $userName, $mailSent, $token);

                    if ($mailSent) {
                        $authSuccess = "Kayıt başarılı! Lütfen hesabınızı doğrulamak için e-posta adresinize gönderdiğimiz bağlantıya tıklayın.";
                    } else {
                        error_log("Failed to send verification email to $userEmail");
                        $authError = "Hesabınız oluşturuldu ancak doğrulama e-postası gönderilemedi. Lütfen e-posta ayarlarını kontrol etmesi için site yöneticisiyle iletişime geçin.";
                    }
                } catch (Throwable $e) {
                    error_log("Registration notification failed for $userEmail: " . $e->getMessage());
                    $authError = "Hesabınız oluşturuldu ancak e-posta bildirimi sırasında bir sorun oluştu.";
                }
            } else {
                $authError = $res['message'];
            }
        }
        break;

    case 'forgot_password':
        if (isLoggedIn()) {
            header("Location: " . url("panel"));
            exit;
        }
        
        $authError = null;
        $authSuccess = null;
        
        if (isset($_POST['submit_forgot'])) {
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            if (isValidEmail($email)) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $tempPassword = bin2hex(random_bytes(4)); // 8 characters
                    $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
                    
                    $stmtUpdate = $pdo->prepare("UPDATE users SET password_hash = ?, is_verified = 1, verification_token = NULL WHERE id = ?");
                    $stmtUpdate->execute([$passwordHash, $user['id']]);
                    
                    $subject = "TLDix Şifre Sıfırlama";
                    $messageHtml = getFormattedEmail('mail_tpl_user_forgot', [
                        'username' => esc($user['username']),
                        'temp_password' => esc($tempPassword),
                        'login_url' => absolute_url('login')
                    ]);
                    
                    $sent = sendEmailNotification($email, $subject, $messageHtml);
                    if ($sent) {
                        // Admin notification email
                        $adminEmail = $config['admin_notification_email'] ?? '';
                        if (!empty($adminEmail)) {
                            $adminSubject = "Şifre Sıfırlama Talebi: " . $user['username'];
                            $adminMessage = getFormattedEmail('mail_tpl_admin_forgot', [
                                'username' => esc($user['username']),
                                'email' => esc($email),
                                'date' => date('Y-m-d H:i:s'),
                                'temp_password' => esc($tempPassword),
                                'login_url' => absolute_url('login')
                            ]);
                            sendEmailNotification($adminEmail, $adminSubject, $adminMessage);
                        }
                        $authSuccess = "Geçici şifreniz e-posta adresinize gönderildi.";
                    } else {
                        $authError = "E-posta gönderimi sırasında bir sorun oluştu.";
                    }
                } else {
                    $authError = "Bu e-posta adresiyle kayıtlı bir kullanıcı bulunamadı.";
                }
            } else {
                $authError = "Geçersiz e-posta adresi!";
            }
        }
        
        $pageTitle = "Şifremi Unuttum | " . $config['site_title'];
        $pageDesc = "Şifrenizi sıfırlamak için e-posta adresinizi girin.";
        break;
        
    case 'verify_email':
        $token = trim($_GET['token'] ?? '');
        $verificationError = null;
        
        if (empty($token)) {
            $verificationError = "Geçersiz veya eksik doğrulama bağlantısı!";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE verification_token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Update user verification status
                $stmtUpdate = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
                $stmtUpdate->execute([$user['id']]);
                
                // Automatically log the user in
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                logActivity($pdo, $user['id'], "E-posta adresi doğrulandı ve giriş yapıldı.");
                
                // Send Welcome E-mail now
                $subject = "Welcome to TLDix!";
                $messageHtml = getFormattedEmail('mail_tpl_user_register', [
                    'username' => esc($user['username']),
                    'login_url' => absolute_url('login')
                ]);
                sendEmailNotification($user['email'], $subject, $messageHtml);
                
                $requestedPlan = $user['pending_plan'] ?? 'free';

                // Notify admin that verification is complete and account is active
                $adminEmail = $config['admin_notification_email'] ?? '';
                if (!empty($adminEmail)) {
                    $adminSubject = "Üyelik Doğrulandı: " . $user['username'];
                    $adminMessage = "<h2>Üyelik Doğrulama Bildirimi</h2><p>Yeni kayıt olan kullanıcının e-posta adresi doğrulandı ve hesabı aktif hale getirildi:</p><ul><li><strong>Kullanıcı Adı:</strong> " . esc($user['username']) . "</li><li><strong>E-posta:</strong> " . esc($user['email']) . "</li><li><strong>Aktif Plan:</strong> FREE</li><li><strong>Bekleyen Plan:</strong> " . esc(strtoupper($requestedPlan ?: 'free')) . "</li></ul>";
                    sendEmailNotification($adminEmail, $adminSubject, $adminMessage);
                }
                
                // Redirect logic: If a paid plan was selected, redirect to checkout, else redirect to panel
                if (in_array($requestedPlan, ['bronze', 'silver', 'gold'], true)) {
                    header("Location: " . url("checkout?plan=" . urlencode($requestedPlan)));
                } else {
                    header("Location: " . url("panel"));
                }
                exit;
            } else {
                $verificationError = "Bu doğrulama bağlantısı geçersiz veya zaten kullanılmış.";
            }
        }
        
        if ($verificationError) {
            $authError = $verificationError;
        }
        
        $pageTitle = "E-posta Doğrulama | " . $config['site_title'];
        $pageDesc = "E-posta adresinizi doğrulayın.";
        break;
        
    case 'logout':
        if (isLoggedIn()) {
            logActivity($pdo, $_SESSION['user_id'], "Sistemden çıkış yapıldı.");
        }
        session_destroy();
        header("Location: " . url(""));
        exit;
        
    case 'panel':
        requireLogin();
        $pageTitle = "Yönetici Paneli | " . $config['site_title'];
        break;
        
    case 'panel_domains':
        requireLogin();
        $userId = $_SESSION['user_id'];
        $pageTitle = "Alan Adlarım | " . $config['site_title'];
        
        // Handle post actions (Add, Favorite, Alert edit, Delete)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $action = $_POST['action'];
            
            if ($action === 'add_domains') {
                $mode = isset($_POST['mode']) ? $_POST['mode'] : 'single';
                $alertSettings = isset($_POST['alerts']) ? $_POST['alerts'] : [];
                
                if ($mode === 'single') {
                    $domainName = cleanDomainName($_POST['domain_name']);
                    if (!empty($domainName)) {
                        $domainData = getOrUpdateDomain($pdo, $domainName);
                        if ($domainData) {
                            try {
                                $n60 = isset($alertSettings['60']) ? (int)$alertSettings['60'] : 0;
                                $n30 = isset($alertSettings['30']) ? (int)$alertSettings['30'] : 0;
                                $n14 = isset($alertSettings['14']) ? (int)$alertSettings['14'] : 0;
                                $n7 = isset($alertSettings['7']) ? (int)$alertSettings['7'] : 0;
                                $n3 = isset($alertSettings['3']) ? (int)$alertSettings['3'] : 0;
                                $n1 = isset($alertSettings['1']) ? (int)$alertSettings['1'] : 0;
                                
                                $stmt = $pdo->prepare("INSERT INTO user_domains 
                                    (user_id, domain_name, notify_60, notify_30, notify_14, notify_7, notify_3, notify_1, created_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt->execute([$userId, $domainName, $n60, $n30, $n14, $n7, $n3, $n1, date('Y-m-d H:i:s')]);
                                
                                logActivity($pdo, $userId, "Alan adı takibe eklendi: $domainName");
                            } catch (PDOException $e) {
                                // Already exists
                            }
                        }
                    }
                } elseif ($mode === 'bulk') {
                    $bulkText = isset($_POST['domains_bulk']) ? $_POST['domains_bulk'] : '';
                    importBulkDomains($pdo, $userId, $bulkText, $alertSettings);
                }
                header("Location: " . url("panel/domains"));
                exit;
            }
            
            if ($action === 'toggle_favorite') {
                header('Content-Type: application/json');
                $domainName = cleanDomainName($_POST['domain_name']);
                
                $stmt = $pdo->prepare("SELECT is_favorite FROM user_domains WHERE user_id = ? AND domain_name = ?");
                $stmt->execute([$userId, $domainName]);
                $current = $stmt->fetch();
                
                if ($current) {
                    $newFav = ($current['is_favorite'] == 1) ? 0 : 1;
                    $stmtUpdate = $pdo->prepare("UPDATE user_domains SET is_favorite = ? WHERE user_id = ? AND domain_name = ?");
                    $stmtUpdate->execute([$newFav, $userId, $domainName]);
                    
                    $actText = $newFav ? "Alan adı favorilere eklendi: $domainName" : "Alan adı favorilerden çıkarıldı: $domainName";
                    logActivity($pdo, $userId, $actText);
                    
                    echo json_encode(['success' => true, 'is_favorite' => $newFav]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Bulunamadı']);
                }
                exit;
            }
            
            if ($action === 'update_alerts') {
                $domainName = cleanDomainName($_POST['domain_name']);
                $alertSettings = isset($_POST['alerts']) ? $_POST['alerts'] : [];
                
                $n60 = isset($alertSettings['60']) ? (int)$alertSettings['60'] : 0;
                $n30 = isset($alertSettings['30']) ? (int)$alertSettings['30'] : 0;
                $n14 = isset($alertSettings['14']) ? (int)$alertSettings['14'] : 0;
                $n7 = isset($alertSettings['7']) ? (int)$alertSettings['7'] : 0;
                $n3 = isset($alertSettings['3']) ? (int)$alertSettings['3'] : 0;
                $n1 = isset($alertSettings['1']) ? (int)$alertSettings['1'] : 0;
                
                $stmt = $pdo->prepare("UPDATE user_domains SET 
                    notify_60 = ?, notify_30 = ?, notify_14 = ?, notify_7 = ?, notify_3 = ?, notify_1 = ? 
                    WHERE user_id = ? AND domain_name = ?");
                $stmt->execute([$n60, $n30, $n14, $n7, $n3, $n1, $userId, $domainName]);
                
                logActivity($pdo, $userId, "Alan adı bildirim ayarları güncellendi: $domainName");
                header("Location: " . url("panel/domains"));
                exit;
            }
        }
        
        // Handle deletion via GET
        if (isset($_GET['delete_domain'])) {
            $domainName = cleanDomainName($_GET['delete_domain']);
            
            $stmt = $pdo->prepare("DELETE FROM user_domains WHERE user_id = ? AND domain_name = ?");
            $stmt->execute([$userId, $domainName]);
            
            logActivity($pdo, $userId, "Alan adı takipten çıkarıldı: $domainName");
            header("Location: " . url("panel/domains"));
            exit;
        }
        break;
        
    case 'panel_hosting':
        requireLogin();
        $pageTitle = "Hosting Takibim | " . $config['site_title'];
        break;
        
    case 'panel_integrations':
        requireLogin();
        $pageTitle = "Entegrasyonlarım | " . $config['site_title'];
        break;

    case 'panel_domains_export':
        requireLogin();
        $userId = $_SESSION['user_id'];
        
        $stmtUser = $pdo->prepare("SELECT api_plan FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $userPlan = $stmtUser->fetchColumn();
        
        if (!in_array($userPlan, ['bronze', 'silver', 'gold'])) {
            $_SESSION['export_error'] = "CSV indirme özelliği sadece Premium üyelerimize açıktır.";
            header("Location: " . url("panel/domains"));
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT ud.*, d.expiration_date, d.registration_date, d.registrar 
            FROM user_domains ud
            LEFT JOIN domains d ON ud.domain_name = d.domain_name
            WHERE ud.user_id = ?
        ");
        $stmt->execute([$userId]);
        $myDomains = $stmt->fetchAll();
        
        exportDomainsToCSV($myDomains);
        exit;

    case 'panel_integrations_upgrade':
        requireLogin();
        // Self-service plan upgrade is DISABLED — plans are only set by admin or after payment confirmation.
        // Redirect to checkout page so user can submit a payment request.
        header("Location: " . url("checkout"));
        exit;

    case 'expiring':
        if (!$isUser) {
            header("Location: " . url("login"));
            exit;
        }
        $pageTitle = "Düşecek Alan Adları | " . $config['site_title'];
        $pageDesc = "Bugün düşecek alan adları, 7 gün sonra ve 30 gün sonra düşecek domain listeleri.";
        break;

    case 'domain':
        $domainName = isset($_GET['name']) ? cleanDomainName($_GET['name']) : '';
        if (empty($domainName)) {
            header("Location: " . url(""));
            exit;
        }
        
        $pageTitle = "$domainName | " . $config['site_title'];
        
        // Handle Follow Action (Asynchronous or form POST fallback)
        $followMessage = null;
        $followError = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'follow') {
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            if (isValidEmail($email)) {
                // Ensure domain is tracked in DB
                $domainData = getOrUpdateDomain($pdo, $domainName);
                if ($domainData && isset($domainData['id'])) {
                    $domainId = $domainData['id'];
                    $now = date('Y-m-d H:i:s');
                    
                    try {
                        // Insert follower
                        $stmt = $pdo->prepare("INSERT INTO followers (domain_id, email, created_at) VALUES (?, ?, ?)");
                        $stmt->execute([$domainId, $email, $now]);
                        
                        // Increment follower count
                        $stmtCount = $pdo->prepare("UPDATE domains SET follower_count = follower_count + 1 WHERE id = ?");
                        $stmtCount->execute([$domainId]);
                        
                        // Log history event
                        $stmtHist = $pdo->prepare("INSERT INTO domain_history (domain_id, event_type, event_description, created_at) VALUES (?, 'new_follower', ?, ?)");
                        $stmtHist->execute([$domainId, "New watch subscription by " . substr($email, 0, 3) . "...", $now]);
                        
                        // Return JSON if AJAX request
                        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => true, 'message' => 'Takip listesine eklendi!']);
                            exit;
                        }
                        $followMessage = "E-posta adresiniz takip listesine eklendi!";
                    } catch (PDOException $e) {
                        // Email already exists for this domain
                        if ($e->getCode() == 23000) {
                            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                                header('Content-Type: application/json');
                                echo json_encode(['success' => true, 'message' => 'Bu alan adını zaten takip ediyorsunuz.']);
                                exit;
                            }
                            $followMessage = "Bu alan adını zaten takip ediyorsunuz.";
                        } else {
                            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                                header('Content-Type: application/json');
                                echo json_encode(['success' => false, 'message' => 'Takip işlemi gerçekleştirilemedi.']);
                                exit;
                            }
                            $followError = "Takip işlemi gerçekleştirilemedi.";
                        }
                    }
                }
            } else {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Geçersiz e-posta adresi.']);
                    exit;
                }
                $followError = "Geçersiz e-posta adresi.";
            }
        }
        
        // Load domain details
        $domainData = getOrUpdateDomain($pdo, $domainName);
        break;
        
    case 'trending':
        $pageTitle = "Trend Alan Adları | " . $config['site_title'];
        
        // Fetch top followed domains
        $stmt = $pdo->query("SELECT * FROM domains ORDER BY follower_count DESC, last_checked DESC LIMIT 50");
        $trendingDomains = $stmt->fetchAll();
        break;
        
    case 'docs':
        $pageTitle = "API Dokümantasyonu | " . $config['site_title'];
        break;
        
    case 'admin':
        $pageTitle = "Yönetici Paneli | " . $config['site_title'];
        
        // Handle admin authentication
        $isAdminLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            $isPasswordCorrect = false;
            if (password_verify($password, $config['admin_password'])) {
                $isPasswordCorrect = true;
            } elseif ($password === $config['admin_password']) {
                $isPasswordCorrect = true;
            }

            if ($email === $config['admin_email'] && $isPasswordCorrect) {
                $_SESSION['admin_logged_in'] = true;
                $isAdminLoggedIn = true;
            } else {
                $loginError = "Hatalı e-posta adresi veya şifre!";
            }
        }
        
        if (isset($_GET['logout'])) {
            session_destroy();
            header("Location: " . url("manage-secure-panel"));
            exit;
        }
        
        // Admin operations
        if ($isAdminLoggedIn) {
            // Download SQLite Database Backup
            if (isset($_GET['action']) && $_GET['action'] === 'backup_db') {
                $dbPath = $config['sqlite_path'] ?? '';
                if ($config['db_type'] === 'sqlite' && file_exists($dbPath)) {
                    if (ob_get_level()) ob_end_clean();
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="database_backup_' . date('Y-m-d_H-i-s') . '.sqlite"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($dbPath));
                    readfile($dbPath);
                    exit;
                }
            }

            // Delete domain
            if (isset($_GET['delete_domain'])) {
                $delId = intval($_GET['delete_domain']);
                $stmt = $pdo->prepare("DELETE FROM followers WHERE domain_id = ?");
                $stmt->execute([$delId]);
                $stmt = $pdo->prepare("DELETE FROM domain_history WHERE domain_id = ?");
                $stmt->execute([$delId]);
                $stmt = $pdo->prepare("DELETE FROM domains WHERE id = ?");
                $stmt->execute([$delId]);
                header("Location: " . url("manage-secure-panel"));
                exit;
            }
            
            // Add monitored domain
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_add_domain') {
                $newDomain = cleanDomainName($_POST['domain_name'] ?? '');
                if ($newDomain) {
                    $currentCount = $pdo->query("SELECT COUNT(*) FROM domains")->fetchColumn();
                    if ($currentCount >= 12) {
                        $adminError = "Maksimum 12 izlenen alan adı limitine ulaşıldı! Yeni eklemek için lütfen önce bazılarını silin.";
                    } else {
                        $stmtCheck = $pdo->prepare("SELECT id FROM domains WHERE domain_name = ?");
                        $stmtCheck->execute([$newDomain]);
                        if ($stmtCheck->fetch()) {
                            $adminError = "Bu alan adı zaten izleniyor!";
                        } else {
                            $now = date('Y-m-d H:i:s');
                            $expDate = date('Y-m-d H:i:s', strtotime('+30 days'));
                            $regDate = date('Y-m-d H:i:s', strtotime('-1 year'));
                            $stmtIns = $pdo->prepare("INSERT INTO domains (domain_name, follower_count, registrar, expiration_date, registration_date, last_checked) VALUES (?, 10, 'Unknown', ?, ?, ?)");
                            $stmtIns->execute([$newDomain, $expDate, $regDate, $now]);
                            logActivity($pdo, 0, "Yönetici alan adı ekledi: $newDomain");
                            header("Location: " . url("manage-secure-panel"));
                            exit;
                        }
                    }
                }
            }

            // Send test email
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_test_email') {
                $testEmail = filter_input(INPUT_POST, 'test_email', FILTER_SANITIZE_EMAIL);
                if (isValidEmail($testEmail)) {
                    $subject = "TLDix E-posta Bildirim Testi";
                    $htmlMessage = "<h1>TLDix E-posta Testi Başarılı!</h1><p>Bu e-posta, sistem ayarlarınızın doğru şekilde çalıştığını doğrulamak amacıyla gönderilmiştir.</p><p>Sunucu saati: " . date('Y-m-d H:i:s') . "</p>";
                    $sent = sendEmailNotification($testEmail, $subject, $htmlMessage);
                    if ($sent) {
                        $_SESSION['test_email_success'] = "Test e-postası başarıyla gönderildi: " . $testEmail;
                    } else {
                        $_SESSION['test_email_error'] = "E-posta gönderimi başarısız oldu! Lütfen logları ve SMTP ayarlarınızı kontrol edin.";
                    }
                } else {
                    $_SESSION['test_email_error'] = "Geçersiz e-posta adresi!";
                }
                header("Location: " . url("manage-secure-panel"));
                exit;
            }
            
            // Fetch domains list for view
            $stmt = $pdo->query("SELECT * FROM domains ORDER BY domain_name ASC");
            $adminDomains = $stmt->fetchAll();
        }
        break;
        
    case 'blog_list':
        $pageTitle = "Blog — " . $config['site_title'];
        $pageDesc = "Domain name tracking and web hosting tips, guides, and SEO analysis.";
        $blogPosts = getBlogPosts($lang);
        break;

    case 'blog_category':
        $categorySlug = $_GET['category'] ?? $categorySlug ?? '';
        $blogPosts = getBlogPostsByCategory($categorySlug, $lang);
        $displayCategory = str_replace('-', ' ', $categorySlug);
        if (!empty($blogPosts)) {
            $firstPost = reset($blogPosts);
            $displayCategory = $firstPost['category'];
        }
        $pageTitle = $displayCategory . " | " . $config['site_title'];
        $pageDesc = "Browse all articles in the " . $displayCategory . " category.";
        break;
        
    case 'blog_detail':
        $postSlug = $_GET['slug'] ?? '';
        $blogPost = getBlogPostBySlug($postSlug, $lang);
        if (!$blogPost) {
            header("Location: " . url("blog"));
            exit;
        }
        $pageTitle = $blogPost['title'] . " | " . $config['site_title'];
        $pageDesc = $blogPost['description'];
        break;

    case 'checkout':
        requireLogin();
        $userId = $_SESSION['user_id'];
        $plan = isset($_GET['plan']) ? trim($_GET['plan']) : 'bronze';
        if (!in_array($plan, ['bronze', 'silver', 'gold'])) {
            $plan = 'bronze';
        }
        
        $checkoutSuccess = null;
        $checkoutError = null;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
            $paymentMethod = $_POST['payment_method'] ?? 'card';
            
            // Set user's plan to the chosen plan
            $stmtUpd = $pdo->prepare("UPDATE users SET api_plan = ? WHERE id = ?");
            $stmtUpd->execute([$plan, $userId]);
            
            // Log activity
            logActivity($pdo, $userId, "Membership plan upgraded to " . strtoupper($plan) . " via " . strtoupper($paymentMethod));
            
            // Set visual alert notification
            $_SESSION['integration_msg'] = __('checkout_success_msg');
            
            header("Location: " . url("panel/integrations"));
            exit;
        }
        
        $pageTitle = __('checkout_title') . " | " . $config['site_title'];
        $pageDesc = __('checkout_subtitle');
        break;
        
    case 'privacy':
        $pageTitle = __('legal_privacy_title') . " | " . $config['site_title'];
        $pageDesc = __('legal_privacy_title');
        break;
        
    case 'terms':
        $pageTitle = __('legal_terms_title') . " | " . $config['site_title'];
        $pageDesc = __('legal_terms_title');
        break;

    case 'domains_for_sale':
        $pageTitle = __('nav_domains_for_sale') . " | " . $config['site_title'];
        $pageDesc = __('sale_subtitle');
        break;

    case 'go':
        $provider = isset($_GET['to']) ? trim($_GET['to']) : '';
        $targetUrl = '';
        if (!empty($provider)) {
            $configKey = 'affiliate_' . $provider;
            $targetUrl = $config[$configKey] ?? '';
        }
        
        if (!empty($targetUrl)) {
            try {
                $userId    = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
                $ip        = $_SERVER['REMOTE_ADDR'] ?? '';
                $now       = date('Y-m-d H:i:s');
                $utmSource = isset($_GET['utm_source']) ? substr(trim($_GET['utm_source']), 0, 100) : null;
                $referrer  = isset($_SERVER['HTTP_REFERER']) ? substr($_SERVER['HTTP_REFERER'], 0, 500) : null;
                $stmt = $pdo->prepare("INSERT INTO affiliate_clicks (user_id, provider, target_url, ip_address, clicked_at, utm_source, referrer) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $provider, $targetUrl, $ip, $now, $utmSource, $referrer]);
            } catch (Exception $e) {
                // Fail silently
            }
            header("Location: " . $targetUrl);
            exit;
        }
        header("Location: " . url(""));
        exit;

 
    case 'cron':
        header('Content-Type: text/plain; charset=UTF-8');
        require_once __DIR__ . '/includes/cron_handler.php';
        echo runCronJobs($pdo);
        exit;

    case 'social_search':
        $pageTitle = __('nav_social_search') . " | " . $config['site_title'];
        $pageDesc = "Search usernames across multiple social networks in real-time.";
        break;

    case 'social_search_ajax':
        // Social Platforms List
        $social_sites = [
            "Instagram" => ["url" => "https://www.instagram.com/%s/", "type" => "status_code"],
            "Twitter" => ["url" => "https://www.twitter.com/%s", "type" => "status_code"],
            "Facebook" => ["url" => "https://www.facebook.com/%s", "type" => "status_code"],
            "GitHub" => ["url" => "https://www.github.com/%s", "type" => "status_code"],
            "Reddit" => ["url" => "https://www.reddit.com/user/%s", "type" => "message", "error" => "user not found"],
            "TikTok" => ["url" => "https://www.tiktok.com/@%s", "type" => "status_code"],
            "YouTube" => ["url" => "https://www.youtube.com/@%s", "type" => "status_code"],
            "Pinterest" => ["url" => "https://www.pinterest.com/%s/", "type" => "status_code"],
            "Snapchat" => ["url" => "https://www.snapchat.com/add/%s", "type" => "status_code"],
            "Telegram" => ["url" => "https://t.me/%s", "type" => "message", "error" => "If you have Telegram"],
            "Twitch" => ["url" => "https://www.twitch.tv/%s", "type" => "status_code"],
            "Spotify" => ["url" => "https://open.spotify.com/user/%s", "type" => "status_code"],
            "Medium" => ["url" => "https://medium.com/@%s", "type" => "status_code"],
            "Steam" => ["url" => "https://steamcommunity.com/id/%s", "type" => "message", "error" => "found"],
            "SoundCloud" => ["url" => "https://soundcloud.com/%s", "type" => "status_code"],
            "Vimeo" => ["url" => "https://vimeo.com/%s", "type" => "status_code"],
            "Dribbble" => ["url" => "https://dribbble.com/%s", "type" => "status_code"],
            "Behance" => ["url" => "https://www.behance.net/%s", "type" => "status_code"],
            "Linktree" => ["url" => "https://linktr.ee/%s", "type" => "status_code"],
            "Chess.com" => ["url" => "https://www.chess.com/member/%s", "type" => "status_code"],
        ];
        header('Content-Type: application/json');
        $query = $_GET['query'] ?? '';
        $batch = isset($_GET['batch']) ? (int)$_GET['batch'] : 0;
        
        if (empty($query)) {
            echo json_encode(['results' => [], 'done' => true]);
            exit;
        }

        $usernames = explode(',', $query);
        $siteNames = array_keys($social_sites);
        $chunkSize = 6;
        $chunkedKeys = array_chunk($siteNames, $chunkSize);
        if (!isset($chunkedKeys[$batch])) {
            echo json_encode(['done' => true]);
            exit;
        }
        
        $results = [];
        foreach ($usernames as $rawUser) {
            $user = trim(preg_replace('/[^a-zA-Z0-9._-]/', '', $rawUser));
            if (empty($user)) continue;
            $mh = curl_multi_init();
            $handles = [];

            foreach ($chunkedKeys[$batch] as $name) {
                $ch = curl_init(sprintf($social_sites[$name]['url'], $user));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
                curl_multi_add_handle($mh, $ch);
                $handles[$name] = $ch;
            }

            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh);
            } while ($running > 0);

            foreach ($handles as $name => $ch) {
                $html = curl_multi_getcontent($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $exists = ($social_sites[$name]['type'] == 'status_code')
                    ? ($code >= 200 && $code < 300)
                    : (strpos($html, $social_sites[$name]['error']) === false);

                $results[] = [
                    'label' => $user,
                    'site' => $name,
                    'url' => sprintf($social_sites[$name]['url'], $user),
                    'status' => $exists ? 'found' : 'not_found'
                ];

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }

            curl_multi_close($mh);
        }

        echo json_encode([
            'results' => $results,
            'nextBatch' => ($batch + 1 < count($chunkedKeys)) ? ($batch + 1) : null,
            'done' => ($batch + 1 >= count($chunkedKeys)),
            'total' => count($social_sites)
        ]);
        exit;

    case 'domain_search':
        $pageTitle = __('nav_domain_search') . " | " . $config['site_title'];
        $pageDesc = "Search domains across multiple TLDs in real-time.";
        break;

    case 'domain_search_ajax':
        // Domain TLDs List
        $tlds = [".com", ".net", ".org", ".info", ".me", ".biz", ".io", ".tech", ".co", ".online", ".space", ".pro", ".xyz"];
        $rdap_base_urls = [
            ".com"    => "https://rdap.verisign.com/com/v1/domain/",
            ".net"    => "https://rdap.verisign.com/net/v1/domain/",
            ".org"    => "https://rdap.publicinterestregistry.org/rdap/domain/",
            ".info"   => "https://rdap.afilias.net/rdap/info/domain/",
            ".me"     => "https://rdap.nic.me/rdap/domain/",
            ".biz"    => "https://rdap.afilias.net/rdap/biz/domain/",
            ".io"     => "https://rdap.nic.io/domain/",
            ".tech"   => "https://rdap.nic.tech/domain/",
            ".co"     => "https://rdap.nic.co/domain/",
            ".online" => "https://rdap.nic.online/domain/",
            ".space"  => "https://rdap.nic.space/domain/",
            ".pro"    => "https://rdap.afilias.net/rdap/pro/domain/",
            ".xyz"    => "https://rdap.nic.xyz/domain/",
        ];

        header('Content-Type: application/json');
        $query = $_GET['query'] ?? '';
        $batch = isset($_GET['batch']) ? (int)$_GET['batch'] : 0;
        
        if (empty($query)) {
            echo json_encode(['results' => [], 'done' => true]);
            exit;
        }

        $domain_base = trim(preg_replace('/[^a-zA-Z0-9-]/', '', $query));
        $chunkedTlds = array_chunk($tlds, 4);
        if (!isset($chunkedTlds[$batch])) {
            echo json_encode(['done' => true]);
            exit;
        }

        $results = [];
        $mh = curl_multi_init();
        $handles = [];

        foreach ($chunkedTlds[$batch] as $tld) {
            $full_domain = $domain_base . $tld;
            $rdap_url = ($rdap_base_urls[$tld] ?? "https://rdap.org/domain/") . urlencode($full_domain);

            $ch = curl_init($rdap_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

            curl_multi_add_handle($mh, $ch);
            $handles[$tld] = ['ch' => $ch, 'domain' => $full_domain];
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        foreach ($handles as $tld => $info) {
            $code = curl_getinfo($info['ch'], CURLINFO_HTTP_CODE);
            $is_taken = ($code === 200);

            $results[] = [
                'label' => $domain_base,
                'site' => $tld,
                'url' => 'https://www.whois.com/whois/' . $info['domain'],
                'status' => $is_taken ? 'not_found' : 'found'
            ];

            curl_multi_remove_handle($mh, $info['ch']);
            curl_close($info['ch']);
        }

        curl_multi_close($mh);

        echo json_encode([
            'results' => $results,
            'nextBatch' => ($batch + 1 < count($chunkedTlds)) ? ($batch + 1) : null,
            'done' => ($batch + 1 >= count($chunkedTlds)),
            'total' => count($tlds)
        ]);
        exit;

    case 'admin_test_smtp_live':
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'log' => 'Hata: Yönetici oturumu aktif değil. Lütfen sayfayı yenileyip tekrar giriş yapın.']);
            exit;
        }

        header('Content-Type: application/json');
        
        $logs = [];
        $log = function($msg) use (&$logs) {
            $logs[] = $msg;
        };

        $host = trim($_POST['smtp_host'] ?? '');
        $port = intval($_POST['smtp_port'] ?? 25);
        $username = trim($_POST['smtp_user'] ?? '');
        $password = $_POST['smtp_pass'] ?? '';
        $fromEmail = trim($_POST['smtp_from_email'] ?? '');
        $fromName = trim($_POST['smtp_from_name'] ?? 'TLDix Test');
        $to = trim($_POST['test_target_email'] ?? '');

        if (empty($host) || empty($port) || empty($to)) {
            echo json_encode(['success' => false, 'log' => "Error: Host, Port, and Target Email are required."]);
            exit;
        }

        $dnsHost = preg_replace('/^[a-z][a-z0-9+.-]*:\/\//i', '', $host);
        $dnsHost = preg_replace('/\/.*$/', '', $dnsHost);
        $dnsHost = preg_replace('/:\d+$/', '', $dnsHost);
        if (filter_var($dnsHost, FILTER_VALIDATE_IP) === false) {
            $resolvedIps = @gethostbynamel($dnsHost);
            if (empty($resolvedIps)) {
                $log("DNS FAILED: '$dnsHost' could not be resolved.");
                $log("Check the SMTP Host value. Do not prefix a subdomain with 'mail.' unless that exact hostname exists in DNS.");
                echo json_encode(['success' => false, 'log' => implode("\n", $logs)]);
                exit;
            }
        }

        if ($port === 465 && strpos($host, 'ssl://') !== 0) {
            $host = 'ssl://' . $host;
        }

        $log("Connecting to $host:$port...");
        $socket = @fsockopen($host, $port, $errno, $errstr, 15);
        if (!$socket) {
            $log("CONNECTION FAILED: $errstr ($errno)");
            echo json_encode(['success' => false, 'log' => implode("\n", $logs)]);
            exit;
        }
        $log("Connected successfully.");

        $getResponse = function($socket) use (&$logs) {
            $data = "";
            while (true) {
                $line = fgets($socket, 512);
                if ($line === false) {
                    break;
                }
                $data .= $line;
                if (strpos($line, "\r\n") !== false) {
                    if (strlen($line) >= 4 && $line[3] !== '-') {
                        break;
                    }
                }
            }
            $logs[] = "S: " . trim($data);
            return $data;
        };

        $writeCommand = function($socket, $cmd) use (&$logs) {
            $logs[] = "C: " . trim($cmd);
            fwrite($socket, $cmd . "\r\n");
        };

        $banner = $getResponse($socket);
        
        $writeCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        $ehloResponse = $getResponse($socket);

        if (!empty($username) && !empty($password)) {
            $writeCommand($socket, "AUTH LOGIN");
            $getResponse($socket);

            $writeCommand($socket, base64_encode($username));
            $getResponse($socket);

            $writeCommand($socket, base64_encode($password));
            $authResponse = $getResponse($socket);
            if (strpos($authResponse, '235') === false) {
                $log("AUTHENTICATION FAILED");
                $writeCommand($socket, "QUIT");
                $getResponse($socket);
                fclose($socket);
                echo json_encode(['success' => false, 'log' => implode("\n", $logs)]);
                exit;
            }
        }

        $writeCommand($socket, "MAIL FROM: <$fromEmail>");
        $getResponse($socket);

        $writeCommand($socket, "RCPT TO: <$to>");
        $rcptResponse = $getResponse($socket);

        $writeCommand($socket, "DATA");
        $getResponse($socket);

        $subject = "TLDix SMTP Live Test";
        $body = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $body .= "To: <$to>\r\n";
        $body .= "From: $fromName <$fromEmail>\r\n";
        $body .= "MIME-Version: 1.0\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= "<h1>SMTP Live Test</h1><p>If you see this, your SMTP connection is fully operational!</p>\r\n.\r\n";

        $logs[] = "C: [Sending Email Data...]";
        fwrite($socket, $body);
        $dataResponse = $getResponse($socket);

        $writeCommand($socket, "QUIT");
        $getResponse($socket);

        fclose($socket);
        
        $success = (strpos($dataResponse, '250') !== false);
        echo json_encode([
            'success' => $success,
            'log' => implode("\n", $logs)
        ]);
        exit;
}

// Assemble page components
require_once __DIR__ . '/templates/header.php';

switch ($route) {
    case 'home':
        require_once __DIR__ . '/templates/home.php';
        break;
    case 'login':
    case 'register':
    case 'forgot_password':
    case 'verify_email':
        require_once __DIR__ . '/templates/login.php';
        break;
    case 'panel':
        require_once __DIR__ . '/templates/dashboard.php';
        break;
    case 'panel_domains':
        require_once __DIR__ . '/templates/domains_list.php';
        break;
    case 'panel_hosting':
        require_once __DIR__ . '/templates/hosting.php';
        break;
    case 'panel_integrations':
        require_once __DIR__ . '/templates/integrations.php';
        break;
    case 'expiring':
        require_once __DIR__ . '/templates/expiring.php';
        break;
    case 'domain':
        require_once __DIR__ . '/templates/domain.php';
        break;
    case 'trending':
        require_once __DIR__ . '/templates/trending.php';
        break;
    case 'docs':
        require_once __DIR__ . '/templates/docs.php';
        break;
    case 'blog_list':
    case 'blog_category':
        require_once __DIR__ . '/templates/blog_list.php';
        break;
    case 'blog_detail':
        require_once __DIR__ . '/templates/blog_detail.php';
        break;
    case 'checkout':
        require_once __DIR__ . '/templates/checkout.php';
        break;
    case 'domains_for_sale':
        require_once __DIR__ . '/templates/domains_for_sale.php';
        break;
    case 'privacy':
    case 'terms':
        require_once __DIR__ . '/templates/legal.php';
        break;
    case 'admin':
        require_once __DIR__ . '/templates/admin.php';
        break;
    case 'social_search':
        require_once __DIR__ . '/templates/social_search.php';
        break;
    case 'domain_search':
        require_once __DIR__ . '/templates/domain_search.php';
        break;
    default:
        require_once __DIR__ . '/templates/home.php';
        break;
}

require_once __DIR__ . '/templates/footer.php';
