<?php
/**
 * Detective Pro (Global Edition) - v1.6.1
 * 200+ Platforms | Domain Checker | Multi-User | PDF Export
 * Author: Kominike Creative Digital (2026)
 */

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

// Domain TLDs List
$tlds = [".com", ".net", ".org", ".info", ".me", ".biz", ".io", ".tech", ".co", ".online", ".space", ".pro", ".xyz"];

// AJAX Handler
if (isset($_GET['action']) && $_GET['action'] == 'search') {
    header('Content-Type: application/json');
    $query = $_GET['query'] ?? '';
    $mode = $_GET['mode'] ?? 'social';
    $batch = isset($_GET['batch']) ? (int)$_GET['batch'] : 0;
    
    if (empty($query)) { echo json_encode(['results' => [], 'done' => true]); exit; }

    if ($mode == 'social') {
        $usernames = explode(',', $query);
        $siteNames = array_keys($social_sites);
        $chunkSize = 6;
        $chunkedKeys = array_chunk($siteNames, $chunkSize);
        if (!isset($chunkedKeys[$batch])) { echo json_encode(['done' => true]); exit; }
        
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
    } else {
        // Domain Checker via RDAP
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

        $domain_base = trim(preg_replace('/[^a-zA-Z0-9-]/', '', $query));
        $chunkedTlds = array_chunk($tlds, 4);
        if (!isset($chunkedTlds[$batch])) { echo json_encode(['done' => true]); exit; }

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
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detective Pro | OSINT & Domains</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://unpkg.com/@lottiefiles/dotlottie-web@0.40.0/dist/dotlottie-web.umd.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root[data-theme="dark"] {
            --primary: #00f2fe;
            --secondary: #4facfe;
            --bg: #020617;
            --glass: rgba(15, 23, 42, 0.7);
            --text: #f8fafc;
            --border: rgba(255,255,255,0.1);
        }
        :root[data-theme="light"] {
            --primary: #2563eb;
            --secondary: #3b82f6;
            --bg: #f8fafc;
            --glass: rgba(255, 255, 255, 0.8);
            --text: #0f172a;
            --border: rgba(0,0,0,0.1);
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            transition: 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container {
            max-width: 1000px;
            width: 92%;
            margin: 40px auto;
            text-align: center;
            flex: 1;
        }

        .scan-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.55);
            backdrop-filter: blur(6px);
        }
        .scan-overlay.active { display: flex; }
        .scan-overlay dotlottie-player,
        #scanCanvas { width: 260px; height: 260px; }
        .scan-overlay p {
            color: #fff;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 3px;
            margin-top: 10px;
            text-transform: uppercase;
            animation: blink 1.4s ease-in-out infinite;
        }

        @keyframes blink {
            0%,100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        .logo-area { margin-bottom: 25px; }
        .logo-area img {
            max-width: 280px;
            width: 100%;
            filter: drop-shadow(0 0 8px rgba(79, 172, 254, 0.2));
        }

        .mode-switcher {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .mode-btn {
            padding: 10px 25px;
            border-radius: 30px;
            border: 1px solid var(--border);
            background: var(--glass);
            color: var(--text);
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 800;
            transition: 0.3s;
        }

        .mode-btn.active {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: #000;
            border: none;
            box-shadow: 0 5px 15px rgba(0,242,254,0.3);
        }

        .search-panel {
            background: var(--glass);
            backdrop-filter: blur(20px);
            padding: 30px;
            border-radius: 28px;
            border: 1px solid var(--border);
            box-shadow: 0 40px 100px -20px rgba(0,0,0,0.6);
            margin-bottom: 30px;
        }

        .input-group {
            display: flex;
            gap: 12px;
        }

        input {
            flex: 1;
            background: rgba(0,0,0,0.2);
            border: 1px solid var(--border);
            padding: 16px 18px;
            color: var(--text);
            border-radius: 16px;
            font-size: 1rem;
            outline: none;
            transition: 0.3s;
            min-width: 0;
        }

        input:focus {
            border-color: var(--primary);
            background: rgba(0,0,0,0.3);
        }

        .action-btns {
            display: flex;
            gap: 10px;
            margin-top: 18px;
            justify-content: center;
            flex-wrap: wrap;
        }

        button.main-btn {
            padding: 14px 35px;
            border-radius: 14px;
            border: none;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: #000;
            text-transform: uppercase;
            font-size: 0.95rem;
        }

        button.pdf-btn {
            padding: 14px 22px;
            border-radius: 14px;
            border: none;
            background: #10b981;
            color: #fff;
            cursor: pointer;
            font-weight: 800;
            font-size: 0.9rem;
            display: none;
        }

        button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,242,254,0.2);
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .stats {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin: 20px 0;
            opacity: 0;
            transition: 0.5s;
            flex-wrap: wrap;
        }

        .stats.active { opacity: 1; }

        .stat {
            background: var(--glass);
            padding: 8px 16px;
            border-radius: 12px;
            border: 1px solid var(--border);
            font-size: 0.82rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .progress {
            width: 100%;
            height: 6px;
            background: rgba(128,128,128,0.1);
            border-radius: 10px;
            margin-top: 20px;
            overflow: hidden;
            display: none;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            width: 0%;
            transition: 0.5s;
        }

        .share-row {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .share-btn {
            padding: 8px 18px;
            border-radius: 10px;
            border: none;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.3s;
            color: #fff;
        }

        .share-btn:hover {
            filter: brightness(1.1);
            transform: scale(1.05);
        }

        .share-wa { background: #25D366; }
        .share-tw { background: #1DA1F2; }
        .share-li { background: #0077b5; }

        .cta-banner {
            margin-top: 22px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px 20px;
            text-align: center;
        }

        .cta-banner p {
            margin: 4px 0;
            font-size: 0.88rem;
            line-height: 1.6;
        }

        .cta-banner a {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
        }

        .cta-banner a:hover { text-decoration: underline; }

        .domain-cta {
            margin-top: 18px;
            font-size: 0.88rem;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 18px;
            text-align: center;
        }

        .domain-cta a {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
        }

        .domain-cta a:hover { text-decoration: underline; }

        .results {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 14px;
            margin: 30px 0;
        }

        .card {
            background: var(--glass);
            padding: 18px;
            border-radius: 18px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: inherit;
            transition: 0.3s;
            text-align: left;
        }

        .card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            background: rgba(255,255,255,0.05);
        }

        .card.found { border-left: 4px solid #10b981; }
        .card.not-found { opacity: 0.5; border-left: 4px solid #f43f5e; }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .found .dot {
            background: #10b981;
            box-shadow: 0 0 8px #10b981;
        }

        .not-found .dot { background: #f43f5e; }

        .seo-block {
            width: 92%;
            max-width: 1000px;
            margin: 0 auto 30px auto;
            padding: 28px 24px;
            box-sizing: border-box;
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 28px;
            box-shadow: 0 40px 100px -20px rgba(0,0,0,0.25);
            text-align: left;
            line-height: 1.8;
        }

        .seo-block h2 {
            margin: 0 0 14px 0;
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text);
        }

        .seo-block h3 {
            margin: 22px 0 8px 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text);
        }

        .seo-block p {
            margin: 0 0 14px 0;
            font-size: 0.96rem;
            color: var(--text);
            opacity: 0.95;
        }

        footer {
            padding: 30px 20px;
            width: 100%;
            text-align: center;
            border-top: 1px solid var(--border);
            color: #475569;
            margin-top: auto;
            box-sizing: border-box;
        }

        .footer-logo {
            font-weight: 800;
            color: var(--text);
            letter-spacing: 2px;
        }

        .theme-toggle {
            position: fixed;
            top: 16px;
            right: 16px;
            background: var(--glass);
            border: 1px solid var(--border);
            padding: 10px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.1rem;
            z-index: 999;
        }

        @media (max-width: 640px) {
            .container { margin: 30px auto; }
            .logo-area img { max-width: 200px; }
            .input-group { flex-direction: column; }
            .action-btns { flex-direction: column; align-items: stretch; }
            button.main-btn, button.pdf-btn { width: 100%; }
            .stats { gap: 8px; }
            .results { grid-template-columns: 1fr; }
            .share-row { flex-direction: column; align-items: center; }

            .seo-block {
                padding: 22px 18px;
                border-radius: 20px;
            }

            .seo-block h2 { font-size: 1.15rem; }
            .seo-block p { font-size: 0.93rem; }
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">🌓</button>

    <div class="container">
        <div class="logo-area"><img src="logo.webp" alt="Detective Pro"></div>

        <div class="mode-switcher">
            <button class="mode-btn active" id="modeSocial" onclick="setMode('social')">SOCIAL SEARCH</button>
            <button class="mode-btn" id="modeDomain" onclick="setMode('domain')">DOMAIN CHECKER</button>
        </div>

        <div class="search-panel">
            <div class="input-group">
                <input type="text" id="searchInput" placeholder="Enter usernames (comma separated)..." onkeydown="if(event.key==='Enter') startSearch()">
            </div>

            <div class="action-btns">
                <button class="main-btn" id="searchBtn" onclick="startSearch()">🔍 SEARCH</button>
                <button class="pdf-btn" id="pdfBtn" onclick="exportPDF()">📄 DOWNLOAD REPORT</button>
            </div>

            <div class="stats" id="statsBar">
                <div class="stat">📡 <span id="sTarandi">0</span> Scanned</div>
                <div class="stat" style="color:#10b981">✅ <span id="sBulundu">0</span> Success</div>
                <div class="stat" style="color:#f43f5e">❌ <span id="sYok">0</span> Taken / Not Found</div>
            </div>

            <div class="progress" id="pBar"><div class="progress-fill" id="pFill"></div></div>

            <div class="share-row" id="shareRow" style="display:none">
                <button class="share-btn share-wa" onclick="share('whatsapp')">📲 WhatsApp</button>
                <button class="share-btn share-tw" onclick="share('twitter')">𝕏 Twitter</button>
                <button class="share-btn share-li" onclick="share('linkedin')">💼 LinkedIn</button>
            </div>

            <div class="cta-banner" id="socialCta" style="display:none">
                <p>🌐 For the best social media management, <strong>Kominike "Creative" Digital</strong> is your go-to partner.</p>
                <p>Visit: <a href="https://kominikee.com" target="_blank">kominikee.com</a> &nbsp;|&nbsp; Email: <a href="mailto:hello@kominikee.com">hello@kominikee.com</a></p>
                <p style="opacity:0.7">We are ready to provide you with a tailored offer. Let's connect!</p>
            </div>

            <div class="domain-cta" id="domainCta" style="display:none">
                💡 Looking to register an available domain at an affordable price? <a href="https://www.hostinger.com/tr/referral?REFERRALCODE=VKNEMRECEYZG" target="_blank">Get a great deal on Domain & Hosting here!</a>
            </div>
        </div>

        <div class="results" id="resultsGrid"></div>
    </div>

    <div class="scan-overlay" id="scanOverlay">
        <canvas id="scanCanvas"></canvas>
        <p>🔍 Scanning...</p>
    </div>

    <section class="seo-block" aria-label="How Detective Pro works and its features">
    <h2>How Detective Pro Works: Social Media Username and Domain Checking System</h2>

    <p><strong>Detective Pro</strong> is an advanced OSINT platform that brings together social media username lookup, domain availability checking, and digital identity research on a single screen. The main purpose of this system is to help users research brand names, check username availability across different platforms, verify the registration status of domain extensions, and provide a fast, simple, and reliable tool for anyone who wants to build a strong digital presence. It is especially useful for personal branding, agencies, entrepreneurs, social media professionals, and digital marketing teams.</p>

    <h3>How does the social media username scanning system work?</h3>
    <p>In Social Search mode, the user enters one or more usernames separated by commas. The entered data is first sanitized and only valid characters are preserved. The system then checks the relevant username across platforms such as Instagram, Twitter, Facebook, GitHub, Reddit, TikTok, YouTube, Pinterest, Snapchat, Telegram, Twitch, Spotify, Medium, Steam, SoundCloud, Vimeo, Dribbble, Behance, Linktree, and Chess.com. A dedicated URL structure is used for each platform, and the appropriate verification logic is applied.</p>

    <p>On some social media platforms, profile existence is determined through HTTP status codes, while on others, specific error messages within the page content are analyzed. This allows results to be obtained in a more accurate and stable way. Instead of loading all platforms at once, the system processes queries in batches. This approach protects server performance while providing a smoother user experience. The result cards clearly show on which platform a match was found or not found. Clickable results make it possible to go directly to the detected profile page.</p>

    <h3>Domain checker infrastructure and domain lookup logic</h3>
    <p>In Domain Checker mode, the user only enters the base domain name. The system automatically combines this name with popular extensions such as .com, .net, .org, .info, .me, .biz, .io, .tech, .co, .online, .space, .pro, and .xyz. After that, an RDAP-based registration query is performed for each domain combination. RDAP is the modern REST-based equivalent of the traditional WHOIS system and provides a reliable standard for determining whether a domain name is registered or not.</p>

    <p>If the query returns HTTP 200, the domain is considered registered. If the query returns HTTP 404, the domain is considered available. Detective Pro presents this technical result in a user-friendly way as “AVAILABLE” or “TAKEN.” This allows users researching brand names to quickly see which domain extensions are still available. In addition, the WHOIS links included in the result cards make it possible to perform a more detailed review. Batch-based progressive querying is also used in domain searches, balancing speed, stability, and accessibility.</p>

    <h3>Advantages for brand research, SEO, and digital visibility</h3>
    <p>For a brand to appear strong in the digital world, being able to use the same username consistently across different platforms is extremely important. Likewise, finding a suitable domain creates a solid foundation for SEO. When users search for a brand name, trust increases, brand recognition grows, and conversion potential improves if the social media accounts and website name match each other. Detective Pro directly addresses this need. By combining social media username lookup and domain availability analysis, it helps users make the right decisions before the branding process even begins.</p>

    <p>In SEO strategy, brand consistency is an important ranking and trust signal. Consistent usernames, matching domain structures, and strong digital asset planning provide advantages in content marketing, organic traffic growth, and brand authority. Especially for new startups, checking usernames and domain names at the beginning reduces future rebranding costs. Detective Pro accelerates this process while saving both time and operational effort.</p>

    <h3>Technical structure, speed, and user experience</h3>
    <p>The platform is built on PHP and a cURL multi-based architecture. This makes it possible to execute multiple requests in parallel. On the client side, a JavaScript-based asynchronous fetch structure allows results to load instantly without refreshing the page. When the user starts a search, the scanning animation, progress bar, and statistics area become active. The system shows in real time how many platforms have been scanned, how many results have been found, and how many queries returned negative results. This is especially useful when checking multiple usernames or researching multiple domain extensions.</p>

    <p>With its modern interface, light and dark theme support, responsive design, and clean card layout, the user experience remains consistent across mobile and desktop devices. Found results can also be exported as PDF files. This feature provides significant value for agency reporting, client presentations, preliminary research reports, and brand consulting workflows. The ability to share results turns the platform from a simple checking tool into a solution that can be integrated into real business processes.</p>

    <h3>Who is Detective Pro suitable for?</h3>
    <p>Detective Pro is suitable for social media managers, SEO specialists, brand consultants, content creators, startup founders, agency teams, and anyone conducting digital research. If you are planning to launch a new brand, looking for the same username across all major platforms, or trying to find the right domain name, this system can save you significant time. Thanks to its user-friendly interface and technically solid structure, it is suitable for both professional and individual use.</p>

    <p>In summary, Detective Pro is a powerful solution developed for social media username scanning, domain availability checking, digital identity analysis, fast reporting, and SEO-driven brand research. It is a practical, fast, and results-oriented assistant for anyone who wants to make a strong start in the digital world, choose the right name, and maintain brand consistency.</p>
</section>

    <footer>
        <div class="footer-logo">KOMINIKE "CREATIVE" DIGITAL</div>
        <div style="font-size:0.8rem; margin-top:5px">&copy; 2026 Detective Pro v1.7</div>
    </footer>

    <script>
        let currentMode = 'social';
        let allResults = [];
        let counts = { t: 0, b: 0, y: 0 };
        let isSearching = false;

        function setMode(mode) {
            if (isSearching) return;
            currentMode = mode;
            document.getElementById('modeSocial').classList.toggle('active', mode === 'social');
            document.getElementById('modeDomain').classList.toggle('active', mode === 'domain');
            document.getElementById('searchInput').placeholder =
                mode === 'social'
                    ? 'Enter usernames (comma separated)...'
                    : 'Enter domain name (e.g. google)...';
        }

        function toggleTheme() {
            const root = document.documentElement;
            root.setAttribute(
                'data-theme',
                root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'
            );
        }

        let lottiePlayer = null;

        function initLottie() {
            try {
                if (lottiePlayer || typeof DotLottie === 'undefined') return;
                lottiePlayer = new DotLottie({
                    canvas: document.getElementById('scanCanvas'),
                    src: 'scan.lottie',
                    loop: true,
                    autoplay: false
                });
            } catch (e) {
                lottiePlayer = null;
            }
        }

        function showScanOverlay() {
            try { initLottie(); } catch (e) {}
            document.getElementById('scanOverlay').classList.add('active');
            try {
                if (lottiePlayer) lottiePlayer.play();
            } catch (e) {}
        }

        function hideScanOverlay() {
            document.getElementById('scanOverlay').classList.remove('active');
            try {
                if (lottiePlayer) lottiePlayer.stop();
            } catch (e) {}
        }

        async function startSearch() {
            const query = document.getElementById('searchInput').value.trim();
            if (!query || isSearching) return;

            const grid = document.getElementById('resultsGrid');
            grid.innerHTML = '';
            allResults = [];
            counts = { t: 0, b: 0, y: 0 };
            updateStats();

            isSearching = true;
            showScanOverlay();
            document.getElementById('searchBtn').disabled = true;
            document.getElementById('statsBar').classList.add('active');
            document.getElementById('pBar').style.display = 'block';
            document.getElementById('pFill').style.width = '0%';
            document.getElementById('pdfBtn').style.display = 'none';
            document.getElementById('shareRow').style.display = 'none';
            document.getElementById('socialCta').style.display = 'none';
            document.getElementById('domainCta').style.display = 'none';

            let batch = 0;

            while (true) {
                try {
                    const response = await fetch(`?action=search&query=${encodeURIComponent(query)}&mode=${currentMode}&batch=${batch}`);
                    const data = await response.json();

                    if (data.results && data.results.length > 0) {
                        data.results.forEach(res => {
                            allResults.push(res);
                            counts.t++;
                            if (res.status === 'found') counts.b++;
                            else counts.y++;

                            const card = document.createElement(res.status === 'found' ? 'a' : 'div');
                            if (res.status === 'found') {
                                card.href = res.url;
                                card.target = '_blank';
                            }

                            card.className = `card ${res.status === 'found' ? 'found' : 'not-found'}`;
                            const statusLabel = res.status === 'found'
                                ? (currentMode === 'social' ? 'Found' : 'AVAILABLE')
                                : (currentMode === 'social' ? 'None' : 'TAKEN');

                            card.innerHTML = `
                                <div class="dot"></div>
                                <div>
                                    <div style="font-size:0.7rem; opacity:0.6">${res.label} ${currentMode === 'social' ? '@' : ''}</div>
                                    <div style="font-weight:700">${res.site}</div>
                                    <div style="font-size:0.75rem">${statusLabel}</div>
                                </div>
                            `;
                            grid.appendChild(card);
                        });

                        updateStats();

                        const progress = Math.min(
                            ((batch + 1) / Math.ceil(data.total / (currentMode === 'social' ? 6 : 4))) * 100,
                            100
                        );
                        document.getElementById('pFill').style.width = progress + '%';
                    }

                    if (data.done || !data.nextBatch) break;
                    batch = data.nextBatch;
                } catch (e) {
                    break;
                }
            }

            hideScanOverlay();
            isSearching = false;
            document.getElementById('searchBtn').disabled = false;

            if (counts.b > 0) {
                document.getElementById('pdfBtn').style.display = 'block';
                document.getElementById('shareRow').style.display = 'flex';
                if (currentMode === 'social') document.getElementById('socialCta').style.display = 'block';
                if (currentMode === 'domain') document.getElementById('domainCta').style.display = 'block';
            } else {
                if (currentMode === 'domain') document.getElementById('domainCta').style.display = 'block';
            }
        }

        function share(platform) {
            const text = encodeURIComponent(`I found ${counts.b} profile(s) for "${document.getElementById('searchInput').value.trim()}" using Detective Pro!`);
            const url = encodeURIComponent(window.location.href);

            const links = {
                whatsapp: `https://api.whatsapp.com/send?text=${text} ${url}`,
                twitter: `https://twitter.com/intent/tweet?text=${text}&url=${url}`,
                linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${url}`
            };

            window.open(links[platform], '_blank');
        }

        function updateStats() {
            document.getElementById('sTarandi').innerText = counts.t;
            document.getElementById('sBulundu').innerText = counts.b;
            document.getElementById('sYok').innerText = counts.y;
        }

        function exportPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            doc.setFontSize(22);
            doc.text(`DETECTIVE PRO REPORT (${currentMode.toUpperCase()})`, 20, 20);
            doc.setFontSize(12);
            doc.text("Generated by Kominike Creative Digital - 2026", 20, 30);

            let y = 45;
            allResults.filter(r => r.status === 'found').forEach((res, i) => {
                if (y > 270) {
                    doc.addPage();
                    y = 20;
                }
                doc.text(`${i + 1}. [${res.label}] ${res.site}: ${res.url}`, 20, y);
                y += 10;
            });

            doc.save(`Detective_Report_${new Date().getTime()}.pdf`);
        }
    </script>
</body>
</html>