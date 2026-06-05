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
            $domainUrl = absolute_url("domain/" . urlencode($domainName));
            $namecheapAff = $config['affiliate_namecheap'] . "&query=" . urlencode($domainName);
            
            $emailBody = getFormattedEmail('mail_tpl_domain_expiry', [
                'domain_name' => esc($domainName),
                'expiry_date' => formatDate($ud['expiration_date'], 'd M Y'),
                'days_left' => max(0, round($daysToExpiry)),
                'panel_url' => $domainUrl
            ]);
            
            $sent = sendEmailNotification($email, $subject, $emailBody);
            if ($sent) {
                $stmtUpdate = $pdo->prepare("UPDATE user_domains SET {$alertFlagField} = 1 WHERE id = ?");
                $stmtUpdate->execute([$udId]);
                $logs[] = "Domain Email sent to $email for $domainName ($alertFlagField)";
                
                // Trigger Webhook if user is Premium
                if (!empty($ud['webhook_url']) && userPlanAllows($ud['api_plan'], 'webhook')) {
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
            $hostingUrl = absolute_url("panel/hosting");
            $hostingerAff = $config['affiliate_hostinger'];
            
            $emailBody = getFormattedEmail('mail_tpl_hosting_expiry', [
                'domain_name' => esc($domainName),
                'hosting_provider' => esc($provider),
                'expiry_date' => formatDate($uh['expiration_date'], 'd M Y'),
                'days_left' => max(0, round($daysToExpiry)),
                'panel_url' => $hostingUrl
            ]);
            
            $sent = sendEmailNotification($email, $subject, $emailBody);
            if ($sent) {
                $stmtUpdate = $pdo->prepare("UPDATE user_hostings SET {$alertFlagField} = 1 WHERE id = ?");
                $stmtUpdate->execute([$uhId]);
                $logs[] = "Hosting Email sent to $email for $domainName ($alertFlagField)";
                
                // Trigger Webhook if user is Premium
                if (!empty($uh['webhook_url']) && userPlanAllows($uh['api_plan'], 'webhook')) {
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
