<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

global $config, $pdo;

$isUser = isLoggedIn();
$username = $isUser ? $_SESSION['username'] : '';
$initials = '';
$userPlan = 'free';
if ($isUser) {
    $initials = strtoupper(substr($username, 0, 2));
    try {
        $stmtPlan = $pdo->prepare("SELECT api_plan FROM users WHERE id = ?");
        $stmtPlan->execute([$_SESSION['user_id']]);
        $userPlan = $stmtPlan->fetchColumn() ?: 'free';
    } catch (Exception $e) {
        $userPlan = 'free';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? esc($pageTitle) : __('site_title') . ' — ' . __('nav_track'); ?></title>
    <meta name="description" content="<?php echo isset($pageDesc) ? esc($pageDesc) : __('hero_subtitle'); ?>">
    <?php if (!empty($config['seo_keywords'])): ?>
        <meta name="keywords" content="<?php echo esc($config['seo_keywords']); ?>">
    <?php endif; ?>
    <?php if (!empty($config['seo_author'])): ?>
        <meta name="author" content="<?php echo esc($config['seo_author']); ?>">
    <?php endif; ?>
    
    <!-- Theme resolution script to prevent FOIT -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'system';
            let activeTheme = savedTheme;
            if (savedTheme === 'system') {
                activeTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-theme', activeTheme);
            document.documentElement.setAttribute('data-selected-theme', savedTheme);
        })();
    </script>
    
    <!-- Open Graph Tags -->
    <meta property="og:title" content="<?php echo isset($pageTitle) ? esc($pageTitle) : 'TLDix'; ?>">
    <meta property="og:description" content="<?php echo isset($pageDesc) ? esc($pageDesc) : 'Alan adlarının süresini takip edin.'; ?>">
    <meta property="og:type" content="website">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Style Sheet -->
    <link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <?php if (($route ?? '') === 'checkout'): ?>
        <script async defer src="https://js.whop.com/static/checkout/loader.js"></script>
    <?php endif; ?>
    
    <!-- Base Path for JavaScript -->
    <script>
        window.BASE_PATH = <?php echo json_encode(BASE_PATH); ?>;
    </script>

    <!-- Google Search Console Verification -->
    <?php if (!empty($config['google_search_console'])): ?>
        <?php echo $config['google_search_console']; ?>
    <?php endif; ?>

    <!-- Bing Webmaster Verification -->
    <?php if (!empty($config['bing_verification'])): ?>
        <meta name="msvalidate.01" content="<?php echo esc($config['bing_verification']); ?>">
    <?php endif; ?>

    <!-- Google Analytics 4 -->
    <?php if (!empty($config['google_analytics_id'])): ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc($config['google_analytics_id']); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '<?php echo esc($config['google_analytics_id']); ?>');
        </script>
    <?php endif; ?>

    <!-- Google Tag Manager -->
    <?php if (!empty($config['google_tag_manager'])): ?>
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo esc($config['google_tag_manager']); ?>');</script>
    <?php endif; ?>

    <!-- Cloudflare Web Analytics -->
    <?php if (!empty($config['cf_analytics_token'])): ?>
        <script defer src='https://static.cloudflareinsights.com/beacon.min.js' data-cf-beacon='{"token": "<?php echo esc($config['cf_analytics_token']); ?>"}'></script>
    <?php endif; ?>

    <!-- Custom Head Code -->
    <?php if (!empty($config['custom_head_code'])): ?>
        <?php echo $config['custom_head_code']; ?>
    <?php endif; ?>

    <!-- OG Image -->
    <?php if (!empty($config['seo_og_image'])): ?>
        <meta property="og:image" content="<?php echo esc($config['seo_og_image']); ?>">
    <?php endif; ?>
</head>
<body>
    <!-- Radial Background Gradient for glow effect -->
    <div class="bg-glow"></div>

    <header class="app-header">
        <div class="container header-container">
            <a href="<?php echo url(''); ?>" class="app-logo logo-mark logo-mark-header" aria-label="TLDix">
                <img src="<?php echo url('assets/images/logo.png'); ?>" alt="TLDix Logo" class="logo-img logo-img-light">
                <img src="<?php echo url('assets/images/dark-logo.png'); ?>" alt="" class="logo-img logo-img-dark" aria-hidden="true">
            </a>
            
            <nav class="app-nav">
                <ul>
                    <li><a href="<?php echo url(''); ?>" class="<?php echo ($route === 'home') ? 'active' : ''; ?>"><?php echo __('nav_track'); ?></a></li>
                    <li><a href="<?php echo url('trending'); ?>" class="<?php echo ($route === 'trending') ? 'active' : ''; ?>"><?php echo __('nav_trending'); ?></a></li>
                    <li><a href="<?php echo url('domains-for-sale'); ?>" class="<?php echo ($route === 'domains_for_sale') ? 'active' : ''; ?>"><?php echo __('nav_domains_for_sale'); ?></a></li>
                    <li><a href="<?php echo url('social-search'); ?>" class="<?php echo ($route === 'social_search') ? 'active' : ''; ?>"><?php echo __('nav_social_search'); ?></a></li>
                    <li><a href="<?php echo url('docs'); ?>" class="<?php echo ($route === 'docs') ? 'active' : ''; ?>"><?php echo __('nav_api'); ?></a></li>
                </ul>
            </nav>
            
            <div class="header-actions">
                <!-- Language Selection Dropdown -->
                <div class="lang-dropdown">
                    <button class="lang-btn" id="langDropdownBtn" aria-label="Dil Seçimi / Language Selection">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: block;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="2" y1="12" x2="22" y2="12"></line>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                        </svg>
                        <span class="lang-current-code"><?php echo strtoupper($lang); ?></span>
                    </button>
                    <div class="lang-dropdown-menu" id="langDropdownMenu">
                        <a href="?lang=en" class="lang-item <?php echo ($lang === 'en') ? 'active' : ''; ?>">
                            <span class="flag-icon">🇬🇧</span> English
                        </a>
                        <a href="?lang=tr" class="lang-item <?php echo ($lang === 'tr') ? 'active' : ''; ?>">
                            <span class="flag-icon">🇹🇷</span> Türkçe
                        </a>
                        <a href="?lang=es" class="lang-item <?php echo ($lang === 'es') ? 'active' : ''; ?>">
                            <span class="flag-icon">🇪🇸</span> Español
                        </a>
                        <a href="?lang=de" class="lang-item <?php echo ($lang === 'de') ? 'active' : ''; ?>">
                            <span class="flag-icon">🇩🇪</span> Deutsch
                        </a>
                </div>
            </div>
            
            <!-- Theme Selection Dropdown -->
                <div class="theme-dropdown" style="margin-right: 0.5rem;">
                    <button class="theme-btn" id="themeDropdownBtn" aria-label="Tema Seçimi / Theme Selection">
                        <span class="theme-current-text">Theme</span>
                    </button>
                    <div class="theme-dropdown-menu" id="themeDropdownMenu">
                        <button type="button" class="theme-item" data-theme-val="light">
                            <span class="theme-text"><?php echo __('theme_light', 'Light'); ?></span>
                        </button>
                        <button type="button" class="theme-item" data-theme-val="dark">
                            <span class="theme-text"><?php echo __('theme_dark', 'Dark'); ?></span>
                        </button>
                        <button type="button" class="theme-item" data-theme-val="system">
                            <span class="theme-text"><?php echo __('theme_system', 'System'); ?></span>
                        </button>
                    </div>
                </div>

                <?php if ($isUser): ?>
                    <span class="user-plan-badge badge-<?php echo esc($userPlan); ?>" style="font-size: 0.75rem; padding: 4px 10px; border-radius: 20px; font-weight: 700; text-transform: uppercase; margin-right: 0.5rem; border: 1px solid rgba(255,255,255,0.15); background: rgba(99, 102, 241, 0.1); color: var(--color-primary);"><?php echo esc($userPlan); ?></span>
                    
                    <!-- UserInitials Bubble with dropdown actions -->
                    <div class="user-bubble-dropdown">
                        <button class="user-initials-bubble" id="userBubbleBtn" aria-label="Kullanıcı Menüsü">
                            <?php echo $initials; ?>
                        </button>
                        <div class="dropdown-menu-list" id="userDropdownMenu">
                            <span class="dropdown-username">@<?php echo esc($username); ?></span>
                            <hr class="dropdown-divider">
                            <a href="<?php echo url('panel'); ?>" class="dropdown-item"><?php echo __('control_panel'); ?></a>
                            <a href="<?php echo url('panel/domains'); ?>" class="dropdown-item"><?php echo __('my_domains'); ?></a>
                            <a href="<?php echo url('panel/hosting'); ?>" class="dropdown-item"><?php echo __('hosting_tracking'); ?></a>
                             <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                                 <a href="<?php echo url('manage-secure-panel'); ?>" class="dropdown-item"><?php echo __('admin_panel'); ?></a>
                             <?php endif; ?>
                            <hr class="dropdown-divider">
                            <a href="<?php echo url('logout'); ?>" class="dropdown-item item-logout"><?php echo __('nav_logout'); ?></a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?php echo url('login'); ?>" class="btn btn-secondary btn-nav"><?php echo __('nav_login'); ?></a>
                    <a href="<?php echo url('register'); ?>" class="btn btn-primary btn-nav btn-nav-accent js-register-rotator" data-alt-text="Free"><?php echo __('nav_register'); ?></a>
                <?php endif; ?>
                
                <button class="menu-toggle" aria-label="Menüyü Aç" aria-expanded="false" popovertarget="mobile-nav">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="4" y1="12" x2="20" y2="12"></line>
                        <line x1="4" y1="6" x2="20" y2="6"></line>
                        <line x1="4" y1="18" x2="20" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Mobile Nav Popover (Modern Web Popover API) -->
    <div id="mobile-nav" popover="auto" class="mobile-nav-panel">
        <div class="mobile-nav-header">
            <a href="<?php echo url(''); ?>" class="app-logo logo-mark logo-mark-mobile" aria-label="TLDix" onclick="document.getElementById('mobile-nav').hidePopover();">
                <img src="<?php echo url('assets/images/logo.png'); ?>" alt="TLDix Logo" class="logo-img logo-img-light">
                <img src="<?php echo url('assets/images/dark-logo.png'); ?>" alt="" class="logo-img logo-img-dark" aria-hidden="true">
            </a>
            <button class="menu-close" popovertargetaction="hide" popovertarget="mobile-nav" aria-label="Kapat">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <nav class="mobile-menu">
            <ul>
                <?php if ($isUser): ?>
                    <li><a href="<?php echo url('panel'); ?>" onclick="document.getElementById('mobile-nav').hidePopover();"><?php echo __('nav_dashboard'); ?></a></li>
                    <li><a href="<?php echo url('panel/domains'); ?>" onclick="document.getElementById('mobile-nav').hidePopover();"><?php echo __('nav_domains'); ?></a></li>
                    <li><a href="<?php echo url('panel/hosting'); ?>" onclick="document.getElementById('mobile-nav').hidePopover();"><?php echo __('nav_hosting'); ?></a></li>
                    <li><a href="<?php echo url('expiring'); ?>" onclick="document.getElementById('mobile-nav').hidePopover();"><?php echo __('nav_expiring'); ?></a></li>
                    <li><a href="<?php echo url('domains-for-sale'); ?>" onclick="document.getElementById('mobile-nav').hidePopover();"><?php echo __('nav_domains_for_sale'); ?></a></li>
                    <li><a href="<?php echo url('panel/integrations'); ?>" onclick="document.getElementById('mobile-nav').hidePopover();"><?php echo __('nav_integrations'); ?></a></li>
                    <li><a href="<?php echo url('panel/integrations#pricing'); ?>" onclick="document.getElementById('mobile-nav').hidePopover();" style="color: var(--color-primary); font-weight: bold; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;">👑 Premium</a></li>
                    <li><a href="<?php echo url('logout'); ?>" onclick="document.getElementById('mobile-nav').hidePopover();" style="color: #ef4444;"><?php echo __('nav_logout'); ?></a></li>
                <?php else: ?>
                    <li><a href="<?php echo url(''); ?>" onclick="document.getElementById('mobile-nav').hidePopover();"><?php echo __('nav_track'); ?></a></li>
                    <li><a href="<?php echo url('trending'); ?>" onclick="document.getElementById('mobile-nav').hidePopover();"><?php echo __('nav_trending'); ?></a></li>
                    <li><a href="<?php echo url('domains-for-sale'); ?>" onclick="document.getElementById('mobile-nav').hidePopover();"><?php echo __('nav_domains_for_sale'); ?></a></li>
                    <li><a href="<?php echo url('docs'); ?>" onclick="document.getElementById('mobile-nav').hidePopover();"><?php echo __('nav_api'); ?></a></li>
                    <li><a href="<?php echo url('login'); ?>" class="btn btn-secondary" style="margin-top: 1rem; width: 100%; text-align: center;" onclick="document.getElementById('mobile-nav').hidePopover();"><?php echo __('nav_login'); ?></a></li>
                    <li><a href="<?php echo url('register'); ?>" class="btn btn-primary" style="margin-top: 0.5rem; width: 100%; text-align: center;" onclick="document.getElementById('mobile-nav').hidePopover();"><?php echo __('nav_register'); ?></a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <!-- Main Container -->
    <main class="app-main-content">
        <div class="container">
             <!-- Header Ad Slot -->
             <?php if (($config['ad_status'] ?? 'off') === 'on' && !empty($config['ad_header']) && $route !== 'panel' && $route !== 'panel_domains' && $route !== 'panel_hosting' && $route !== 'panel_integrations'): ?>
                 <div class="ad-container ad-header-slot">
                     <?php echo $config['ad_header']; ?>
                 </div>
             <?php endif; ?>
