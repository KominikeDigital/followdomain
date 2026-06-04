<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

// Get pre-loaded query if redirected from home
$initQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$primaryDomainProvider = getPrimaryAffiliateProvider($pdo, $config, 'domain_search_primary_provider', 'domain', 'namecheap');
$recommendedHostingProviders = getSelectedAffiliateProviders($pdo, $config, 'recommended_hosting_codes', 'hosting', ['hostinger', 'bluehost', 'siteground']);
$recommendedSslProviders = getSelectedAffiliateProviders($pdo, $config, 'recommended_ssl_codes', 'ssl', ['namecheap_ssl', 'ssls', 'ssldragon']);
$emailProviders = getAffiliateProviders($pdo, $config, 'email', false);
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
            Domain, hosting, SSL, and email options are separated below so you can compare the right service without noise.
        </div>
    </div>

    <div class="comparison-tabs domain-search-comparison" id="searchComparisonShell" data-comparison-tabs style="display:none;">
        <div class="comparison-tab-list" role="tablist">
            <button type="button" class="comparison-tab active" data-tab-target="domains">Domains</button>
            <button type="button" class="comparison-tab" data-tab-target="hosting">Hosting</button>
            <button type="button" class="comparison-tab" data-tab-target="ssl">SSL</button>
            <button type="button" class="comparison-tab" data-tab-target="email">Email</button>
        </div>

        <div class="comparison-panel active" data-tab-panel="domains">
            <div class="domain-search-results-list" id="resultsGrid"></div>
            <div class="suggested-domain-block" id="suggestedDomainBlock" style="display:none;">
                <div class="suggested-domain-head">
                    <h4>Suggested Domains</h4>
                    <span>Available alternatives from your search</span>
                </div>
                <div class="suggested-domain-list" id="suggestedDomainList"></div>
            </div>
        </div>

        <?php
            $tabGroups = [
                'hosting' => $recommendedHostingProviders,
                'ssl' => $recommendedSslProviders,
                'email' => $emailProviders,
            ];
        ?>
        <?php foreach ($tabGroups as $panelKey => $providers): ?>
            <div class="comparison-panel" data-tab-panel="<?php echo esc($panelKey); ?>">
                <?php if (empty($providers)): ?>
                    <div class="comparison-empty">No <?php echo esc(strtoupper($panelKey)); ?> provider is selected yet.</div>
                <?php else: ?>
                    <div class="comparison-provider-list">
                        <?php foreach ($providers as $provider): ?>
                            <a href="<?php echo url('go?to=' . urlencode($provider['code']) . '&utm_source=domain_search_' . urlencode($panelKey)); ?>" target="_blank" rel="noopener" class="comparison-provider-row">
                                <span class="comparison-provider-name"><?php echo esc($provider['name']); ?></span>
                                <span class="comparison-provider-desc"><?php echo esc($provider['description']); ?></span>
                                <strong>Recommended</strong>
                                <span class="comparison-provider-action"><?php echo esc($provider['button_label']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
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
    const primaryDomainProviderCode = <?php echo json_encode($primaryDomainProvider['code'] ?? 'namecheap'); ?>;

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
        const shell = document.getElementById('searchComparisonShell');
        const suggestedBlock = document.getElementById('suggestedDomainBlock');
        const suggestedList = document.getElementById('suggestedDomainList');
        grid.innerHTML = '';
        suggestedList.innerHTML = '';
        suggestedBlock.style.display = 'none';
        shell.style.display = 'block';
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
                        const fullDomain = `${res.label}${res.site}`;
                        
                        if (isAvailable) {
                            card.href = getUrl(`go?to=${encodeURIComponent(primaryDomainProviderCode)}&utm_source=domain_search&query=${encodeURIComponent(fullDomain)}`);
                            card.className = 'domain-result-row available';
                        } else {
                            card.href = getUrl(`domain/${encodeURIComponent(fullDomain)}`);
                            card.className = 'domain-result-row taken';
                        }

                        const statusLabel = isAvailable ? 'Available' : 'Taken';
                        const ctaText = isAvailable ? 'Register Now' : 'Details';

                        card.innerHTML = `
                            <span class="domain-result-status-dot"></span>
                            <strong>${fullDomain}</strong>
                            <span>${statusLabel}</span>
                            <em>${ctaText}</em>
                        `;
                        grid.appendChild(card);

                        if (isAvailable) {
                            const suggestedLink = document.createElement('a');
                            suggestedLink.href = getUrl(`go?to=${encodeURIComponent(primaryDomainProviderCode)}&utm_source=suggested_domain&query=${encodeURIComponent(fullDomain)}`);
                            suggestedLink.target = '_blank';
                            suggestedLink.rel = 'noopener';
                            suggestedLink.textContent = fullDomain;
                            suggestedList.appendChild(suggestedLink);
                        }
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
        if (suggestedList.children.length > 0 && counts.y > 0) {
            suggestedBlock.style.display = 'block';
        }
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
