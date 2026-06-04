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

    global $config;
    $configuredBase = trim((string)($config['site_url'] ?? ''));
    if ($configuredBase !== '') {
        return rtrim($configuredBase, '/') . '/' . $path;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $forwardedProto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    if ($forwardedProto === 'https') {
        $isHttps = true;
    }

    $protocol = $isHttps ? 'https://' : 'http://';
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

function mediaUrl($path = '') {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('/^(https?:\/\/|data:|\/\/)/i', $path)) {
        return $path;
    }
    return url($path);
}

function extractHtmlAttributeValue($html, $tagName, $attributeName) {
    $html = (string)$html;
    $tagName = preg_quote($tagName, '/');
    $attributeName = preg_quote($attributeName, '/');

    if (!preg_match_all('/<' . $tagName . '\b[^>]*>/i', $html, $tags)) {
        return '';
    }

    foreach ($tags[0] as $tag) {
        if (preg_match('/\s' . $attributeName . '\s*=\s*(["\'])(.*?)\1/i', $tag, $matches)) {
            return trim(html_entity_decode($matches[2], ENT_QUOTES, 'UTF-8'));
        }
    }

    return '';
}

function normalizeGoogleSearchConsoleToken($value) {
    $raw = trim((string)$value);
    if ($raw === '') return '';

    $token = '';
    if (stripos($raw, 'google-site-verification') !== false && stripos($raw, '<meta') !== false) {
        $token = extractHtmlAttributeValue($raw, 'meta', 'content');
    }
    if ($token === '' && preg_match('/google-site-verification\s*=\s*([A-Za-z0-9_-]+)/i', $raw, $matches)) {
        $token = $matches[1];
    }
    if ($token === '') {
        $token = trim(strip_tags($raw));
        $token = preg_replace('/^google-site-verification\s*=\s*/i', '', $token);
        $token = trim($token, " \t\n\r\0\x0B\"'");
    }

    return preg_match('/^[A-Za-z0-9_-]{10,200}$/', $token) ? $token : '';
}

function normalizeBingVerificationToken($value) {
    $raw = trim((string)$value);
    if ($raw === '') return '';

    $token = '';
    if (stripos($raw, '<meta') !== false) {
        $token = extractHtmlAttributeValue($raw, 'meta', 'content');
    }
    if ($token === '' && preg_match('/(?:msvalidate\.01|bing-site-verification)\s*=\s*([A-Za-z0-9_-]+)/i', $raw, $matches)) {
        $token = $matches[1];
    }
    if ($token === '') {
        $token = trim(strip_tags($raw));
        $token = preg_replace('/^(?:msvalidate\.01|bing-site-verification)\s*=\s*/i', '', $token);
        $token = trim($token, " \t\n\r\0\x0B\"'");
    }

    return preg_match('/^[A-Za-z0-9_-]{8,200}$/', $token) ? $token : '';
}

function normalizeGoogleAnalyticsId($value) {
    if (preg_match('/\bG-[A-Z0-9]+\b/i', (string)$value, $matches)) {
        return strtoupper($matches[0]);
    }
    return '';
}

function normalizeGoogleTagManagerId($value) {
    if (preg_match('/\bGTM-[A-Z0-9]+\b/i', (string)$value, $matches)) {
        return strtoupper($matches[0]);
    }
    return '';
}

function normalizeGoogleAdsensePublisherId($value) {
    if (preg_match('/\b(?:ca-)?(pub-\d{8,32})\b/i', (string)$value, $matches)) {
        return strtolower($matches[1]);
    }
    return '';
}

function normalizeCloudflareAnalyticsToken($value) {
    $raw = trim((string)$value);
    if ($raw === '') return '';

    $token = '';
    if (preg_match('/["\']token["\']\s*:\s*["\']([^"\']+)["\']/i', $raw, $matches)) {
        $token = trim($matches[1]);
    }
    if ($token === '') {
        $token = trim(strip_tags($raw));
        $token = trim($token, " \t\n\r\0\x0B\"'");
    }

    return preg_match('/^[A-Za-z0-9_-]{10,200}$/', $token) ? $token : '';
}

function normalizeIntegrationSetting($key, $value) {
    switch ($key) {
        case 'google_search_console':
            return normalizeGoogleSearchConsoleToken($value);
        case 'bing_verification':
            return normalizeBingVerificationToken($value);
        case 'google_analytics_id':
            return normalizeGoogleAnalyticsId($value);
        case 'google_tag_manager':
            return normalizeGoogleTagManagerId($value);
        case 'google_adsense_id':
            return normalizeGoogleAdsensePublisherId($value);
        case 'cf_analytics_token':
            return normalizeCloudflareAnalyticsToken($value);
        default:
            return $value;
    }
}

function shouldRenderCustomHeadCode($value) {
    return preg_match('/<(meta|link|script|style|noscript)\b/i', (string)$value) === 1;
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
        
        'mail_tpl_admin_register' => '<h2>Yeni Üye Kaydı Bildirimi</h2><p>Sisteminizde yeni bir kullanıcı başarıyla kaydoldu:</p><ul><li><strong>Kullanıcı Adı:</strong> {username}</li><li><strong>E-posta:</strong> {email}</li><li><strong>Kayıt Tarihi:</strong> {date}</li><li><strong>Doğrulama Maili Durumu:</strong> {mail_status}</li></ul><p><strong>Kullanıcı Doğrulama Bağlantısı:</strong> <a href="{verify_url}">{verify_url}</a></p><p>Kullanıcı detaylarını incelemek için yönetici panelinizi ziyaret edebilirsiniz.</p>',
        
        'mail_tpl_admin_forgot' => '<h2>Şifre Sıfırlama Bildirimi</h2><p>Aşağıdaki kullanıcı şifre sıfırlama talebinde bulundu. Kullanıcıya gönderilen geçici şifre ve giriş bağlantısı aşağıdadır:</p><ul><li><strong>Kullanıcı Adı:</strong> {username}</li><li><strong>E-posta:</strong> {email}</li><li><strong>Geçici Şifre:</strong> <code>{temp_password}</code></li><li><strong>Giriş Bağlantısı:</strong> <a href="{login_url}">{login_url}</a></li><li><strong>Tarih:</strong> {date}</li></ul><p>Güvenlik için kullanıcı giriş yaptıktan sonra şifresini profil ayarlarından değiştirmelidir.</p>',
        
        'mail_tpl_domain_expiry' => '<h2>Domain Süresi Sona Eriyor!</h2><p>Takip listenizdeki <strong>{domain_name}</strong> alan adınızın süresi yakında doluyor.</p><ul><li><strong>Alan Adı:</strong> {domain_name}</li><li><strong>Bitiş Tarihi:</strong> {expiry_date}</li><li><strong>Kalan Gün:</strong> {days_left}</li></ul><p>Alan adınızı kaybetmemek ve kesintisiz hizmet almaya devam etmek için hemen yenileme işlemlerini yapmanızı öneririz.</p><p><a href="{panel_url}" class="btn">Domain Listeme Git</a></p>',
        
        'mail_tpl_hosting_expiry' => '<h2>Hosting Hizmet Süresi Sona Eriyor!</h2><p>Takip listenizdeki <strong>{domain_name}</strong> alan adına ait hosting (barındırma) paketinizin süresi yakında doluyor.</p><ul><li><strong>Hizmet Sunucusu:</strong> {hosting_provider}</li><li><strong>Bitiş Tarihi:</strong> {expiry_date}</li><li><strong>Kalan Gün:</strong> {days_left}</li></ul><p>Web sitenizin yayınının kesilmesini önlemek için hosting paketinizi yenilemeyi unutmayın.</p><p><a href="{panel_url}" class="btn">Hosting Listeme Git</a></p>'
    ];
    
    $templateContent = $config[$templateKey] ?? ($defaults[$templateKey] ?? '');

    if (in_array($templateKey, ['mail_tpl_user_forgot', 'mail_tpl_admin_forgot'], true)) {
        if (array_key_exists('temp_password', $replacements) && strpos($templateContent, '{temp_password}') === false) {
            $templateContent .= '<p><strong>Geçici Şifre:</strong> <code>{temp_password}</code></p>';
        }
        if (array_key_exists('login_url', $replacements) && strpos($templateContent, '{login_url}') === false) {
            $templateContent .= '<p><a href="{login_url}" class="btn">Giriş Yap</a></p><p>Giriş bağlantısı: <a href="{login_url}">{login_url}</a></p>';
        }
    }

    if ($templateKey === 'mail_tpl_user_verify' && array_key_exists('verify_url', $replacements) && strpos($templateContent, '{verify_url}') === false) {
        $templateContent .= '<p><a href="{verify_url}" class="btn">E-postamı Doğrula</a></p><p>Doğrulama bağlantısı: <a href="{verify_url}">{verify_url}</a></p>';
    }

    if ($templateKey === 'mail_tpl_admin_register') {
        if (array_key_exists('mail_status', $replacements) && strpos($templateContent, '{mail_status}') === false) {
            $templateContent .= '<p><strong>Doğrulama Maili Durumu:</strong> {mail_status}</p>';
        }
        if (array_key_exists('verify_url', $replacements) && $replacements['verify_url'] !== '' && strpos($templateContent, '{verify_url}') === false) {
            $templateContent .= '<p><strong>Kullanıcı Doğrulama Bağlantısı:</strong> <a href="{verify_url}">{verify_url}</a></p>';
        }
    }
    
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

function normalizeSocialLinkUrl($url) {
    $url = trim((string)$url);
    if ($url === '') {
        return '';
    }

    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'https://' . $url;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }

    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : '';
}

function normalizeSocialLinkName($name) {
    $name = trim(strip_tags((string)$name));
    $name = preg_replace('/\s+/', ' ', $name);
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 60, 'UTF-8');
    }
    return substr($name, 0, 60);
}

function normalizeSocialLinks($rawLinks) {
    $links = [];

    if (isset($rawLinks['name'], $rawLinks['url']) && is_array($rawLinks['name']) && is_array($rawLinks['url'])) {
        foreach ($rawLinks['name'] as $idx => $name) {
            $links[] = [
                'name' => $name,
                'url' => $rawLinks['url'][$idx] ?? '',
            ];
        }
    } elseif (is_array($rawLinks)) {
        $links = $rawLinks;
    }

    $normalized = [];
    $seen = [];
    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }

        $name = normalizeSocialLinkName($link['name'] ?? '');
        $url = normalizeSocialLinkUrl($link['url'] ?? '');
        if ($name === '' || $url === '') {
            continue;
        }

        $key = strtolower($name . '|' . $url);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $normalized[] = ['name' => $name, 'url' => $url];
        if (count($normalized) >= 12) {
            break;
        }
    }

    return $normalized;
}

function getConfiguredSocialLinks($cfg = null) {
    if ($cfg === null) {
        global $config;
        $cfg = $config;
    }

    $decoded = [];
    $json = trim((string)($cfg['social_links_json'] ?? ''));
    if ($json !== '') {
        $parsed = json_decode($json, true);
        if (is_array($parsed)) {
            $decoded = normalizeSocialLinks($parsed);
        }
    }

    if (!empty($decoded)) {
        return $decoded;
    }

    $legacyMap = [
        'twitter' => 'Twitter',
        'github' => 'Github',
        'linkedin' => 'Linkedin',
        'instagram' => 'Instagram',
    ];
    $legacyLinks = [];
    foreach ($legacyMap as $key => $name) {
        $url = trim((string)($cfg['social_' . $key . '_url'] ?? ''));
        if ($url !== '' && $url !== '#') {
            $legacyLinks[] = ['name' => $name, 'url' => $url];
        }
    }

    return normalizeSocialLinks($legacyLinks);
}

function formatSocialDisplayName($name) {
    $name = normalizeSocialLinkName($name);
    if ($name === '') {
        return '';
    }

    if (function_exists('mb_strtolower') && function_exists('mb_strtoupper') && function_exists('mb_substr')) {
        $lower = mb_strtolower($name, 'UTF-8');
        return mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($lower, 1, 60, 'UTF-8');
    }

    return ucfirst(strtolower($name));
}

/**
 * Format date nicely
 */
function formatDate($dateStr, $format = 'd M Y, H:i') {
    if (!$dateStr) return 'N/A';
    $time = strtotime($dateStr);
    return $time ? date($format, $time) : 'N/A';
}

function sanitizeAffiliateCode($code) {
    $code = strtolower(trim((string)$code));
    $code = preg_replace('/[^a-z0-9_]+/', '_', $code);
    return trim($code, '_');
}

function getBuiltInAffiliateProviders($cfg = null) {
    if ($cfg === null) {
        global $config;
        $cfg = $config;
    }

    $defs = [
        'namecheap' => ['Namecheap', 'domain', 'affiliate_namecheap', 'Budget-friendly domain registration and renewals.', 'View Deal', 10],
        'godaddy' => ['GoDaddy', 'domain', 'affiliate_godaddy', 'Large registrar network with domain and business tools.', 'View Deal', 20],
        'namesilo' => ['NameSilo', 'domain', 'affiliate_namesilo', 'Simple domain registration with transparent pricing.', 'View Deal', 30],
        'porkbun' => ['Porkbun', 'domain', 'affiliate_porkbun', 'Low-cost registrar with developer-friendly DNS tools.', 'View Deal', 40],
        'dynadot' => ['Dynadot', 'domain', 'affiliate_dynadot', 'Registrar and marketplace tools for domain investors.', 'View Deal', 50],
        'spaceship' => ['Spaceship', 'domain', 'affiliate_spaceship', 'Modern registrar experience from the Namecheap team.', 'View Deal', 60],
        'domainnameapi' => ['Domain Name API', 'domain', 'affiliate_domainnameapi', 'Registrar/reseller API for automated domain workflows.', 'View Deal', 70],

        'hostinger' => ['Hostinger', 'hosting', 'affiliate_hostinger', 'Affordable hosting bundles for new websites and side projects.', 'View Hosting', 10],
        'bluehost' => ['Bluehost', 'hosting', 'affiliate_bluehost', 'WordPress-friendly hosting plans for small teams.', 'View Hosting', 20],
        'siteground' => ['SiteGround', 'hosting', 'affiliate_siteground', 'Managed hosting with strong support and performance.', 'View Hosting', 30],
        'kinsta' => ['Kinsta', 'hosting', 'affiliate_kinsta', 'Premium managed WordPress hosting for growing businesses.', 'View Hosting', 40],
        'wpengine' => ['WP Engine', 'hosting', 'affiliate_wpengine', 'Managed WordPress platform for professional teams.', 'View Hosting', 50],
        'interserver' => ['InterServer', 'hosting', 'affiliate_interserver', 'Reliable hosting with flexible monthly pricing.', 'View Hosting', 60],

        'namecheap_ssl' => ['Namecheap SSL', 'ssl', 'affiliate_namecheap_ssl', 'Budget-friendly SSL certificates with quick activation.', 'View SSL', 10],
        'ssls' => ['SSLs.com', 'ssl', 'affiliate_ssls', 'Specialized SSL certificate marketplace with discount pricing.', 'View SSL', 20],
        'ssldragon' => ['SSL Dragon', 'ssl', 'affiliate_ssldragon', 'SSL certificates and validation options for many use cases.', 'View SSL', 30],

        'google_workspace' => ['Google Workspace', 'email', 'affiliate_google_workspace', 'Business email and collaboration tools for teams.', 'View Email', 10],
        'zoho_mail' => ['Zoho Mail', 'email', 'affiliate_zoho_mail', 'Privacy-focused business email with flexible plans.', 'View Email', 20],
        'titan_email' => ['Titan Email', 'email', 'affiliate_titan_email', 'Professional email hosting for domain owners.', 'View Email', 30],

        'afternic' => ['Afternic', 'marketplace', 'affiliate_afternic', 'GoDaddy network marketplace for buying and selling domains.', 'Visit Marketplace', 10],
        'sedo' => ['Sedo', 'marketplace', 'affiliate_sedo', 'Established domain marketplace and brokerage platform.', 'Visit Marketplace', 20],
        'dan' => ['Dan.com', 'marketplace', 'affiliate_dan', 'Fast domain landing pages and sales workflow.', 'Visit Marketplace', 30],
        'atom' => ['Atom', 'marketplace', 'affiliate_atom', 'Premium brand and domain marketplace.', 'Visit Marketplace', 40],
        'dynadot_mkt' => ['Dynadot Marketplace', 'marketplace', 'affiliate_dynadot_mkt', 'Auction and marketplace tools from Dynadot.', 'Visit Marketplace', 50],
    ];

    $colors = [
        'domain' => 'linear-gradient(135deg, #6366f1 0%, #2563eb 100%)',
        'hosting' => 'linear-gradient(135deg, #14b8a6 0%, #0f766e 100%)',
        'ssl' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
        'email' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
        'marketplace' => 'linear-gradient(135deg, #8b5cf6 0%, #be185d 100%)',
    ];

    $providers = [];
    foreach ($defs as $code => $def) {
        [$name, $category, $key, $description, $buttonLabel, $sortOrder] = $def;
        $providers[$code] = [
            'id' => null,
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'settings_key' => $key,
            'target_url' => $cfg[$key] ?? '',
            'description' => $description,
            'button_label' => $buttonLabel,
            'is_active' => 1,
            'sort_order' => $sortOrder,
            'source' => 'builtin',
            'color' => $colors[$category] ?? $colors['domain'],
        ];
    }

    return $providers;
}

function getAffiliateProviders($db = null, $cfg = null, $category = null, $includeInactive = false) {
    if ($cfg === null) {
        global $config;
        $cfg = $config;
    }
    if ($db === null) {
        global $pdo;
        $db = $pdo;
    }

    $providers = getBuiltInAffiliateProviders($cfg);

    if ($db instanceof PDO) {
        try {
            $rows = $db->query("SELECT * FROM affiliate_partners ORDER BY sort_order ASC, name ASC")->fetchAll();
            foreach ($rows as $row) {
                $code = sanitizeAffiliateCode($row['code'] ?? '');
                if ($code === '') continue;
                $isActive = (int)($row['is_active'] ?? 1);
                if (!$includeInactive && !$isActive) continue;
                $cat = in_array($row['category'] ?? 'domain', ['domain', 'hosting', 'ssl', 'email', 'marketplace'], true) ? $row['category'] : 'domain';
                $providers[$code] = [
                    'id' => $row['id'] ?? null,
                    'code' => $code,
                    'name' => $row['name'] ?? $code,
                    'category' => $cat,
                    'settings_key' => null,
                    'target_url' => $row['target_url'] ?? '',
                    'description' => $row['description'] ?? '',
                    'button_label' => $row['button_label'] ?: 'View Deal',
                    'is_active' => $isActive,
                    'sort_order' => (int)($row['sort_order'] ?? 100),
                    'source' => 'custom',
                    'color' => [
                        'domain' => 'linear-gradient(135deg, #6366f1 0%, #2563eb 100%)',
                        'hosting' => 'linear-gradient(135deg, #14b8a6 0%, #0f766e 100%)',
                        'ssl' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                        'email' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
                        'marketplace' => 'linear-gradient(135deg, #8b5cf6 0%, #be185d 100%)',
                    ][$cat],
                ];
            }
        } catch (Throwable $e) {
            // Table may not exist until the next automatic migration has run.
        }
    }

    $filtered = [];
    foreach ($providers as $code => $provider) {
        if ($category !== null && $provider['category'] !== $category) continue;
        if (!$includeInactive && empty($provider['target_url'])) continue;
        $filtered[$code] = $provider;
    }

    uasort($filtered, function ($a, $b) {
        $sort = ((int)$a['sort_order']) <=> ((int)$b['sort_order']);
        if ($sort !== 0) return $sort;
        return strcasecmp($a['name'], $b['name']);
    });

    return $filtered;
}

function getAffiliateProviderByCode($pdo, $config, $providerCode) {
    $providerCode = sanitizeAffiliateCode($providerCode);
    if ($providerCode === '') return null;
    $providers = getAffiliateProviders($pdo, $config, null, false);
    return $providers[$providerCode] ?? null;
}

function appendUrlParam($targetUrl, $key, $value) {
    $targetUrl = trim((string)$targetUrl);
    if ($targetUrl === '' || $value === '') return $targetUrl;
    return $targetUrl . (strpos($targetUrl, '?') === false ? '?' : '&') . rawurlencode($key) . '=' . rawurlencode($value);
}

function getAffiliateTargetUrl($pdo, $config, $providerCode, $queryDomain = '') {
    $provider = getAffiliateProviderByCode($pdo, $config, $providerCode);
    if (!$provider || empty($provider['target_url'])) return '';
    $targetUrl = $provider['target_url'];
    if ($queryDomain !== '') {
        $targetUrl = appendUrlParam($targetUrl, 'query', $queryDomain);
    }
    return $targetUrl;
}

function parseSelectedProviderCodes($value, $defaultCodes = []) {
    $value = trim((string)$value);
    if ($value === '') return $defaultCodes;
    $parts = preg_split('/[\s,;]+/', $value);
    $codes = [];
    foreach ($parts as $part) {
        $code = sanitizeAffiliateCode($part);
        if ($code !== '' && !in_array($code, $codes, true)) {
            $codes[] = $code;
        }
    }
    return $codes ?: $defaultCodes;
}

function getSelectedAffiliateProviders($pdo, $config, $settingKey, $category, $defaultCodes = [], $limit = 0) {
    $providers = getAffiliateProviders($pdo, $config, $category, false);
    $codes = parseSelectedProviderCodes($config[$settingKey] ?? '', $defaultCodes);
    $selected = [];

    foreach ($codes as $code) {
        if (isset($providers[$code])) {
            $selected[$code] = $providers[$code];
        }
    }

    if (empty($selected)) {
        $selected = $providers;
    }

    if ($limit > 0) {
        $selected = array_slice($selected, 0, $limit, true);
    }

    return $selected;
}

function getPrimaryAffiliateProvider($pdo, $config, $settingKey, $category, $defaultCode) {
    $code = sanitizeAffiliateCode($config[$settingKey] ?? $defaultCode);
    $providers = getAffiliateProviders($pdo, $config, $category, false);
    if (isset($providers[$code])) return $providers[$code];
    return reset($providers) ?: null;
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
 * Read a cached raw WHOIS/RDAP lookup result, including unregistered domains
 */
function getCachedDomainLookup($pdo, $domainName, $ttlSeconds) {
    try {
        $stmt = $pdo->prepare("SELECT result_json, last_checked FROM domain_lookup_cache WHERE domain_name = ?");
        $stmt->execute([$domainName]);
        $cached = $stmt->fetch();
        if (!$cached || empty($cached['result_json'])) {
            return null;
        }

        $lastChecked = strtotime($cached['last_checked'] ?? '');
        if (!$lastChecked || time() - $lastChecked > $ttlSeconds) {
            return null;
        }

        $decoded = json_decode($cached['result_json'], true);
        return is_array($decoded) ? $decoded : null;
    } catch (Throwable $e) {
        error_log("WHOIS cache read failed for $domainName: " . $e->getMessage());
        return null;
    }
}

/**
 * Store a raw WHOIS/RDAP lookup result for cost control
 */
function storeDomainLookupCache($pdo, $domainName, $info) {
    try {
        $registered = isset($info['registered']) && $info['registered'] ? 1 : 0;
        $json = json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            INSERT INTO domain_lookup_cache (domain_name, registered, result_json, last_checked)
            VALUES (?, ?, ?, ?)
            ON CONFLICT(domain_name) DO UPDATE SET
                registered = excluded.registered,
                result_json = excluded.result_json,
                last_checked = excluded.last_checked
        ");
        $stmt->execute([$domainName, $registered, $json, $now]);
    } catch (PDOException $e) {
        try {
            $stmt = $pdo->prepare("SELECT domain_name FROM domain_lookup_cache WHERE domain_name = ?");
            $stmt->execute([$domainName]);
            if ($stmt->fetch()) {
                $upd = $pdo->prepare("UPDATE domain_lookup_cache SET registered = ?, result_json = ?, last_checked = ? WHERE domain_name = ?");
                $upd->execute([$registered, $json, $now, $domainName]);
            } else {
                $ins = $pdo->prepare("INSERT INTO domain_lookup_cache (domain_name, registered, result_json, last_checked) VALUES (?, ?, ?, ?)");
                $ins->execute([$domainName, $registered, $json, $now]);
            }
        } catch (Throwable $fallbackError) {
            error_log("WHOIS cache write failed for $domainName: " . $fallbackError->getMessage());
        }
    }
}

/**
 * Asynchronously checks a domain and updates it in background
 */
function getOrUpdateDomain($pdo, $domainName, $forceRefresh = false) {
    global $config;

    $domainName = cleanDomainName($domainName);
    if (!$domainName) return null;
    
    // Check if domain exists in DB
    $stmt = $pdo->prepare("SELECT * FROM domains WHERE domain_name = ?");
    $stmt->execute([$domainName]);
    $domain = $stmt->fetch();
    
    $now = date('Y-m-d H:i:s');
    
    $cacheTtl = max(3600, (int)($config['whois_cache_ttl_seconds'] ?? 172800));

    // If not found or cached more than configured TTL (or forced)
    $shouldUpdate = $forceRefresh || !$domain;
    if ($domain) {
        $lastChecked = strtotime($domain['last_checked']);
        if (!$lastChecked || time() - $lastChecked > $cacheTtl) {
            $shouldUpdate = true;
        }
    }
    
    if ($shouldUpdate) {
        $info = (!$forceRefresh) ? getCachedDomainLookup($pdo, $domainName, $cacheTtl) : null;
        if (!$info) {
            $info = fetchDomainInfo($domainName);
            if ($info && !empty($info['success'])) {
                storeDomainLookupCache($pdo, $domainName, $info);
            }
        }
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
function encodeEmailHeader($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function formatEmailAddress($email, $name = '') {
    $email = trim((string)$email);
    $name = trim((string)$name);
    if ($name === '') {
        return '<' . $email . '>';
    }
    return encodeEmailHeader($name) . ' <' . $email . '>';
}

function readSmtpResponse($socket) {
    $data = "";
    while (true) {
        $line = fgets($socket, 512);
        if ($line === false) {
            break;
        }
        $data .= $line;
        if (strlen($line) >= 4 && $line[3] !== '-') {
            break;
        }
    }
    return $data;
}

function expectSmtpResponse($socket, $expectedCodes, $context) {
    $response = readSmtpResponse($socket);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, (array)$expectedCodes, true)) {
        error_log("SMTP $context failed: " . trim($response));
        return false;
    }
    return $response;
}

function sendEmailNotification($to, $subject, $messageHtml, $replyToEmail = '', $replyToName = '') {
    global $config;
    
    $to = trim((string)$to);
    $fromEmail = trim((string)($config['smtp_from_email'] ?? 'alerts@tldix.local'));
    $fromName = trim((string)($config['smtp_from_name'] ?? 'TLDix Alerts'));
    $replyToEmail = trim((string)$replyToEmail);
    $replyToName = preg_replace('/[\r\n]+/', ' ', trim((string)$replyToName));

    if (!isValidEmail($to)) {
        error_log("Email skipped: invalid recipient address ($to).");
        return false;
    }

    if (!isValidEmail($fromEmail)) {
        error_log("Email skipped: invalid sender address ($fromEmail).");
        return false;
    }

    if (!isValidEmail($replyToEmail)) {
        $replyToEmail = $fromEmail;
        $replyToName = '';
    }

    $replyToHeader = formatEmailAddress($replyToEmail, $replyToName);
    
    // Headers for HTML mail
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . formatEmailAddress($fromEmail, $fromName) . "\r\n";
    $headers .= "Reply-To: " . $replyToHeader . "\r\n";
    $headers .= "Return-Path: " . $fromEmail . "\r\n";
    $headers .= "Date: " . date(DATE_RFC2822) . "\r\n";
    $headers .= "Message-ID: <" . bin2hex(random_bytes(12)) . "@" . preg_replace('/^.*@/', '', $fromEmail) . ">\r\n";
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
            stream_set_timeout($socket, 20);

            if (!expectSmtpResponse($socket, [220], 'banner')) {
                fclose($socket);
                return false;
            }

            // HELO
            $serverName = $_SERVER['SERVER_NAME'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
            fwrite($socket, "EHLO " . $serverName . "\r\n");
            $ehloResponse = expectSmtpResponse($socket, [250], 'EHLO');
            if (!$ehloResponse) {
                fwrite($socket, "HELO " . $serverName . "\r\n");
                if (!expectSmtpResponse($socket, [250], 'HELO')) {
                    fclose($socket);
                    return false;
                }
            }

            $securePref = strtolower(trim((string)($config['smtp_secure'] ?? '')));
            $shouldStartTls = ($securePref === 'tls' || ($port === 587 && stripos((string)$ehloResponse, 'STARTTLS') !== false));
            if ($shouldStartTls && strpos($host, 'ssl://') !== 0) {
                fwrite($socket, "STARTTLS\r\n");
                if (!expectSmtpResponse($socket, [220], 'STARTTLS')) {
                    fclose($socket);
                    return false;
                }
                $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (!@stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                    error_log("SMTP STARTTLS failed: could not enable TLS encryption.");
                    fclose($socket);
                    return false;
                }
                fwrite($socket, "EHLO " . $serverName . "\r\n");
                if (!expectSmtpResponse($socket, [250], 'EHLO after STARTTLS')) {
                    fclose($socket);
                    return false;
                }
            }

            // AUTH LOGIN if credentials are provided
            if (!empty($username) && !empty($password)) {
                fwrite($socket, "AUTH LOGIN\r\n");
                if (!expectSmtpResponse($socket, [334], 'AUTH LOGIN')) {
                    fclose($socket);
                    return false;
                }

                fwrite($socket, base64_encode($username) . "\r\n");
                if (!expectSmtpResponse($socket, [334], 'AUTH username')) {
                    fclose($socket);
                    return false;
                }

                fwrite($socket, base64_encode($password) . "\r\n");
                if (!expectSmtpResponse($socket, [235], 'AUTH password')) {
                    fclose($socket);
                    return false;
                }
            }

            // MAIL FROM
            fwrite($socket, "MAIL FROM: <$fromEmail>\r\n");
            if (!expectSmtpResponse($socket, [250], 'MAIL FROM')) {
                fclose($socket);
                return false;
            }

            // RCPT TO
            fwrite($socket, "RCPT TO: <$to>\r\n");
            if (!expectSmtpResponse($socket, [250, 251], 'RCPT TO')) {
                fclose($socket);
                return false;
            }

            // DATA
            fwrite($socket, "DATA\r\n");
            if (!expectSmtpResponse($socket, [354], 'DATA')) {
                fclose($socket);
                return false;
            }

            // Email Body
            $body = "Subject: " . encodeEmailHeader($subject) . "\r\n";
            $body .= "To: <$to>\r\n";
            $body .= "From: " . formatEmailAddress($fromEmail, $fromName) . "\r\n";
            $body .= "Reply-To: " . $replyToHeader . "\r\n";
            $body .= "MIME-Version: 1.0\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $body .= $messageHtml . "\r\n.\r\n";

            fwrite($socket, $body);
            if (!expectSmtpResponse($socket, [250], 'message body')) {
                fclose($socket);
                return false;
            }

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
        $encodedSubject = encodeEmailHeader($subject);
        $params = (stripos(PHP_OS, 'WIN') === 0) ? '' : '-f' . escapeshellarg($fromEmail);
        $sent = $params !== ''
            ? mail($to, $encodedSubject, $messageHtml, $headers, $params)
            : mail($to, $encodedSubject, $messageHtml, $headers);
        if (!$sent) {
            error_log("PHP mail() returned false for recipient $to.");
        }
        return $sent;
    } catch (Throwable $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

function sendUserVerificationEmail($email, $username, $token) {
    $verifyUrl = absolute_url('verify-email?token=' . $token);
    $messageHtml = getFormattedEmail('mail_tpl_user_verify', [
        'username' => esc($username),
        'verify_url' => $verifyUrl
    ]);
    return sendEmailNotification($email, 'TLDix E-posta Doğrulama', $messageHtml);
}

function sendAdminRegistrationNotification($email, $username, $mailSent, $token = '') {
    global $config;

    $adminEmail = $config['admin_notification_email'] ?? '';
    if (empty($adminEmail)) {
        return false;
    }

    $subjectPrefix = $mailSent ? 'Yeni Üye Kaydı' : 'Yeni Üye Kaydı - Doğrulama Maili Gönderilemedi';
    $verifyUrl = $token ? absolute_url('verify-email?token=' . $token) : '';
    $messageHtml = getFormattedEmail('mail_tpl_admin_register', [
        'username' => esc($username),
        'email' => esc($email),
        'date' => date('Y-m-d H:i:s'),
        'verify_url' => $verifyUrl,
        'mail_status' => $mailSent ? 'Kullanıcı doğrulama e-postası gönderildi.' : 'Kullanıcı doğrulama e-postası gönderilemedi. SMTP/PHP mail loglarını kontrol edin.'
    ]);

    if (!$mailSent) {
        $messageHtml = getEmailTemplateWrapper(
            '<h2>Yeni Üye Kaydı - Mail Sorunu</h2>' .
            '<p>Kullanıcı hesabı oluşturuldu ancak doğrulama e-postası gönderilemedi.</p>' .
            '<ul><li><strong>Kullanıcı Adı:</strong> ' . esc($username) . '</li>' .
            '<li><strong>E-posta:</strong> ' . esc($email) . '</li>' .
            '<li><strong>Tarih:</strong> ' . date('Y-m-d H:i:s') . '</li>' .
            ($verifyUrl ? '<li><strong>Doğrulama Linki:</strong> <a href="' . esc($verifyUrl) . '">' . esc($verifyUrl) . '</a></li>' : '') .
            '</ul><p>Lütfen SMTP ayarlarını ve alıcı domain teslimatını kontrol edin.</p>'
        );
    }

    return sendEmailNotification($adminEmail, $subjectPrefix . ': ' . $username, $messageHtml);
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
    global $pdo;

    $parts = explode('.', $domainName);
    $tld = 'com';
    if (count($parts) > 1) {
        $tld = implode('.', array_slice($parts, 1));
    }
    $tld = strtolower($tld);
    
    $pricingMatrix = $config['domain_prices'] ?? [];
    $prices = $pricingMatrix[$tld] ?? $pricingMatrix['com'] ?? [];
    
    $providers = getAffiliateProviders($pdo ?? null, $config, 'domain', false);
    
    $comparison = [];
    foreach ($providers as $provider) {
        $name = $provider['name'];
        $price = $prices[$name] ?? 'N/A';
        $affUrl = url('go?to=' . urlencode($provider['code']) . '&utm_source=price_comparison&query=' . urlencode($domainName));
        
        $comparison[] = [
            'provider' => $name,
            'price' => $price,
            'aff_url' => $affUrl,
            'code' => $provider['code'],
            'category' => $provider['category'],
            'description' => $provider['description'],
            'button_label' => $provider['button_label'],
        ];
    }
    
    return $comparison;
}

/**
 * Build a Whop checkout URL with TLDix user metadata attached as query params
 */
function buildWhopCheckoutUrl($baseUrl, $params = []) {
    $baseUrl = trim((string)$baseUrl);
    if ($baseUrl === '') return '';

    $query = http_build_query(array_filter($params, function ($value) {
        return $value !== null && $value !== '';
    }));
    if ($query === '') return $baseUrl;

    return $baseUrl . (strpos($baseUrl, '?') === false ? '?' : '&') . $query;
}

function extractWhopPlanId($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    if (preg_match('/plan_[A-Za-z0-9_\\-]+/', $value, $matches)) {
        return $matches[0];
    }
    return '';
}

/**
 * Read request headers with lowercase keys for webhook verification
 */
function getRequestHeadersLower() {
    $headers = [];
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $key => $value) {
            $headers[strtolower($key)] = $value;
        }
        return $headers;
    }

    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$name] = $value;
        }
    }
    return $headers;
}

function decodeStandardWebhookSecret($secret) {
    $secret = trim((string)$secret);
    if ($secret === '') return '';
    if (strpos($secret, 'whsec_') === 0) {
        $decoded = base64_decode(substr($secret, 6), true);
        return $decoded !== false ? $decoded : substr($secret, 6);
    }
    $decoded = base64_decode($secret, true);
    return $decoded !== false ? $decoded : $secret;
}

function parseStandardWebhookSignatures($signatureHeader) {
    $signatures = [];
    foreach (preg_split('/\s+/', trim((string)$signatureHeader)) as $part) {
        if ($part === '') continue;
        $bits = explode(',', $part, 2);
        $signatures[] = count($bits) === 2 ? $bits[1] : $part;
    }
    return $signatures;
}

/**
 * Verify Standard Webhooks HMAC-SHA256 signatures used by Whop webhooks
 */
function verifyStandardWebhookSignature($payload, $headers, $secret, $toleranceSeconds = 300) {
    $webhookId = trim((string)($headers['webhook-id'] ?? ''));
    $timestamp = trim((string)($headers['webhook-timestamp'] ?? ''));
    $signatureHeader = trim((string)($headers['webhook-signature'] ?? ''));

    if ($webhookId === '' || $timestamp === '' || $signatureHeader === '') {
        return false;
    }

    if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > $toleranceSeconds) {
        return false;
    }

    $secretBytes = decodeStandardWebhookSecret($secret);
    if ($secretBytes === '') {
        return false;
    }

    $signedContent = $webhookId . '.' . $timestamp . '.' . $payload;
    $expected = base64_encode(hash_hmac('sha256', $signedContent, $secretBytes, true));
    foreach (parseStandardWebhookSignatures($signatureHeader) as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}

function findNestedValueByKeys($payload, $keys) {
    if (!is_array($payload)) return null;
    foreach ($payload as $key => $value) {
        if (in_array(strtolower((string)$key), $keys, true) && !is_array($value) && $value !== '') {
            return $value;
        }
        if (is_array($value)) {
            $found = findNestedValueByKeys($value, $keys);
            if ($found !== null && $found !== '') {
                return $found;
            }
        }
    }
    return null;
}

function findNestedEmail($payload) {
    if (!is_array($payload)) return null;
    foreach ($payload as $key => $value) {
        if (!is_array($value) && stripos((string)$key, 'email') !== false && isValidEmail((string)$value)) {
            return strtolower(trim((string)$value));
        }
        if (is_array($value)) {
            $found = findNestedEmail($value);
            if ($found) return $found;
        }
    }
    return null;
}

function detectWhopPlan($payload) {
    global $config;

    $payloadText = strtolower(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    foreach (['gold', 'silver', 'bronze'] as $candidate) {
        $configuredPlanId = strtolower(trim((string)($config['whop_plan_' . $candidate] ?? '')));
        if ($configuredPlanId !== '' && strpos($payloadText, $configuredPlanId) !== false) {
            return $candidate;
        }
    }

    $plan = strtolower((string)(findNestedValueByKeys($payload, ['tldix_plan', 'plan', 'plan_key', 'tier']) ?? ''));
    if (in_array($plan, ['bronze', 'silver', 'gold'], true)) {
        return $plan;
    }

    foreach (['gold', 'silver', 'bronze'] as $candidate) {
        if (strpos($payloadText, $candidate) !== false) {
            return $candidate;
        }
    }

    $amount = findNestedValueByKeys($payload, ['amount', 'total', 'subtotal', 'price', 'value']);
    $amount = is_numeric($amount) ? (float)$amount : 0.0;
    if ($amount >= 9900) return 'gold';
    if ($amount >= 2900) return 'silver';
    if ($amount >= 900) return 'bronze';
    if ($amount >= 99) return 'gold';
    if ($amount >= 29) return 'silver';
    if ($amount >= 9) return 'bronze';

    return 'bronze';
}

/**
 * Verify and process Whop plan webhooks
 */
function processWhopWebhook($pdo, $rawPayload, $headers) {
    global $config;

    $secret = trim((string)($config['whop_webhook_secret'] ?? ''));
    if ($secret === '') {
        $secret = trim((string)getenv('WHOP_WEBHOOK_SECRET'));
    }
    if ($secret === '') {
        error_log('Whop webhook rejected: whop_webhook_secret is not configured.');
        return ['status' => 503, 'body' => ['success' => false, 'error' => 'webhook_secret_missing']];
    }

    if (!verifyStandardWebhookSignature($rawPayload, $headers, $secret)) {
        error_log('Whop webhook rejected: signature verification failed.');
        return ['status' => 401, 'body' => ['success' => false, 'error' => 'invalid_signature']];
    }

    $payload = json_decode($rawPayload, true);
    if (!is_array($payload)) {
        return ['status' => 400, 'body' => ['success' => false, 'error' => 'invalid_json']];
    }

    $eventId = trim((string)($headers['webhook-id'] ?? findNestedValueByKeys($payload, ['id', 'event_id']) ?? bin2hex(random_bytes(8))));
    $eventType = strtolower(trim((string)(findNestedValueByKeys($payload, ['type', 'event_type']) ?? 'unknown')));
    $now = date('Y-m-d H:i:s');

    try {
        $stmt = $pdo->prepare("INSERT INTO webhook_events (provider, event_id, event_type, payload, processed, created_at) VALUES ('whop', ?, ?, ?, 0, ?)");
        $stmt->execute([$eventId, $eventType, $rawPayload, $now]);
    } catch (PDOException $e) {
        return ['status' => 200, 'body' => ['success' => true, 'duplicate' => true]];
    }

    $activateEvents = ['payment.succeeded', 'invoice.paid', 'membership.activated', 'membership.created'];
    $deactivateEvents = ['membership.deactivated', 'membership.cancelled', 'membership.canceled', 'refund.created', 'payment.failed'];
    $shouldActivate = in_array($eventType, $activateEvents, true) || preg_match('/(paid|succeeded|activated|created)$/', $eventType);
    $shouldDeactivate = in_array($eventType, $deactivateEvents, true) || preg_match('/(deactivated|cancelled|canceled|refunded|failed)$/', $eventType);

    if (!$shouldActivate && !$shouldDeactivate) {
        $pdo->prepare("UPDATE webhook_events SET processed = 1 WHERE provider = 'whop' AND event_id = ?")->execute([$eventId]);
        return ['status' => 200, 'body' => ['success' => true, 'ignored' => true, 'event_type' => $eventType]];
    }

    $userId = findNestedValueByKeys($payload, ['tldix_user_id', 'user_id', 'client_reference_id']);
    $user = null;
    if (is_numeric($userId) && (int)$userId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([(int)$userId]);
        $user = $stmt->fetch();
    }

    if (!$user) {
        $email = findNestedEmail($payload);
        if ($email) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
        }
    }

    if (!$user) {
        return ['status' => 202, 'body' => ['success' => false, 'pending' => true, 'error' => 'user_not_found']];
    }

    $plan = detectWhopPlan($payload);
    $orderId = (string)(findNestedValueByKeys($payload, ['order_id', 'payment_id', 'invoice_id', 'membership_id', 'receipt_id']) ?? $eventId);
    $amountRaw = findNestedValueByKeys($payload, ['amount', 'total', 'subtotal', 'price', 'value']);
    $amount = is_numeric($amountRaw) ? (float)$amountRaw : 0.0;
    if ($amount > 1000) $amount = $amount / 100;
    $currency = strtoupper((string)(findNestedValueByKeys($payload, ['currency', 'currency_code']) ?? 'USD'));

    if ($shouldDeactivate) {
        $pdo->prepare("UPDATE users SET api_plan = 'free', pending_plan = NULL WHERE id = ?")->execute([$user['id']]);
        if (function_exists('logActivity')) {
            logActivity($pdo, $user['id'], "Whop aboneliği pasifleştirildi. Plan free olarak güncellendi. Event: $eventType");
        }
        $pdo->prepare("UPDATE webhook_events SET processed = 1 WHERE provider = 'whop' AND event_id = ?")->execute([$eventId]);
        return ['status' => 200, 'body' => ['success' => true, 'user_id' => (int)$user['id'], 'plan' => 'free']];
    }

    $pdo->prepare("UPDATE users SET api_plan = ?, pending_plan = NULL WHERE id = ?")->execute([$plan, $user['id']]);
    $stmt = $pdo->prepare("
        INSERT INTO payments (user_id, plan, amount, currency, method, status, reference, notes, whop_order_id, created_at, confirmed_at)
        VALUES (?, ?, ?, ?, 'whop', 'confirmed', ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        $plan,
        $amount > 0 ? $amount : 0,
        $currency ?: 'USD',
        $orderId,
        'Whop webhook: ' . $eventType,
        $orderId,
        $now,
        $now
    ]);
    if (function_exists('logActivity')) {
        logActivity($pdo, $user['id'], "Whop ödemesi onaylandı. Plan " . strtoupper($plan) . " aktif edildi.");
    }
    $pdo->prepare("UPDATE webhook_events SET processed = 1 WHERE provider = 'whop' AND event_id = ?")->execute([$eventId]);

    return ['status' => 200, 'body' => ['success' => true, 'user_id' => (int)$user['id'], 'plan' => $plan]];
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
function normalizeDatabaseBlogPost($row, $lang = 'en') {
    if (!in_array($lang, ['en', 'tr', 'es', 'de'], true)) {
        $lang = 'en';
    }

    $title = $row['title_' . $lang] ?? '';
    $description = $row['description_' . $lang] ?? '';
    $content = $row['content_' . $lang] ?? '';

    foreach (['en', 'tr', 'es', 'de'] as $fallbackLang) {
        if ($title === '' && !empty($row['title_' . $fallbackLang])) {
            $title = $row['title_' . $fallbackLang];
        }
        if ($description === '' && !empty($row['description_' . $fallbackLang])) {
            $description = $row['description_' . $fallbackLang];
        }
        if ($content === '' && !empty($row['content_' . $fallbackLang])) {
            $content = $row['content_' . $fallbackLang];
        }
    }

    if ($description === '' && $content !== '') {
        $plainContent = strip_tags($content);
        $description = trim(function_exists('mb_substr') ? mb_substr($plainContent, 0, 170, 'UTF-8') : substr($plainContent, 0, 170));
    }

    return [
        'slug' => $row['slug'],
        'title' => $title ?: $row['slug'],
        'description' => $description,
        'category' => $row['category'] ?: 'General',
        'image' => $row['image_url'] ?: 'assets/images/blog/domain_tracking.png',
        'date' => $row['created_at'] ?: date('Y-m-d'),
        'content' => $content,
        'seo_title' => $row['meta_title'] ?: $title,
        'seo_description' => $row['meta_description'] ?: $description,
        'source' => 'database',
    ];
}

function getDatabaseBlogPosts($lang = 'en', $includeDrafts = false) {
    global $pdo;
    if (!$pdo instanceof PDO) return [];

    try {
        $sql = "SELECT * FROM blog_posts";
        if (!$includeDrafts) {
            $sql .= " WHERE COALESCE(status, 'published') = 'published'";
        }
        $sql .= " ORDER BY created_at DESC, id DESC";
        $rows = $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }

    $posts = [];
    foreach ($rows as $row) {
        if (empty($row['slug'])) continue;
        $posts[$row['slug']] = normalizeDatabaseBlogPost($row, $lang);
    }
    return $posts;
}

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

    $dbPosts = getDatabaseBlogPosts($lang);
    if (!empty($dbPosts)) {
        $posts = array_merge($posts, $dbPosts);
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
