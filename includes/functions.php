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
 * Helper to generate absolute URLs (required for email clients)
 */
function absolute_url($path = '') {
    $path = ltrim($path, '/');
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    $base = '';
    if (defined('BASE_PATH')) {
        $base = BASE_PATH;
    } else {
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = rtrim(dirname($script_name), '/\\');
    }
    $base = trim($base, '/\\');
    
    return $protocol . $host . ($base !== '' ? '/' . $base : '') . '/' . $path;
}

/**
 * Wraps dynamic email content in a premium HTML frame with site logo and footer
 */
function getEmailTemplateWrapper($contentHtml) {
    global $config;
    
    $logoUrl = absolute_url('assets/images/logo.png');
    $siteUrl = absolute_url('');
    $siteTitle = $config['site_title'] ?? 'TLDix';
    
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc($siteTitle) . '</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #0b0f19;
            font-family: "Outfit", "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #cbd5e1;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper {
            width: 100%;
            background-color: #0b0f19;
            padding: 40px 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }
        .header {
            background: linear-gradient(135deg, #0b0f19 0%, #1e1b4b 100%);
            padding: 30px 40px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            text-align: left;
        }
        .header img {
            height: 50px;
            width: auto;
            display: block;
            border: 0;
        }
        .content {
            padding: 40px;
            line-height: 1.6;
            font-size: 15px;
            color: #cbd5e1;
        }
        .content h1, .content h2, .content h3 {
            color: #ffffff;
            margin-top: 0;
            font-family: "Outfit", sans-serif;
            font-weight: 600;
        }
        .footer {
            background-color: #090d16;
            padding: 30px 40px;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 12px;
            color: #64748b;
        }
        .footer a {
            color: #6366f1;
            text-decoration: none;
            font-weight: 500;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 15px;
            margin-bottom: 15px;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <a href="' . $siteUrl . '" target="_blank">
                    <img src="' . $logoUrl . '" alt="' . esc($siteTitle) . ' Logo">
                </a>
            </div>
            <div class="content">
                ' . $contentHtml . '
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' <a href="' . $siteUrl . '" target="_blank">' . esc($siteTitle) . '</a>. Tüm Hakları Saklıdır.</p>
                <p>Long-term and reliable tracking infrastructure.</p>
            </div>
        </div>
    </div>
</body>
</html>';
}

/**
 * Hydrates, overrides, and completes customizable email templates
 */
function getFormattedEmail($templateKey, $replacements = []) {
    global $config;
    
    $defaults = [
        'mail_tpl_user_register' => '<h2>Hoş Geldiniz, {username}!</h2><p>TLDix platformuna başarıyla üye oldunuz. Artık alan adlarınızı ve barındırma (hosting) sürelerinizi tek bir noktadan güvenle takip edebilirsiniz.</p><p>Takip listenize yeni alan adları eklemek için hemen kullanıcı panelinize giriş yapabilirsiniz:</p><p><a href="{login_url}" class="btn">Panel Girişi Yap</a></p><p>Herhangi bir sorunuz olursa bizimle iletişime geçebilirsiniz.</p>',
        
        'mail_tpl_user_verify' => '<h2>E-posta Adresinizi Doğrulayın</h2><p>Merhaba {username},</p><p>TLDix platformuna başarıyla üye oldunuz. Hesabınızı aktifleştirmek ve hizmetleri kullanmaya başlamak için lütfen aşağıdaki bağlantıya tıklayarak e-posta adresinizi doğrulayın:</p><p><a href="{verify_url}" class="btn">E-postamı Doğrula</a></p><p>Bağlantı çalışmıyorsa aşağıdaki URL\'yi kopyalayıp tarayıcınıza yapıştırabilirsiniz:</p><p>{verify_url}</p>',
        
        'mail_tpl_user_forgot' => '<h2>Şifre Sıfırlama Talebi</h2><p>Hesabınız için şifre sıfırlama talebinde bulundunuz. Sizin için geçici bir şifre oluşturuldu:</p><div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; font-size: 18px; font-weight: bold; text-align: center; border: 1px dashed rgba(255,255,255,0.2); color: #6366f1; margin: 20px 0;">{temp_password}</div><p>Lütfen bu şifreyi kullanarak sisteme giriş yapın ve profil ayarlarınızdan şifrenizi hemen güncelleyin:</p><p><a href="{login_url}" class="btn">Giriş Yap</a></p><p>Bu talebi siz yapmadıysanız lütfen bu e-postayı dikkate almayın.</p>',
        
        'mail_tpl_admin_register' => '<h2>Yeni Üye Kaydı Bildirimi</h2><p>Sisteminizde yeni bir kullanıcı başarıyla kaydoldu:</p><ul><li><strong>Kullanıcı Adı:</strong> {username}</li><li><strong>E-posta:</strong> {email}</li><li><strong>Kayıt Tarihi:</strong> {date}</li></ul><p>Kullanıcı detaylarını incelemek için yönetici panelinizi ziyaret edebilirsiniz.</p>',
        
        'mail_tpl_admin_forgot' => '<h2>Şifre Sıfırlama Bildirimi</h2><p>Aşağıdaki kullanıcı şifre sıfırlama talebinde bulundu ve kendisine geçici şifre gönderildi:</p><ul><li><strong>Kullanıcı Adı:</strong> {username}</li><li><strong>E-posta:</strong> {email}</li><li><strong>Tarih:</strong> {date}</li></ul>',
        
        'mail_tpl_domain_expiry' => '<h2>Domain Süresi Sona Eriyor!</h2><p>Takip listenizdeki <strong>{domain_name}</strong> alan adınızın süresi yakında doluyor.</p><ul><li><strong>Alan Adı:</strong> {domain_name}</li><li><strong>Bitiş Tarihi:</strong> {expiry_date}</li><li><strong>Kalan Gün:</strong> {days_left}</li></ul><p>Alan adınızı kaybetmemek ve kesintisiz hizmet almaya devam etmek için hemen yenileme işlemlerini yapmanızı öneririz.</p><p><a href="{panel_url}" class="btn">Domain Listeme Git</a></p>',
        
        'mail_tpl_hosting_expiry' => '<h2>Hosting Hizmet Süresi Sona Eriyor!</h2><p>Takip listenizdeki <strong>{domain_name}</strong> alan adına ait hosting (barındırma) paketinizin süresi yakında doluyor.</p><ul><li><strong>Hizmet Sunucusu:</strong> {hosting_provider}</li><li><strong>Bitiş Tarihi:</strong> {expiry_date}</li><li><strong>Kalan Gün:</strong> {days_left}</li></ul><p>Web sitenizin yayınının kesilmesini önlemek için hosting paketinizi yenilemeyi unutmayın.</p><p><a href="{panel_url}" class="btn">Hosting Listeme Git</a></p>'
    ];
    
    $templateContent = $config[$templateKey] ?? ($defaults[$templateKey] ?? '');
    
    // Auto replace placeholders
    foreach ($replacements as $placeholder => $val) {
        $templateContent = str_replace('{' . $placeholder . '}', $val, $templateContent);
    }
    
    return getEmailTemplateWrapper($templateContent);
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
    
    $fromEmail = $config['smtp_from_email'] ?? 'alerts@tldix.local';
    $fromName = $config['smtp_from_name'] ?? 'TLDix Alerts';
    
    // Headers for HTML mail
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . $fromName . " <" . $fromEmail . ">\r\n";
    $headers .= "Reply-To: " . $fromEmail . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    try {
        // Check if SMTP is enabled
        if ((int)($config['email_use_smtp'] ?? 0) === 1) {
            if (!function_exists('fsockopen')) {
                error_log("SMTP email skipped: fsockopen() is not available on this server.");
                return false;
            }

            $host = trim($config['smtp_host'] ?? '');
            $port = (int)($config['smtp_port'] ?? 0);
            $username = $config['smtp_user'] ?? '';
            $password = $config['smtp_pass'] ?? '';

            if ($host === '' || $port <= 0) {
                error_log("SMTP email skipped: smtp_host or smtp_port is not configured.");
                return false;
            }

            $dnsHost = preg_replace('/^[a-z][a-z0-9+.-]*:\/\//i', '', $host);
            $dnsHost = preg_replace('/\/.*$/', '', $dnsHost);
            $dnsHost = preg_replace('/:\d+$/', '', $dnsHost);
            if (filter_var($dnsHost, FILTER_VALIDATE_IP) === false) {
                $resolvedIps = @gethostbynamel($dnsHost);
                if (empty($resolvedIps)) {
                    error_log("SMTP DNS lookup failed for host: $dnsHost");
                    return false;
                }
            }

            if ($port === 465 && strpos($host, 'ssl://') !== 0) {
                $host = 'ssl://' . $host;
            }

            $socket = @fsockopen($host, $port, $errno, $errstr, 15);
            if (!$socket) {
                error_log("SMTP connection failed: $errstr ($errno)");
                return false;
            }

            $getResponse = function($socket) {
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
                return $data;
            };

            $getResponse($socket); // Read banner

            // HELO
            $serverName = $_SERVER['SERVER_NAME'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
            fwrite($socket, "EHLO " . $serverName . "\r\n");
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
        }

        if (!function_exists('mail')) {
            error_log("Email skipped: PHP mail() is not available on this server.");
            return false;
        }

        // Fallback to PHP's built-in mail() function
        return mail($to, $subject, $messageHtml, $headers);
    } catch (Throwable $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
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
        'X-TLDix-Event: domain_alert'
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
    header('Content-Disposition: attachment; filename=tldix_export_' . date('Y-m-d') . '.csv');
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
