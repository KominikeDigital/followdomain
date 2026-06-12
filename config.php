<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

// Global configuration configuration array
$config = [
    // Database Configuration
    'db_type' => 'sqlite', // 'sqlite' or 'mysql'
    'sqlite_path' => getenv('TLDIX_SQLITE_PATH') ?: dirname(__DIR__) . '/tldix_data/database.sqlite',
    
    'mysql_host' => 'localhost',
    'mysql_db' => 'domain_tracker',
    'mysql_user' => 'root',
    'mysql_pass' => '',
    
    // Admin Credentials
    'admin_email' => 'emre@kominikee.com',
    'admin_password' => '$2y$12$wqLYlpj00Gih/EqXiaTdMOfWRijqVTa4UnarbpOp31mJlUQxY46Va',
    
    // Default Ad & SEO Settings
    'ad_status' => 'off', // 'on' or 'off'
    'seo_keywords' => 'domain, tracking, whois, expired domains',
    'seo_author' => 'TLDix',
    'google_search_console' => '',
    'google_analytics_id' => 'G-FRCMXY9TL3',
    'google_tag_manager' => '',
    'google_adsense_id' => 'pub-8595320911699983',
    'bing_verification' => '',
    'cf_analytics_token' => '',
    'custom_head_code' => '',
    
    // Site Identity
    'site_title' => 'TLDix.com',
    'site_description' => 'Domain Expiration Tracker, Alerts & Domain Watchers',
    'site_url' => getenv('TLDIX_SITE_URL') ?: 'https://tldix.com',
    'seo_og_image' => getenv('TLDIX_OG_IMAGE') ?: 'https://tldix.com/assets/images/logo.png',
    'contact_recipient_email' => 'hello@tldix.com',
    'chrome_extension_url' => getenv('TLDIX_CHROME_EXTENSION_URL') ?: 'https://chromewebstore.google.com/detail/tldix-takip-paneli/dpkegfggpakofppgmbeofankjcefmeib',
    'social_links_json' => '',
    'social_twitter_url' => '',
    'social_github_url' => '',
    'social_linkedin_url' => '',
    'social_instagram_url' => '',
    
    // Affiliate Links — Domain Registrars
    'affiliate_namecheap'     => 'https://www.namecheap.com/?aff=your_aff_id',
    'affiliate_namesilo'      => 'https://www.namesilo.com/?rid=your_aff_id',
    'affiliate_porkbun'       => 'https://porkbun.com/?aff=your_aff_id',
    'affiliate_dynadot'       => 'https://www.dynadot.com/?aff=your_aff_id',
    'affiliate_spaceship'     => 'https://www.spaceship.com/?aff=your_aff_id',
    'affiliate_domainnameapi' => 'https://domainnameapi.com/?ref=your_aff_id',
    'affiliate_godaddy'       => 'https://www.godaddy.com/?isc=your_aff_id',
    
    // Affiliate Links — Web Hosting (Yuksek Komisyon)
    'affiliate_hostinger'     => 'https://hostinger.com/?referral=your_aff_id',
    'affiliate_bluehost'      => 'https://www.bluehost.com/track/your_aff_id/',
    'affiliate_siteground'    => 'https://www.siteground.com/index.htm?afcode=your_aff_id',
    'affiliate_kinsta'        => 'https://kinsta.com/?kaid=your_aff_id',
    'affiliate_wpengine'      => 'https://wpengine.com/?aff=your_aff_id',
    'affiliate_interserver'   => 'https://www.interserver.net/?id=your_aff_id',
    
    // Affiliate Links — SSL Certificates
    'affiliate_namecheap_ssl' => 'https://www.namecheap.com/security/ssl-certificates/?aff=your_aff_id',
    'affiliate_ssls'          => 'https://www.ssls.com/?aff=your_aff_id',
    'affiliate_ssldragon'     => 'https://www.ssldragon.com/?aff=your_aff_id',

    // Affiliate Links — Business Email
    'affiliate_google_workspace' => 'https://workspace.google.com/',
    'affiliate_zoho_mail'        => 'https://www.zoho.com/mail/',
    'affiliate_titan_email'      => 'https://titan.email/',
    
    // Affiliate Links — Domain Marketplace
    'affiliate_afternic'      => 'https://www.afternic.com/?ref=your_ref_id',
    'affiliate_sedo'          => 'https://www.sedo.com/?ref=your_ref_id',
    'affiliate_dan'           => 'https://dan.com/?ref=your_ref_id',
    'affiliate_atom'          => 'https://www.atom.com/?ref=your_ref_id',
    'affiliate_dynadot_mkt'   => 'https://www.dynadot.com/domain/market?aff=your_aff_id',
    
    // Default Domain Price Estimates (for comparison widget)
    'domain_prices' => [
        'com' => [
            'Namecheap' => '$9.98',
            'Hostinger' => '$8.99',
            'NameSilo' => '$10.95',
            'Porkbun' => '$9.73',
            'Spaceship' => '$8.85',
            'Dynadot' => '$9.50',
            'Domain Name API' => '$10.15'
        ],
        'net' => [
            'Namecheap' => '$11.98',
            'Hostinger' => '$12.99',
            'NameSilo' => '$11.75',
            'Porkbun' => '$11.45',
            'Spaceship' => '$9.90',
            'Dynadot' => '$10.99',
            'Domain Name API' => '$11.20'
        ],
        'org' => [
            'Namecheap' => '$8.98',
            'Hostinger' => '$9.99',
            'NameSilo' => '$10.85',
            'Porkbun' => '$10.35',
            'Spaceship' => '$8.50',
            'Dynadot' => '$9.99',
            'Domain Name API' => '$10.50'
        ],
        'com.tr' => [
            'Namecheap' => '$6.99',
            'Hostinger' => '$5.99',
            'NameSilo' => 'N/A',
            'Porkbun' => 'N/A',
            'Spaceship' => 'N/A',
            'Dynadot' => 'N/A',
            'Domain Name API' => '$4.50'
        ],
        'tr' => [
            'Namecheap' => '$9.99',
            'Hostinger' => '$8.99',
            'NameSilo' => 'N/A',
            'Porkbun' => 'N/A',
            'Spaceship' => 'N/A',
            'Dynadot' => 'N/A',
            'Domain Name API' => '$7.90'
        ]
    ],
    
    // Ad Placements (HTML/JavaScript codes like Google AdSense)
    'ad_header' => '<div style="width: 100%; min-height: 90px; background: rgba(255, 255, 255, 0.03); border: 1px dashed rgba(255, 255, 255, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: rgba(255, 255, 255, 0.4); font-size: 0.85rem; margin-bottom: 2rem;">[Google Ad - Header Banner 728x90]</div>',
    
    'ad_sidebar' => '<div style="width: 100%; min-height: 250px; background: rgba(255, 255, 255, 0.03); border: 1px dashed rgba(255, 255, 255, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: rgba(255, 255, 255, 0.4); font-size: 0.85rem; margin: 1.5rem 0;">[Google Ad - Sidebar Banner 300x250]</div>',
    
    'ad_footer' => '<div style="width: 100%; min-height: 90px; background: rgba(255, 255, 255, 0.03); border: 1px dashed rgba(255, 255, 255, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: rgba(255, 255, 255, 0.4); font-size: 0.85rem; margin-top: 2rem;">[Google Ad - Footer Banner 728x90]</div>',
    
    // Email Notification Configuration (For Expiration Alerts)
    'email_use_smtp' => 0, // 0 = Standard PHP mail(), 1 = SMTP
    'smtp_host' => 'smtp.mailtrap.io',
    'smtp_port' => 2525,
    'smtp_user' => '',
    'smtp_pass' => '',
    'smtp_from_email' => 'alerts@tldix.local',
    'smtp_from_name' => 'TLDix Alerts',
    'whois_cache_ttl_seconds' => 172800,
    'whop_webhook_secret' => getenv('WHOP_WEBHOOK_SECRET') ?: '',
    'whop_link_bronze' => '',
    'whop_link_silver' => '',
    'whop_link_gold' => '',
    'whop_plan_bronze' => '',
    'whop_plan_silver' => '',
    'whop_plan_gold' => '',

    // Affiliate display controls
    'recommended_hosting_codes' => 'hostinger,bluehost,siteground',
    'recommended_ssl_codes' => 'namecheap_ssl,ssls,ssldragon',
    'domain_search_primary_provider' => 'namecheap',
];

return $config;
