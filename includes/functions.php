<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

/**
 * Helper to generate subdirectory-aware URLs
 */
function url($path = '') {
    $path = ltrim($path, '/');
    if (defined('BASE_PATH')) {
        return BASE_PATH . '/' . $path;
    }
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $base_path = rtrim(dirname($script_name), '/\\');
    return $base_path . '/' . $path;
}

/**
 * Localization helper to retrieve translated strings
 */
function __($key, $default = '') {
    global $translations;
    if (isset($translations[$key])) {
        return $translations[$key];
    }
    return $default ?: $key;
}

/**
 * Validate email address format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Helper to escape output for safe HTML rendering
 */
function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format date nicely
 */
function formatDate($dateStr, $format = 'd M Y, H:i') {
    if (!$dateStr) return 'N/A';
    $time = strtotime($dateStr);
    return $time ? date($format, $time) : 'N/A';
}

/**
 * Calculate countdown details
 */
function getCountdownDetails($expirationDate) {
    if (!$expirationDate) {
        return ['days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 0, 'text' => __('days_left_expired'), 'expired' => true];
    }
    
    $now = time();
    $exp = strtotime($expirationDate);
    $diff = $exp - $now;
    
    if ($diff <= 0) {
        return ['days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 0, 'text' => __('days_left_expired'), 'expired' => true];
    }
    
    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);
    $seconds = $diff % 60;
    
    $parts = [];
    if ($days > 0) {
        $parts[] = $days . ' ' . ($days == 1 ? __('time_day') : __('time_days'));
    }
    if ($hours > 0) {
        $parts[] = $hours . ' ' . ($hours == 1 ? __('time_hour') : __('time_hours'));
    }
    if ($days === 0 && $minutes > 0) {
        $parts[] = $minutes . ' ' . ($minutes == 1 ? __('time_minute') : __('time_minutes'));
    }
    
    $text = implode(', ', $parts) . ' ' . __('time_remaining');
    
    return [
        'days' => $days,
        'hours' => $hours,
        'minutes' => $minutes,
        'seconds' => $seconds,
        'text' => $text,
        'expired' => false
    ];
}

/**
 * Calculate expiration percentage
 */
function getExpirationProgress($registrationDate, $expirationDate) {
    if (!$registrationDate || !$expirationDate) {
        return 0;
    }
    
    $reg = strtotime($registrationDate);
    $exp = strtotime($expirationDate);
    $now = time();
    
    if ($now >= $exp) return 100;
    if ($now <= $reg) return 0;
    
    $totalDuration = $exp - $reg;
    if ($totalDuration <= 0) return 0;
    
    $elapsed = $now - $reg;
    return round(($elapsed / $totalDuration) * 100, 1);
}

/**
 * Asynchronously checks a domain and updates it in background
 */
function getOrUpdateDomain($pdo, $domainName, $forceRefresh = false) {
    $domainName = cleanDomainName($domainName);
    if (!$domainName) return null;
    
    // Check if domain exists in DB
    $stmt = $pdo->prepare("SELECT * FROM domains WHERE domain_name = ?");
    $stmt->execute([$domainName]);
    $domain = $stmt->fetch();
    
    $now = date('Y-m-d H:i:s');
    
    // If not found or cached more than 24 hours (or forced)
    $shouldUpdate = $forceRefresh || !$domain;
    if ($domain) {
        $lastChecked = strtotime($domain['last_checked']);
        if (time() - $lastChecked > 86400) {
            $shouldUpdate = true;
        }
    }
    
    if ($shouldUpdate) {
        $info = fetchDomainInfo($domainName);
        if ($info && $info['success']) {
            if (!$info['registered']) {
                // Domain is unregistered / available
                if ($domain) {
                    // It was registered, now expired and deleted
                    $stmt = $pdo->prepare("DELETE FROM followers WHERE domain_id = ?");
                    $stmt->execute([$domain['id']]);
                    
                    $stmt = $pdo->prepare("DELETE FROM domain_history WHERE domain_id = ?");
                    $stmt->execute([$domain['id']]);
                    
                    $stmt = $pdo->prepare("DELETE FROM domains WHERE id = ?");
                    $stmt->execute([$domain['id']]);
                    
                    return ['registered' => false, 'domain_name' => $domainName];
                }
                return ['registered' => false, 'domain_name' => $domainName];
            }
            
            // Domain is registered, update or insert
            $expDate = $info['expiration_date'];
            $regDate = $info['registration_date'];
            $lastChange = $info['last_changed_date'];
            $registrar = $info['registrar'];
            $statusStr = implode(', ', $info['status']);
            $nsStr = implode(', ', $info['nameservers']);
            
            if ($domain) {
                // Check if expiration date changed (renewed!)
                if ($domain['expiration_date'] !== $expDate) {
                    $stmt = $pdo->prepare("INSERT INTO domain_history (domain_id, event_type, event_description, created_at) VALUES (?, 'renewal', ?, ?)");
                    $stmt->execute([$domain['id'], "Domain renewed. Old: " . $domain['expiration_date'] . ", New: " . $expDate, $now]);
                    
                    // Reset notification counters for new expiration cycle
                    $stmt = $pdo->prepare("UPDATE followers SET notified_30=0, notified_7=0, notified_1=0, notified_0=0 WHERE domain_id = ?");
                    $stmt->execute([$domain['id']]);
                }
                
                // Update
                $stmt = $pdo->prepare("UPDATE domains SET expiration_date = ?, registration_date = ?, last_checked = ?, registrar = ?, status = ?, nameservers = ?, last_changed_date = ? WHERE id = ?");
                $stmt->execute([$expDate, $regDate, $now, $registrar, $statusStr, $nsStr, $lastChange, $domain['id']]);
                
                // Fetch fresh copy
                $stmt = $pdo->prepare("SELECT * FROM domains WHERE id = ?");
                $stmt->execute([$domain['id']]);
                $domain = $stmt->fetch();
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO domains (domain_name, expiration_date, registration_date, last_checked, follower_count, registrar, status, nameservers, last_changed_date) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)");
                $stmt->execute([$domainName, $expDate, $regDate, $now, $registrar, $statusStr, $nsStr, $lastChange]);
                
                $newId = $pdo->lastInsertId();
                
                // History log
                $stmt = $pdo->prepare("INSERT INTO domain_history (domain_id, event_type, event_description, created_at) VALUES (?, 'track_start', 'Domain added to tracking.', ?)");
                $stmt->execute([$newId, $now]);
                
                // Fetch fresh copy
                $stmt = $pdo->prepare("SELECT * FROM domains WHERE id = ?");
                $stmt->execute([$newId]);
                $domain = $stmt->fetch();
            }
        }
    }
    
    return $domain;
}

/**
 * Custom SMTP Client and email sender
 */
function sendEmailNotification($to, $subject, $messageHtml) {
    global $config;
    
    $fromEmail = $config['smtp_from_email'];
    $fromName = $config['smtp_from_name'];
    
    // Headers for HTML mail
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . $fromName . " <" . $fromEmail . ">\r\n";
    $headers .= "Reply-To: " . $fromEmail . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Check if SMTP is enabled
    if ((int)($config['email_use_smtp'] ?? 0) === 1) {
        $host = $config['smtp_host'];
        $port = $config['smtp_port'];
        $username = $config['smtp_user'];
        $password = $config['smtp_pass'];
        
        $socket = @fsockopen($host, $port, $errno, $errstr, 15);
        if (!$socket) {
            error_log("SMTP connection failed: $errstr ($errno)");
            return false;
        }
        
        $getResponse = function($socket) {
            $data = "";
            while (strpos($data, "\r\n") === false || $data[3] === '-') {
                $line = fgets($socket, 512);
                $data .= $line;
            }
            return $data;
        };
        
        $getResponse($socket); // Read banner
        
        // HELO
        fwrite($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $getResponse($socket);
        
        // AUTH LOGIN if credentials are provided
        if (!empty($username) && !empty($password)) {
            fwrite($socket, "AUTH LOGIN\r\n");
            $getResponse($socket);
            
            fwrite($socket, base64_encode($username) . "\r\n");
            $getResponse($socket);
            
            fwrite($socket, base64_encode($password) . "\r\n");
            $getResponse($socket);
        }
        
        // MAIL FROM
        fwrite($socket, "MAIL FROM: <$fromEmail>\r\n");
        $getResponse($socket);
        
        // RCPT TO
        fwrite($socket, "RCPT TO: <$to>\r\n");
        $getResponse($socket);
        
        // DATA
        fwrite($socket, "DATA\r\n");
        $getResponse($socket);
        
        // Email Body
        $body = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $body .= "To: <$to>\r\n";
        $body .= "From: $fromName <$fromEmail>\r\n";
        $body .= "MIME-Version: 1.0\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $messageHtml . "\r\n.\r\n";
        
        fwrite($socket, $body);
        $getResponse($socket);
        
        // QUIT
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        return true;
    } else {
        // Fallback to PHP's built-in mail() function
        return mail($to, $subject, $messageHtml, $headers);
    }
}

/**
 * Bulk Import Domains for a user
 */
function importBulkDomains($pdo, $userId, $domainsText, $alertSettings) {
    $lines = preg_split('/[\r\n,;]+/', $domainsText);
    $count = 0;
    $errors = [];
    $now = date('Y-m-d H:i:s');
    
    // Set default alert flags
    $n60 = isset($alertSettings['60']) ? (int)$alertSettings['60'] : 1;
    $n30 = isset($alertSettings['30']) ? (int)$alertSettings['30'] : 1;
    $n14 = isset($alertSettings['14']) ? (int)$alertSettings['14'] : 1;
    $n7 = isset($alertSettings['7']) ? (int)$alertSettings['7'] : 1;
    $n3 = isset($alertSettings['3']) ? (int)$alertSettings['3'] : 1;
    $n1 = isset($alertSettings['1']) ? (int)$alertSettings['1'] : 1;
    
    foreach ($lines as $line) {
        $domainName = cleanDomainName($line);
        if (empty($domainName) || strpos($domainName, '.') === false) {
            continue;
        }
        
        try {
            // First check/update domain details in master domain table
            $domainData = getOrUpdateDomain($pdo, $domainName);
            if ($domainData) {
                // Now link this domain to the user
                $stmt = $pdo->prepare("INSERT INTO user_domains 
                    (user_id, domain_name, notify_60, notify_30, notify_14, notify_7, notify_3, notify_1, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $domainName, $n60, $n30, $n14, $n7, $n3, $n1, $now]);
                $count++;
            } else {
                $errors[] = "$domainName: Bilgiler alınamadı veya kaydedilmemiş.";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                // User already tracks this domain
                continue;
            }
            $errors[] = "$domainName: Veri tabanı hatası: " . $e->getMessage();
        }
    }
    
    if ($count > 0) {
        $stmtLog = $pdo->prepare("INSERT INTO activity_logs (user_id, action, created_at) VALUES (?, ?, ?)");
        $stmtLog->execute([$userId, "$count adet alan adı toplu olarak içe aktarıldı.", $now]);
    }
    
    return [
        'success' => true,
        'imported_count' => $count,
        'errors' => $errors
    ];
}

/**
 * Add user hosting tracking
 */
function addUserHosting($pdo, $userId, $provider, $domainName, $expDate, $alertSettings) {
    $provider = trim($provider);
    $domainName = cleanDomainName($domainName);
    
    if (empty($provider) || empty($domainName) || empty($expDate)) {
        return ['success' => false, 'message' => __('fill_all_fields')];
    }
    
    $now = date('Y-m-d H:i:s');
    $n30 = isset($alertSettings['30']) ? (int)$alertSettings['30'] : 1;
    $n7 = isset($alertSettings['7']) ? (int)$alertSettings['7'] : 1;
    $n1 = isset($alertSettings['1']) ? (int)$alertSettings['1'] : 1;
    
    // Format expiration date to standard datetime
    $expFormatted = date('Y-m-d H:i:s', strtotime($expDate));
    
    try {
        $stmt = $pdo->prepare("INSERT INTO user_hostings 
            (user_id, hosting_provider, domain_name, expiration_date, notify_30, notify_7, notify_1, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $provider, $domainName, $expFormatted, $n30, $n7, $n1, $now]);
        
        $stmtLog = $pdo->prepare("INSERT INTO activity_logs (user_id, action, created_at) VALUES (?, ?, ?)");
        $stmtLog->execute([$userId, "Hosting takip eklendi: $domainName ($provider)", $now]);
        
        return ['success' => true, 'message' => __('msg_hosting_created')];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => __('msg_hosting_error') . ': ' . $e->getMessage()];
    }
}

/**
 * Get domain pricing comparison matrix
 */
function getDomainPriceComparison($domainName, $config) {
    $parts = explode('.', $domainName);
    $tld = 'com';
    if (count($parts) > 1) {
        $tld = implode('.', array_slice($parts, 1));
    }
    $tld = strtolower($tld);
    
    $pricingMatrix = $config['domain_prices'] ?? [];
    $prices = $pricingMatrix[$tld] ?? $pricingMatrix['com'] ?? [];
    
    $providers = [
        'Namecheap' => 'affiliate_namecheap',
        'Hostinger' => 'affiliate_hostinger',
        'NameSilo' => 'affiliate_namesilo',
        'Porkbun' => 'affiliate_porkbun',
        'Spaceship' => 'affiliate_spaceship',
        'Dynadot' => 'affiliate_dynadot',
        'Domain Name API' => 'affiliate_domainnameapi'
    ];
    
    $comparison = [];
    foreach ($providers as $name => $key) {
        $price = $prices[$name] ?? 'N/A';
        
        $providerCode = str_replace('affiliate_', '', $key);
        $affUrl = url('go?to=' . urlencode($providerCode));
        
        $comparison[] = [
            'provider' => $name,
            'price' => $price,
            'aff_url' => $affUrl
        ];
    }
    
    return $comparison;
}

/**
 * Dispatch webhooks on domain updates/alerts
 */
function triggerWebhookNotification($webhookUrl, $payload) {
    if (empty($webhookUrl)) return false;
    
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-DomainAwait-Event: domain_alert'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    return ($httpCode >= 200 && $httpCode < 300);
}

/**
 * Output user domains list as a CSV file
 */
function exportDomainsToCSV($myDomains) {
    // Clear buffer to prevent headers issue
    if (ob_get_level()) ob_end_clean();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=domainawait_export_' . date('Y-m-d') . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    // Write UTF-8 BOM for Excel Turkish character compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['Alan Adı', 'Son Geçerlilik Tarihi', 'Kayıt Tarihi', 'Kayıt Firması', 'Favori']);
    
    foreach ($myDomains as $row) {
        fputcsv($output, [
            $row['domain_name'],
            $row['expiration_date'] ?? 'N/A',
            $row['registration_date'] ?? 'N/A',
            $row['registrar'] ?? 'N/A',
            $row['is_favorite'] == 1 ? 'Evet' : 'Hayır'
        ]);
    }
    fclose($output);
    exit;
}

/**
 * Get inline SVG logo for domain registrars
 */
function getRegistrarLogo($provider) {
    switch (trim($provider ?? '')) {
        case 'Namecheap':
            return '<svg class="registrar-logo" viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px; flex-shrink: 0;"><rect width="24" height="24" rx="6" fill="#de3724"/><path d="M12 5l-5 5h3v5h4v-5h3l-5-5z" fill="#FFF"/></svg>';
        case 'Hostinger':
            return '<svg class="registrar-logo" viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px; flex-shrink: 0;"><rect width="24" height="24" rx="6" fill="#673de6"/><path d="M8 7h2.5v3.5h3V7H16v10h-2.5v-4h-3v4H8V7z" fill="#FFF"/></svg>';
        case 'NameSilo':
            return '<svg class="registrar-logo" viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px; flex-shrink: 0;"><rect width="24" height="24" rx="6" fill="#ff8c00"/><circle cx="12" cy="12" r="5" fill="#FFF"/><path d="M12 9v6M9 12h6" stroke="#ff8c00" stroke-width="1.5" stroke-linecap="round"/></svg>';
        case 'Porkbun':
            return '<svg class="registrar-logo" viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px; flex-shrink: 0;"><rect width="24" height="24" rx="6" fill="#f472b6"/><ellipse cx="12" cy="12" rx="5" ry="3.5" fill="#FFF"/><circle cx="10" cy="12" r="1" fill="#f472b6"/><circle cx="14" cy="12" r="1" fill="#f472b6"/></svg>';
        case 'Spaceship':
            return '<svg class="registrar-logo" viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px; flex-shrink: 0;"><rect width="24" height="24" rx="6" fill="#0f172a"/><path d="M12 6s2.5 3.5 2.5 6h-5c0-2.5 2.5-6 2.5-6z" fill="#38bdf8"/><path d="M9.5 12h5v2h-5v-2z" fill="#0284c7"/></svg>';
        case 'Dynadot':
            return '<svg class="registrar-logo" viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px; flex-shrink: 0;"><rect width="24" height="24" rx="6" fill="#d32f2f"/><path d="M8 7h4a5 5 0 0 1 5 5 5 5 0 0 1-5 5H8V7zm2.5 2.5v5H12a2.5 2.5 0 0 0 2.5-2.5 2.5 2.5 0 0 0-2.5-2.5h-1.5z" fill="#FFF"/></svg>';
        case 'Domain Name API':
            return '<svg class="registrar-logo" viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px; flex-shrink: 0;"><rect width="24" height="24" rx="6" fill="#0284c7"/><path d="M8 10l-3 2 3 2M16 10l3 2-3 2M13 8l-2 8" stroke="#FFF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        default:
            return '<svg class="registrar-logo" viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px; flex-shrink: 0;"><rect width="24" height="24" rx="6" fill="#4b5563"/><circle cx="12" cy="12" r="5" stroke="#FFF" stroke-width="1.5"/><line x1="7" y1="12" x2="17" y2="12" stroke="#FFF" stroke-width="1.5"/></svg>';
    }
}

/**
 * Load localized blog posts
 */
function getBlogPosts($lang = 'en') {
    if (!in_array($lang, ['en', 'tr', 'es', 'de'])) {
        $lang = 'en';
    }
    
    $posts = [];
    $mainPath = __DIR__ . '/../languages/blog_' . $lang . '.php';
    if (file_exists($mainPath)) {
        $posts = require $mainPath;
    } else {
        $posts = require __DIR__ . '/../languages/blog_en.php';
    }
    
    $guidePath = __DIR__ . '/../languages/blog_guide_' . $lang . '.php';
    if (file_exists($guidePath)) {
        $guidePosts = require $guidePath;
        $posts = array_merge($guidePosts, $posts);
    } else {
        $defaultGuidePath = __DIR__ . '/../languages/blog_guide_en.php';
        if (file_exists($defaultGuidePath)) {
            $guidePosts = require $defaultGuidePath;
            $posts = array_merge($guidePosts, $posts);
        }
    }
    
    return $posts;
}

/**
 * Load single localized blog post by slug
 */
function getBlogPostBySlug($slug, $lang = 'en') {
    $posts = getBlogPosts($lang);
    return $posts[$slug] ?? null;
}

/**
 * Load latest localized blog posts
 */
function getLatestBlogPosts($lang = 'en', $limit = 3) {
    $posts = getBlogPosts($lang);
    // Sort posts by date descending
    uasort($posts, function($a, $b) {
        return strcmp($b['date'] ?? '', $a['date'] ?? '');
    });
    return array_slice($posts, 0, $limit, true);
}

/**
 * Slugify category name for clean URL filtering
 */
function slugifyCategory($name) {
    $charMap = [
        'ı'=>'i', 'ş'=>'s', 'ğ'=>'g', 'ü'=>'u', 'ö'=>'o', 'ç'=>'c',
        'İ'=>'i', 'Ş'=>'s', 'Ğ'=>'g', 'Ü'=>'u', 'Ö'=>'o', 'Ç'=>'c',
        'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ñ'=>'n',
        'ä'=>'a', 'ö'=>'o', 'ü'=>'u', 'ß'=>'ss', 'Ö'=>'o', 'Ä'=>'a', 'Ü'=>'u'
    ];
    $name = strtr($name, $charMap);
    return strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '', str_replace(' ', '-', $name)));
}

/**
 * Retrieve blog posts filtered by category slug
 */
function getBlogPostsByCategory($categorySlug, $lang = 'en') {
    $posts = getBlogPosts($lang);
    $filtered = [];
    foreach ($posts as $slug => $post) {
        $postCatSlug = slugifyCategory($post['category']);
        if ($postCatSlug === $categorySlug) {
            $filtered[$slug] = $post;
        }
    }
    return $filtered;
}
