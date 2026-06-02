<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

require_once __DIR__ . '/whois.php';
require_once __DIR__ . '/functions.php';

function runCronJobs($pdo) {
    global $config;
    
    $nowTime = time();
    $logs = [];
    $logs[] = "[" . date('Y-m-d H:i:s') . "] Starting cron job run...";
    
    // 1. Refresh cache of master domains table
    $stmt = $pdo->query("SELECT * FROM domains");
    $domains = $stmt->fetchAll();
    
    foreach ($domains as $domain) {
        $domainName = $domain['domain_name'];
        $lastChecked = strtotime($domain['last_checked']);
        $expTime = strtotime($domain['expiration_date']);
        $daysToExpiry = ($expTime - $nowTime) / 86400;
        
        // Cache refresh threshold: 4 hours if expiring in < 7 days, else 12 hours
        $cacheThreshold = ($daysToExpiry < 7) ? 14400 : 43200;
        
        if ($nowTime - $lastChecked > $cacheThreshold) {
            $logs[] = "Refreshing cache for $domainName...";
            getOrUpdateDomain($pdo, $domainName, true);
        }
    }
    
    // 2. Check and Dispatch User Domain Notifications
    // Query user_domains with user details and master domain info
    $stmtUD = $pdo->query("
        SELECT ud.*, u.email, u.username, u.api_plan, u.webhook_url, d.expiration_date, d.registration_date, d.registrar 
        FROM user_domains ud
        JOIN users u ON ud.user_id = u.id
        JOIN domains d ON ud.domain_name = d.domain_name
    ");
    $userDomains = $stmtUD->fetchAll();
    
    foreach ($userDomains as $ud) {
        $udId = $ud['id'];
        $domainName = $ud['domain_name'];
        $email = $ud['email'];
        $username = $ud['username'];
        $expTime = strtotime($ud['expiration_date']);
        $daysToExpiry = ($expTime - $nowTime) / 86400;
        
        $sendAlert = false;
        $alertFlagField = '';
        $subject = '';
        $timeLabel = '';
        
        // Check levels based on user settings
        if ($daysToExpiry <= 0 && !$ud['notified_0_sent']) {
            $sendAlert = true;
            $alertFlagField = 'notified_0_sent';
            $subject = "ALARM: {$domainName} Alan Adınızın Süresi Doldu!";
            $timeLabel = "süresi bugün doldu";
        } elseif ($daysToExpiry <= 1 && $daysToExpiry > 0 && $ud['notify_1'] && !$ud['notified_1_sent']) {
            $sendAlert = true;
            $alertFlagField = 'notified_1_sent';
            $subject = "DİKKAT: {$domainName} Süresi Yarın Doluyor!";
            $timeLabel = "süresi yarın doluyor";
        } elseif ($daysToExpiry <= 3 && $daysToExpiry > 1 && $ud['notify_3'] && !$ud['notified_3_sent']) {
            $sendAlert = true;
            $alertFlagField = 'notified_3_sent';
            $subject = "UYARI: {$domainName} Süresi 3 Gün İçinde Doluyor!";
            $timeLabel = "süresi 3 gün içinde doluyor";
        } elseif ($daysToExpiry <= 7 && $daysToExpiry > 3 && $ud['notify_7'] && !$ud['notified_7_sent']) {
            $sendAlert = true;
            $alertFlagField = 'notified_7_sent';
            $subject = "UYARI: {$domainName} Süresi 7 Gün İçinde Doluyor!";
            $timeLabel = "süresi 7 gün içinde doluyor";
        } elseif ($daysToExpiry <= 14 && $daysToExpiry > 7 && $ud['notify_14'] && !$ud['notified_14_sent']) {
            $sendAlert = true;
            $alertFlagField = 'notified_14_sent';
            $subject = "Hatırlatma: {$domainName} Süresi 14 Gün İçinde Doluyor!";
            $timeLabel = "süresi 14 gün içinde doluyor";
        } elseif ($daysToExpiry <= 30 && $daysToExpiry > 14 && $ud['notify_30'] && !$ud['notified_30_sent']) {
            $sendAlert = true;
            $alertFlagField = 'notified_30_sent';
            $subject = "Hatırlatma: {$domainName} Süresi 30 Gün İçinde Doluyor!";
            $timeLabel = "süresi 30 gün içinde doluyor";
        } elseif ($daysToExpiry <= 60 && $daysToExpiry > 30 && $ud['notify_60'] && !$ud['notified_60_sent']) {
            $sendAlert = true;
            $alertFlagField = 'notified_60_sent';
            $subject = "Hatırlatma: {$domainName} Süresi 60 Gün İçinde Doluyor!";
            $timeLabel = "süresi 60 gün içinde doluyor";
        }
        
        if ($sendAlert) {
            $domainUrl = "http://" . $_SERVER['HTTP_HOST'] . "/domain/" . urlencode($domainName);
            $namecheapAff = $config['affiliate_namecheap'] . "&query=" . urlencode($domainName);
            
            $emailBody = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <style>
                    body { font-family: 'Segoe UI', Helvetica, Arial, sans-serif; background-color: #0b0f19; color: #f3f4f6; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
                    .card { background-color: #111827; border: 1px solid #1f2937; border-radius: 16px; padding: 32px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3); }
                    .logo { font-size: 24px; font-weight: bold; color: #6366f1; text-decoration: none; margin-bottom: 24px; display: inline-block; }
                    h1 { font-size: 20px; margin-top: 0; color: #ffffff; }
                    p { font-size: 16px; line-height: 1.6; color: #9ca3af; }
                    .highlight { color: #f43f5e; font-weight: bold; }
                    .btn-group { margin-top: 24px; display: flex; gap: 12px; flex-wrap: wrap; }
                    .btn { display: inline-block; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #ffffff !important; font-weight: bold; padding: 12px 24px; border-radius: 8px; text-decoration: none; text-align: center; }
                    .btn-secondary { background: rgba(255,255,255,0.05); color: #ffffff !important; border: 1px solid rgba(255,255,255,0.1); }
                    .footer { margin-top: 32px; font-size: 12px; color: #4b5563; text-align: center; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='card'>
                        <a href='http://{$_SERVER['HTTP_HOST']}' class='logo'>TLDix</a>
                        <h1>Alan Adı Yenileme Bildirimi</h1>
                        <p>Merhaba <strong>{$username}</strong>,</p>
                        <p>Takip listenizdeki <span style='color:#ffffff; font-weight:bold;'>{$domainName}</span> alan adının <span class='highlight'>{$timeLabel}!</span></p>
                        <p><strong>Son Geçerlilik Tarihi:</strong> " . formatDate($ud['expiration_date'], 'd M Y') . "</p>
                        <p><strong>Kayıt Kuruluşu:</strong> {$ud['registrar']}</p>
                        
                        <div class='btn-group'>
                            <a href='{$domainUrl}' class='btn'>Panelde İncele</a>
                            <a href='{$namecheapAff}' target='_blank' class='btn btn-secondary'>Namecheap ile Yenile / Kaydet</a>
                        </div>
                    </div>
                    <div class='footer'>
                        Bu e-posta, {$domainName} alan adını TLDix üzerinde takibe aldığınız için gönderilmiştir.<br>
                        &copy; " . date('Y') . " TLDix. Tüm hakları saklıdır.
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $sent = sendEmailNotification($email, $subject, $emailBody);
            if ($sent) {
                $stmtUpdate = $pdo->prepare("UPDATE user_domains SET {$alertFlagField} = 1 WHERE id = ?");
                $stmtUpdate->execute([$udId]);
                $logs[] = "Domain Email sent to $email for $domainName ($alertFlagField)";
                
                // Trigger Webhook if user is Premium
                if (!empty($ud['webhook_url']) && in_array($ud['api_plan'], ['bronze', 'silver', 'gold'])) {
                    $payload = [
                        'event' => 'domain_expiration_alert',
                        'domain' => $domainName,
                        'expiration_date' => $ud['expiration_date'],
                        'registrar' => $ud['registrar'],
                        'alert_threshold' => str_replace('notified_', '', str_replace('_sent', '', $alertFlagField)),
                        'message' => $subject,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    triggerWebhookNotification($ud['webhook_url'], $payload);
                }
            } else {
                $logs[] = "Failed to send domain email to $email for $domainName ($alertFlagField)";
            }
        }
    }
    
    // 3. Check and Dispatch User Hosting Notifications
    $stmtUH = $pdo->query("
        SELECT uh.*, u.email, u.username, u.api_plan, u.webhook_url 
        FROM user_hostings uh
        JOIN users u ON uh.user_id = u.id
    ");
    $userHostings = $stmtUH->fetchAll();
    
    foreach ($userHostings as $uh) {
        $uhId = $uh['id'];
        $domainName = $uh['domain_name'];
        $provider = $uh['hosting_provider'];
        $email = $uh['email'];
        $username = $uh['username'];
        $expTime = strtotime($uh['expiration_date']);
        $daysToExpiry = ($expTime - $nowTime) / 86400;
        
        $sendAlert = false;
        $alertFlagField = '';
        $subject = '';
        $timeLabel = '';
        
        if ($daysToExpiry <= 0 && !$uh['notified_0_sent']) {
            $sendAlert = true;
            $alertFlagField = 'notified_0_sent';
            $subject = "ALARM: {$domainName} Hosting Süresi Doldu!";
            $timeLabel = "süresi bugün doldu";
        } elseif ($daysToExpiry <= 1 && $daysToExpiry > 0 && $uh['notify_1'] && !$uh['notified_1_sent']) {
            $sendAlert = true;
            $alertFlagField = 'notified_1_sent';
            $subject = "DİKKAT: {$domainName} Hosting Süresi Yarın Doluyor!";
            $timeLabel = "süresi yarın doluyor";
        } elseif ($daysToExpiry <= 7 && $daysToExpiry > 1 && $uh['notify_7'] && !$uh['notified_7_sent']) {
            $sendAlert = true;
            $alertFlagField = 'notified_7_sent';
            $subject = "UYARI: {$domainName} Hosting Süresi 7 Gün İçinde Doluyor!";
            $timeLabel = "süresi 7 gün içinde doluyor";
        } elseif ($daysToExpiry <= 30 && $daysToExpiry > 7 && $uh['notify_30'] && !$uh['notified_30_sent']) {
            $sendAlert = true;
            $alertFlagField = 'notified_30_sent';
            $subject = "Hatırlatma: {$domainName} Hosting Süresi 30 Gün İçinde Doluyor!";
            $timeLabel = "süresi 30 gün içinde doluyor";
        }
        
        if ($sendAlert) {
            $hostingerAff = $config['affiliate_hostinger'];
            
            $emailBody = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <style>
                    body { font-family: 'Segoe UI', Helvetica, Arial, sans-serif; background-color: #0b0f19; color: #f3f4f6; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
                    .card { background-color: #111827; border: 1px solid #1f2937; border-radius: 16px; padding: 32px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3); }
                    .logo { font-size: 24px; font-weight: bold; color: #6366f1; text-decoration: none; margin-bottom: 24px; display: inline-block; }
                    h1 { font-size: 20px; margin-top: 0; color: #ffffff; }
                    p { font-size: 16px; line-height: 1.6; color: #9ca3af; }
                    .highlight { color: #f43f5e; font-weight: bold; }
                    .btn-group { margin-top: 24px; display: flex; gap: 12px; flex-wrap: wrap; }
                    .btn { display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff !important; font-weight: bold; padding: 12px 24px; border-radius: 8px; text-decoration: none; text-align: center; }
                    .btn-secondary { background: rgba(255,255,255,0.05); color: #ffffff !important; border: 1px solid rgba(255,255,255,0.1); }
                    .footer { margin-top: 32px; font-size: 12px; color: #4b5563; text-align: center; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='card'>
                        <a href='http://{$_SERVER['HTTP_HOST']}' class='logo'>TLDix</a>
                        <h1>Hosting Yenileme Bildirimi</h1>
                        <p>Merhaba <strong>{$username}</strong>,</p>
                        <p>Takip listenizdeki <span style='color:#ffffff; font-weight:bold;'>{$domainName}</span> alan adına bağlı <strong>{$provider}</strong> hosting paketinizin <span class='highlight'>{$timeLabel}!</span></p>
                        <p><strong>Hosting Bitiş Tarihi:</strong> " . formatDate($uh['expiration_date'], 'd M Y') . "</p>
                        
                        <div class='btn-group'>
                            <a href='http://{$_SERVER['HTTP_HOST']}/panel/hosting' class='btn'>Hosting Panelini Aç</a>
                            <a href='{$hostingerAff}' target='_blank' class='btn btn-secondary'>%70 İndirimli Yeni Hosting Satın Al</a>
                        </div>
                    </div>
                    <div class='footer'>
                        Bu e-posta, {$domainName} alan adının bağlı olduğu hosting paketini TLDix üzerinde takibe aldığınız için gönderilmiştir.<br>
                        &copy; " . date('Y') . " TLDix. Tüm hakları saklıdır.
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $sent = sendEmailNotification($email, $subject, $emailBody);
            if ($sent) {
                $stmtUpdate = $pdo->prepare("UPDATE user_hostings SET {$alertFlagField} = 1 WHERE id = ?");
                $stmtUpdate->execute([$uhId]);
                $logs[] = "Hosting Email sent to $email for $domainName ($alertFlagField)";
                
                // Trigger Webhook if user is Premium
                if (!empty($uh['webhook_url']) && in_array($uh['api_plan'], ['bronze', 'silver', 'gold'])) {
                    $payload = [
                        'event' => 'hosting_expiration_alert',
                        'domain' => $domainName,
                        'hosting_provider' => $provider,
                        'expiration_date' => $uh['expiration_date'],
                        'alert_threshold' => str_replace('notified_', '', str_replace('_sent', '', $alertFlagField)),
                        'message' => $subject,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    triggerWebhookNotification($uh['webhook_url'], $payload);
                }
            } else {
                $logs[] = "Failed to send hosting email to $email for $domainName ($alertFlagField)";
            }
        }
    }
    
    $logs[] = "[" . date('Y-m-d H:i:s') . "] Cron job run finished.";
    return implode("\n", $logs);
}
