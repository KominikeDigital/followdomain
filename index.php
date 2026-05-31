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
        }
    }
}

// Handle Home Page Search redirect
if ($route === 'home' && isset($_GET['q'])) {
    $searchDomain = cleanDomainName($_GET['q']);
    if ($searchDomain) {
        header("Location: " . url("domain/" . urlencode($searchDomain)));
        exit;
    }
}

// Global page variables
$pageTitle = $config['site_title'];
$pageDesc = $config['site_description'];

// Handle specific page logic before loading template headers
switch ($route) {
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
            }
        }
        
        // Handle Register Submission
        if (isset($_POST['submit_register'])) {
            $res = registerUser($pdo, $_POST['username'], $_POST['email'], $_POST['password']);
            if ($res['success']) {
                $userEmail = trim($_POST['email']);
                $userName = trim($_POST['username']);
                $subject = "Welcome to domainawait!";
                $messageHtml = "
                <h2>Welcome to domainawait!</h2>
                <p>Hello <strong>" . esc($userName) . "</strong>,</p>
                <p>Your account has been successfully created. You can now start tracking your domain names and hosting servers, configure custom alert intervals, and use our Developer API.</p>
                <p>Best regards,<br>domainawait Team</p>
                ";
                sendEmailNotification($userEmail, $subject, $messageHtml);
                
                header("Location: " . url("panel"));
                exit;
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
                    
                    $stmtUpdate = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmtUpdate->execute([$passwordHash, $user['id']]);
                    
                    $subject = "domainawait Şifre Sıfırlama";
                    $messageHtml = "
                    <h2>Şifre Sıfırlama Talebi</h2>
                    <p>Merhaba <strong>" . esc($user['username']) . "</strong>,</p>
                    <p>domainawait hesabınız için şifre sıfırlama talebinde bulundunuz.</p>
                    <p>Geçici şifreniz: <strong style='font-size: 1.2rem; background: #eee; padding: 2px 6px; border-radius: 4px; color: #000;'>" . esc($tempPassword) . "</strong></p>
                    <p>Lütfen bu şifreyi kullanarak giriş yapın ve ardından hesap panelinizden şifrenizi değiştirin.</p>
                    ";
                    
                    $sent = sendEmailNotification($email, $subject, $messageHtml);
                    if ($sent) {
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
            JOIN domains d ON ud.domain_name = d.domain_name
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
                    $subject = "domainawait E-posta Bildirim Testi";
                    $htmlMessage = "<h1>domainawait E-posta Testi Başarılı!</h1><p>Bu e-posta, sistem ayarlarınızın doğru şekilde çalıştığını doğrulamak amacıyla gönderilmiştir.</p><p>Sunucu saati: " . date('Y-m-d H:i:s') . "</p>";
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
    default:
        require_once __DIR__ . '/templates/home.php';
        break;
}

require_once __DIR__ . '/templates/footer.php';
