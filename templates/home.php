<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

// Fetch top 6 trending domains for preview
$stmt = $pdo->query("SELECT * FROM domains ORDER BY follower_count DESC, last_checked DESC LIMIT 6");
$homeTrending = $stmt->fetchAll();

if (!function_exists('renderHeroTitle')) {
    function renderHeroTitle($lang)
    {
        $words = ['domain', 'hosting', 'SSL'];
        $wordItems = implode('', array_map(static function ($word) {
            return '<span class="hero-rotator-word">' . esc($word) . '</span>';
        }, $words));
        $rotator = '<span class="hero-rotator" aria-label="' . esc(implode(', ', $words)) . '"><span class="hero-rotator-track" aria-hidden="true">' . $wordItems . '</span></span>';

        switch ($lang) {
            case 'tr':
                return 'Bir daha hiçbir ' . $rotator . ' <span class="hero-accent">kaybetmeyin.</span>';
            case 'es':
                return 'No vuelvas a perder un ' . $rotator . ' <span class="hero-accent">nunca más.</span>';
            case 'de':
                return 'Verlieren Sie <span class="hero-accent">nie wieder</span> eine ' . $rotator . '.';
            default:
                return 'Never lose a ' . $rotator . ' <span class="hero-accent">again.</span>';
        }
    }
}

if (!function_exists('getTrendBadgeLabel')) {
    function getTrendBadgeLabel($index)
    {
        $labels = ['Hot', 'Watched', 'Trending'];
        return $labels[$index % count($labels)];
    }
}
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-content">
        <h1 class="hero-title"><?php echo renderHeroTitle($lang ?? 'en'); ?></h1>
        <p class="hero-subtitle"><?php echo __('hero_subtitle'); ?></p>
        <p class="hero-subtitle hero-subtitle-secondary"><?php echo __('hero_subtitle_secondary'); ?></p>

        <div class="hero-free-strip">
            <span class="hero-free-badge"><?php echo __('hero_free_badge'); ?></span>
            <a href="<?php echo url('register'); ?>" class="btn btn-primary hero-free-cta"><?php echo __('hero_free_cta'); ?></a>
            <span class="hero-free-note"><?php echo __('hero_free_note'); ?></span>
        </div>
        
        <div class="search-container-glass">
            <form action="<?php echo url(''); ?>" method="GET" class="search-form" id="searchForm">
                <div class="input-group">
                    <span class="search-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </span>
                    <input type="text" id="domainInput" name="q" placeholder="<?php echo esc(__('search_placeholder')); ?>" autocomplete="off" required>
                    <button type="submit" class="btn btn-primary btn-search"><?php echo __('search_btn'); ?></button>
                </div>
            </form>
            <div id="searchSuggestions" class="search-suggestions" hidden></div>
        </div>
        <p class="search-tip"><?php echo __('search_tip'); ?></p>
    </div>
</section>

<!-- How It Works Section -->
<section class="info-section">
    <div class="section-header">
        <h2 class="section-title"><?php echo __('how_it_works_title'); ?></h2>
        <p class="section-subtitle"><?php echo __('how_it_works_subtitle'); ?></p>
    </div>
    
    <div class="steps-grid">
        <div class="step-card">
            <div class="step-num">01</div>
            <h3 class="flight-display-title" data-flight-text="<?php echo esc(__('step_1_title')); ?>"><?php echo __('step_1_title'); ?></h3>
            <p><?php echo __('step_1_desc'); ?></p>
        </div>
        
        <div class="step-card">
            <div class="step-num">02</div>
            <h3 class="flight-display-title" data-flight-text="<?php echo esc(__('step_2_title')); ?>"><?php echo __('step_2_title'); ?></h3>
            <p><?php echo __('step_2_desc'); ?></p>
        </div>
        
        <div class="step-card">
            <div class="step-num">03</div>
            <h3 class="flight-display-title" data-flight-text="<?php echo esc(__('step_3_title')); ?>"><?php echo __('step_3_title'); ?></h3>
            <p><?php echo __('step_3_desc'); ?></p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/premium-section.php'; ?>

<?php
$chromeExtensionUrl = trim((string)($config['chrome_extension_url'] ?? ''));
$chromeExtensionHasUrl = $chromeExtensionUrl !== '';
?>
<!-- Chrome Extension Section -->
<section class="chrome-extension-section" id="chrome-extension">
    <div class="chrome-extension-shell">
        <div class="chrome-extension-copy">
            <span class="chrome-extension-kicker"><?php echo __('chrome_extension_kicker'); ?></span>
            <h2 class="section-title"><?php echo __('chrome_extension_title'); ?></h2>
            <p class="section-subtitle"><?php echo __('chrome_extension_subtitle'); ?></p>

            <div class="chrome-extension-benefits">
                <div class="chrome-extension-benefit">
                    <span class="chrome-extension-benefit-icon">✓</span>
                    <div>
                        <strong><?php echo __('chrome_extension_benefit_1_title'); ?></strong>
                        <p><?php echo __('chrome_extension_benefit_1_desc'); ?></p>
                    </div>
                </div>
                <div class="chrome-extension-benefit">
                    <span class="chrome-extension-benefit-icon">✓</span>
                    <div>
                        <strong><?php echo __('chrome_extension_benefit_2_title'); ?></strong>
                        <p><?php echo __('chrome_extension_benefit_2_desc'); ?></p>
                    </div>
                </div>
                <div class="chrome-extension-benefit">
                    <span class="chrome-extension-benefit-icon">✓</span>
                    <div>
                        <strong><?php echo __('chrome_extension_benefit_3_title'); ?></strong>
                        <p><?php echo __('chrome_extension_benefit_3_desc'); ?></p>
                    </div>
                </div>
            </div>

            <div class="chrome-extension-actions">
                <a href="<?php echo $chromeExtensionHasUrl ? esc($chromeExtensionUrl) : '#chrome-extension'; ?>"
                   class="btn btn-primary chrome-extension-cta <?php echo $chromeExtensionHasUrl ? '' : 'is-disabled'; ?>"
                   <?php echo $chromeExtensionHasUrl ? 'target="_blank" rel="noopener"' : 'aria-disabled="true" onclick="return false;"'; ?>>
                    <?php echo __('chrome_extension_cta'); ?>
                </a>
                <span class="chrome-extension-note">
                    <?php echo $chromeExtensionHasUrl ? __('chrome_extension_note_live') : __('chrome_extension_note_pending'); ?>
                </span>
            </div>
        </div>

        <div class="chrome-extension-preview" aria-hidden="true">
            <div class="extension-window-bar">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <div class="extension-popup-preview">
                <div class="extension-popup-head">
                    <div class="extension-popup-logo">T</div>
                    <div>
                        <strong>TLDix Panel</strong>
                        <small>Chrome Extension</small>
                    </div>
                </div>
                <div class="extension-popup-stats">
                    <div><strong>12</strong><span>Domains</span></div>
                    <div><strong>3</strong><span>Hosting</span></div>
                    <div><strong>2</strong><span>30 Days</span></div>
                </div>
                <div class="extension-popup-tabs">
                    <span>Domain</span>
                    <span>Hosting</span>
                    <span>SSL</span>
                </div>
                <div class="extension-popup-row">
                    <div>
                        <strong>example.com</strong>
                        <span>Namecheap</span>
                    </div>
                    <em>44 days</em>
                </div>
                <div class="extension-popup-row warning">
                    <div>
                        <strong>renew-soon.com</strong>
                        <span>SSL certificate</span>
                    </div>
                    <em>6 days</em>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Grid -->
<section class="features-section">
    <div class="section-header">
        <h2 class="section-title"><?php echo __('features_title'); ?></h2>
        <p class="section-subtitle"><?php echo __('features_subtitle'); ?></p>
    </div>
    
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </div>
            <h3><?php echo __('home_feature_1_title'); ?></h3>
            <p><?php echo __('home_feature_1_desc'); ?></p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <h3><?php echo __('home_feature_2_title'); ?></h3>
            <p><?php echo __('home_feature_2_desc'); ?></p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <h3><?php echo __('home_feature_3_title'); ?></h3>
            <p><?php echo __('home_feature_3_desc'); ?></p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            </div>
            <h3><?php echo __('home_feature_4_title'); ?></h3>
            <p><?php echo __('home_feature_4_desc'); ?></p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="8" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><line x1="7" y1="8" x2="7.01" y2="8"/><line x1="7" y1="17" x2="7.01" y2="17"/></svg>
            </div>
            <h3><?php echo __('home_feature_5_title'); ?></h3>
            <p><?php echo __('home_feature_5_desc'); ?></p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-5"/></svg>
            </div>
            <h3><?php echo __('home_feature_6_title'); ?></h3>
            <p><?php echo __('home_feature_6_desc'); ?></p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96 12 12l8.73-5.04"/><path d="M12 22V12"/></svg>
            </div>
            <h3><?php echo __('home_feature_7_title'); ?></h3>
            <p><?php echo __('home_feature_7_desc'); ?></p>
        </div>
    </div>
</section>

<!-- Trending Section -->
<?php if (!empty($homeTrending)): ?>
<section class="home-trending-section">
    <div class="section-header">
        <h2 class="section-title"><?php echo __('trending_title'); ?></h2>
        <p class="section-subtitle"><?php echo __('trending_subtitle'); ?></p>
    </div>
    
    <div class="trending-grid-list">
        <?php foreach ($homeTrending as $trendIndex => $tDom): 
            $cd = getCountdownDetails($tDom['expiration_date']);
        ?>
            <a href="<?php echo url('domain/' . urlencode($tDom['domain_name'])); ?>" class="trending-item-card">
                <div class="trending-meta">
                    <span class="trending-name"><?php echo esc($tDom['domain_name']); ?></span>
                    <span class="trend-label-badge"><?php echo esc(getTrendBadgeLabel($trendIndex)); ?></span>
                </div>
                <div class="trending-countdown <?php echo $cd['expired'] ? 'expired' : ''; ?>">
                    <?php echo esc($cd['text']); ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    
    <div class="section-actions" style="margin-top: 2.5rem;">
        <a href="<?php echo url('trending'); ?>" class="btn btn-secondary"><?php echo __('view_all_trending'); ?></a>
    </div>
</section>
<?php endif; ?>

<!-- Long Term Reliability -->
<section class="reliability-section">
    <div class="reliability-card">
        <div class="reliability-content">
            <h2><?php echo __('reliability_title'); ?></h2>
            <p><?php echo __('reliability_desc'); ?></p>
            <div class="reliability-badges">
                <div class="badge-item">
                    <span class="badge-icon">✓</span>
                    <span><?php echo __('reliability_badge_1'); ?></span>
                </div>
                <div class="badge-item">
                    <span class="badge-icon">✓</span>
                    <span><?php echo __('reliability_badge_2'); ?></span>
                </div>
                <div class="badge-item">
                    <span class="badge-icon">✓</span>
                    <span><?php echo __('reliability_badge_3'); ?></span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Blog Slider Section -->
<section class="home-blog-slider-section">
    <div class="section-header">
        <h2 class="section-title"><?php echo __('blog_slider_title', 'Latest Insights & Guides'); ?></h2>
        <p class="section-subtitle"><?php echo __('blog_slider_subtitle', 'Explore our latest articles about domains, hosting, SEO, and web security.'); ?></p>
    </div>
    
    <div class="blog-slider-outer">
        <button class="blog-slider-btn prev" id="blogSliderPrev" aria-label="Previous posts">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>
        
        <div class="blog-slider-container" id="blogSliderContainer">
            <?php 
            $allPosts = getBlogPosts($lang);
            // Sort posts by date descending
            uasort($allPosts, function($a, $b) {
                return strcmp($b['date'] ?? '', $a['date'] ?? '');
            });
            $allPosts = array_slice($allPosts, 0, 10, true);
            foreach ($allPosts as $post): 
            ?>
                <div class="blog-slider-card">
                    <a href="<?php echo url('blog/' . $post['slug']); ?>" class="blog-card-image-link">
                        <img src="<?php echo esc(mediaUrl($post['image'])); ?>" alt="<?php echo esc($post['title']); ?>" loading="lazy">
                        <span class="blog-card-category"><?php echo esc($post['category']); ?></span>
                    </a>
                    <div class="blog-card-content">
                        <span class="blog-card-date">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: middle; display: inline-block; position: relative; top: -1px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            <?php echo date('M d, Y', strtotime($post['date'])); ?>
                        </span>
                        <h3 class="blog-card-title">
                            <a href="<?php echo url('blog/' . $post['slug']); ?>"><?php echo esc($post['title']); ?></a>
                        </h3>
                        <p class="blog-card-excerpt"><?php echo esc($post['description']); ?></p>
                        <a href="<?php echo url('blog/' . $post['slug']); ?>" class="blog-card-link">
                            <span><?php echo __('read_more', 'Read More'); ?></span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <button class="blog-slider-btn next" id="blogSliderNext" aria-label="Next posts">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </button>
    </div>
</section>
