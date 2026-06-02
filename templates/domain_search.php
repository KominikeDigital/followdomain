<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

// Get pre-loaded query if redirected from home
$initQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
?>
<div class="det-page-container">
    <div class="det-title-section">
        <h1><?php echo __('nav_domain_search'); ?></h1>
        <p>Check domain name availability across 13 popular domain extensions (.com, .net, .org, .io...) in real-time.</p>
    </div>

    <div class="det-search-panel">
        <div class="det-input-group">
            <input type="text" id="searchInput" class="det-input" placeholder="Enter keyword or brand name (e.g. mybrand)..." value="<?php echo esc($initQuery); ?>" onkeydown="if(event.key==='Enter') startSearch()">
        </div>

        <div class="det-action-btns">
            <button class="btn btn-primary" id="searchBtn" onclick="startSearch()">🔍 Check Availability</button>
        </div>

        <div class="det-stats" id="statsBar">
            <div class="det-stat">📡 <span id="sTarandi">0</span> Scanned</div>
            <div class="det-stat" style="color:#10b981">✅ <span id="sBulundu">0</span> Available</div>
            <div class="det-stat" style="color:#ef4444">❌ <span id="sYok">0</span> Taken</div>
        </div>

        <div class="det-progress" id="pBar"><div class="det-progress-fill" id="pFill"></div></div>

        <div class="det-cta-banner" id="domainCta">
            💡 Looking to register an available domain at an affordable price? <a href="https://www.hostinger.com/tr/referral?REFERRALCODE=VKNEMRECEYZG" target="_blank">Get a great deal on Domain & Hosting here!</a>
        </div>
    </div>

    <div class="det-results" id="resultsGrid"></div>
</div>

<div class="det-overlay" id="scanOverlay">
    <canvas id="scanCanvas"></canvas>
    <p>🔍 Querying Extensions...</p>
</div>

<!-- Load Lottie via CDN -->
<script src="https://unpkg.com/@lottiefiles/dotlottie-web@0.40.0/dist/dotlottie-web.umd.js"></script>

<script>
    let allResults = [];
    let counts = { t: 0, b: 0, y: 0 };
    let isSearching = false;
    let lottiePlayer = null;

    function initLottie() {
        try {
            if (lottiePlayer || typeof DotLottie === 'undefined') return;
            lottiePlayer = new DotLottie({
                canvas: document.getElementById('scanCanvas'),
                src: '<?php echo url("detective/scan.lottie"); ?>',
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

        // Strip extensions or invalid characters just to check raw keyword
        const cleanQuery = query.replace(/\.[a-zA-Z]{2,}$/, '').replace(/[^a-zA-Z0-9-]/g, '');
        if (!cleanQuery) return;

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
        document.getElementById('domainCta').style.display = 'none';

        let batch = 0;

        while (true) {
            try {
                const response = await fetch(`<?php echo url("domain-search/ajax"); ?>?query=${encodeURIComponent(cleanQuery)}&batch=${batch}`);
                const data = await response.json();

                if (data.results && data.results.length > 0) {
                    data.results.forEach(res => {
                        allResults.push(res);
                        counts.t++;
                        if (res.status === 'found') counts.b++;
                        else counts.y++;

                        // res.status === 'found' means available in detective logic, not_found means taken
                        const isAvailable = (res.status === 'found');
                        const card = document.createElement('a');
                        card.target = '_blank';
                        
                        if (isAvailable) {
                            card.href = 'https://www.hostinger.com/tr/referral?REFERRALCODE=VKNEMRECEYZG';
                            card.className = 'det-card found';
                        } else {
                            card.href = res.url; // Whois page
                            card.className = 'det-card not-found';
                        }

                        const statusLabel = isAvailable ? 'AVAILABLE' : 'TAKEN';
                        const ctaText = isAvailable ? 'Register Now' : 'Whois Details';

                        card.innerHTML = `
                            <div class="dot"></div>
                            <div>
                                <div class="det-card-label">${res.label}${res.site}</div>
                                <div class="det-card-title">${statusLabel}</div>
                                <div class="det-card-status">${ctaText}</div>
                            </div>
                        `;
                        grid.appendChild(card);
                    });

                    updateStats();

                    const progress = Math.min(
                        ((batch + 1) / Math.ceil(data.total / 4)) * 100,
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
        document.getElementById('domainCta').style.display = 'block';
    }

    function updateStats() {
        document.getElementById('sTarandi').innerText = counts.t;
        document.getElementById('sBulundu').innerText = counts.b;
        document.getElementById('sYok').innerText = counts.y;
    }

    // Auto-trigger if query is passed
    window.addEventListener('DOMContentLoaded', () => {
        const queryVal = document.getElementById('searchInput').value.trim();
        if (queryVal !== '') {
            startSearch();
        }
    });
</script>
