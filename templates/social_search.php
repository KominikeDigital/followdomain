<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}
?>
<div class="det-page-container">
    <div class="det-brand-strip">
        <img src="<?php echo url('detective/logo.webp'); ?>" alt="Detective Logo" class="det-brand-logo" loading="lazy">
        <div class="det-brand-copy">
            <span>Detective Social Intelligence</span>
            <p>Username footprints, matching profiles, and brand identity checks in one focused search screen.</p>
        </div>
    </div>

    <div class="det-title-section">
        <h1><?php echo __('nav_social_search'); ?></h1>
        <p>Search usernames across 20+ major social networks in real-time. Find matching profiles instantly.</p>
    </div>

    <div class="det-search-panel">
        <div class="det-input-group">
            <input type="text" id="searchInput" class="det-input" placeholder="Enter usernames (comma separated, e.g. john, brandname)..." onkeydown="if(event.key==='Enter') startSearch()">
        </div>

        <div class="det-action-btns">
            <button class="btn btn-primary" id="searchBtn" onclick="startSearch()">🔍 Search Profiles</button>
            <button class="btn btn-secondary" id="pdfBtn" onclick="exportPDF()" style="display: none; background: #10b981; color: #fff;">📄 Download PDF Report</button>
        </div>

        <div class="det-stats" id="statsBar">
            <div class="det-stat">📡 <span id="sTarandi">0</span> Scanned</div>
            <div class="det-stat" style="color:#10b981">✅ <span id="sBulundu">0</span> Found</div>
            <div class="det-stat" style="color:#ef4444">❌ <span id="sYok">0</span> Available</div>
        </div>

        <div class="det-progress" id="pBar"><div class="det-progress-fill" id="pFill"></div></div>

        <div class="det-share-row" id="shareRow">
            <button class="det-share-btn det-share-wa" onclick="share('whatsapp')">📲 WhatsApp</button>
            <button class="det-share-btn det-share-tw" onclick="share('twitter')">𝕏 Twitter</button>
            <button class="det-share-btn det-share-li" onclick="share('linkedin')">💼 LinkedIn</button>
        </div>

        <div class="det-cta-banner" id="socialCta">
            <p>🌐 Need professional digital identity and brand protection services?</p>
            <p>Contact us: <a href="mailto:hello@tldix.com">hello@tldix.com</a> &nbsp;|&nbsp; We'll help you secure all matching profiles.</p>
        </div>
    </div>

    <div class="det-results" id="resultsGrid"></div>
</div>

<div class="det-overlay" id="scanOverlay">
    <canvas id="scanCanvas"></canvas>
    <p>🔍 Scanning Profiles...</p>
</div>

<!-- Load jsPDF & DotLottie via CDNs -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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

        let batch = 0;

        while (true) {
            try {
                const response = await fetch(`<?php echo url("social-search/ajax"); ?>?query=${encodeURIComponent(query)}&batch=${batch}`);
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

                        card.className = `det-card ${res.status === 'found' ? 'found' : 'not-found'}`;
                        const statusLabel = res.status === 'found' ? 'Found' : 'Available';

                        card.innerHTML = `
                            <div class="dot"></div>
                            <div>
                                <div class="det-card-label">@${res.label}</div>
                                <div class="det-card-title">${res.site}</div>
                                <div class="det-card-status">${statusLabel}</div>
                            </div>
                        `;
                        grid.appendChild(card);
                    });

                    updateStats();

                    const progress = Math.min(
                        ((batch + 1) / Math.ceil(data.total / 6)) * 100,
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
            document.getElementById('pdfBtn').style.display = 'inline-flex';
            document.getElementById('shareRow').style.display = 'flex';
            document.getElementById('socialCta').style.display = 'block';
        }
    }

    function share(platform) {
        const text = encodeURIComponent(`I found ${counts.b} profile(s) for "${document.getElementById('searchInput').value.trim()}" using TLDix Social Search!`);
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
        doc.text("TLDIX SOCIAL SEARCH REPORT", 20, 20);
        doc.setFontSize(12);
        doc.text("Generated by TLDix - 2026", 20, 30);

        let y = 45;
        allResults.filter(r => r.status === 'found').forEach((res, i) => {
            if (y > 270) {
                doc.addPage();
                y = 20;
            }
            doc.text(`${i + 1}. [@${res.label}] ${res.site}: ${res.url}`, 20, y);
            y += 10;
        });

        doc.save(`TLDix_Social_Report_${new Date().getTime()}.pdf`);
    }
</script>
