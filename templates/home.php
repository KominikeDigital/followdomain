<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

// Fetch top 6 trending domains for preview
$stmt = $pdo->query("SELECT * FROM domains ORDER BY follower_count DESC, last_checked DESC LIMIT 6");
$homeTrending = $stmt->fetchAll();
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-content">
        <h1 class="hero-title"><?php echo __('hero_title'); ?></h1>
        <p class="hero-subtitle"><?php echo __('hero_subtitle'); ?></p>

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

<?php include __DIR__ . '/premium-section.php'; ?>

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
    </div>
</section>

<!-- Premium Features -->
<section class="premium-features-section">
    <div class="section-header">
        <span class="premium-section-kicker">Premium</span>
        <h2 class="section-title"><?php echo __('premium_features_title'); ?></h2>
        <p class="section-subtitle"><?php echo __('premium_features_subtitle'); ?></p>
    </div>

    <div class="premium-features-grid">
        <div class="premium-feature-card">
            <div class="premium-feature-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
            </div>
            <h3><?php echo __('feature_1_title'); ?></h3>
            <p><?php echo __('feature_1_desc'); ?></p>
        </div>

        <div class="premium-feature-card">
            <div class="premium-feature-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
            </div>
            <h3><?php echo __('feature_2_title'); ?></h3>
            <p><?php echo __('feature_2_desc'); ?></p>
        </div>

        <div class="premium-feature-card">
            <div class="premium-feature-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 20h9"></path>
                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                    <path d="m15 5 3 3"></path>
                </svg>
            </div>
            <h3><?php echo __('feature_3_title'); ?></h3>
            <p><?php echo __('feature_3_desc'); ?></p>
        </div>
    </div>

    <div class="section-actions premium-feature-actions">
        <a href="<?php echo url('register'); ?>" class="btn btn-primary"><?php echo __('premium_features_cta'); ?></a>
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
        <?php foreach ($homeTrending as $tDom): 
            $cd = getCountdownDetails($tDom['expiration_date']);
        ?>
            <a href="<?php echo url('domain/' . urlencode($tDom['domain_name'])); ?>" class="trending-item-card">
                <div class="trending-meta">
                    <span class="trending-name"><?php echo esc($tDom['domain_name']); ?></span>
                    <span class="trending-followers">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        <?php echo (int)$tDom['follower_count'] . ' ' . __('trending_followers'); ?>
                    </span>
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
