<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

global $config;

// Check if domainData was loaded by the router
$hasData = isset($domainData) && is_array($domainData);
$isRegistered = $hasData && (!isset($domainData['registered']) || $domainData['registered'] !== false);

?>

<div class="domain-details-page">
    <?php if (!$hasData): ?>
        <div class="error-card glass-panel">
            <h2><?php echo __('domain_not_found'); ?></h2>
            <p><?php echo __('domain_not_found_sub'); ?></p>
            <a href="<?php echo url(''); ?>" class="btn btn-primary" style="margin-top: 1rem;"><?php echo __('back_to_home'); ?></a>
        </div>
    <?php else: ?>
        
        <!-- Domain Header Title -->
        <div class="domain-title-bar">
            <div>
                <h1 class="domain-main-name"><?php echo esc($domainName); ?></h1>
                <div class="domain-status-badge <?php echo $isRegistered ? 'registered' : 'available'; ?>">
                    <?php echo $isRegistered ? __('status_registered') : __('status_available'); ?>
                </div>
            </div>
            
            <?php if ($isRegistered): ?>
                <div class="follower-stats">
                    <span class="follower-count-num"><?php echo (int)$domainData['follower_count']; ?></span>
                    <span class="follower-count-lbl"><?php echo __('watchers_count_badge'); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="domain-grid-layout">
            
            <!-- LEFT COLUMN: Domain Info & Countdown -->
            <div class="main-column">
                
                <?php if ($isRegistered): 
                    $cd = getCountdownDetails($domainData['expiration_date']);
                    $progress = getExpirationProgress($domainData['registration_date'], $domainData['expiration_date']);
                ?>
                    <!-- Expiration Countdown Card -->
                    <div class="glass-panel countdown-card">
                        <h3 class="card-subtitle"><?php echo __('countdown_title'); ?></h3>
                        
                        <div class="countdown-timer" id="countdownTimer" data-time="<?php echo esc($domainData['expiration_date']); ?>">
                            <div class="timer-box">
                                <span class="timer-number" id="cd-days"><?php echo sprintf('%02d', $cd['days']); ?></span>
                                <span class="timer-label"><?php echo __('label_days'); ?></span>
                            </div>
                            <div class="timer-box">
                                <span class="timer-number" id="cd-hours"><?php echo sprintf('%02d', $cd['hours']); ?></span>
                                <span class="timer-label"><?php echo __('label_hours'); ?></span>
                            </div>
                            <div class="timer-box">
                                <span class="timer-number" id="cd-minutes"><?php echo sprintf('%02d', $cd['minutes']); ?></span>
                                <span class="timer-label"><?php echo __('label_minutes'); ?></span>
                            </div>
                            <div class="timer-box">
                                <span class="timer-number" id="cd-seconds"><?php echo sprintf('%02d', $cd['seconds']); ?></span>
                                <span class="timer-label"><?php echo __('label_seconds'); ?></span>
                            </div>
                        </div>

                        <!-- Progress Bar representing life cycle -->
                        <div class="lifecycle-bar-container">
                            <div class="lifecycle-bar" style="width: <?php echo (float)$progress; ?>%;"></div>
                        </div>
                        <div class="lifecycle-labels">
                            <span><?php echo __('registered_on'); ?> <?php echo formatDate($domainData['registration_date'], 'd M Y'); ?></span>
                            <span><?php echo __('expires_on'); ?> <?php echo formatDate($domainData['expiration_date'], 'd M Y'); ?></span>
                        </div>
                    </div>

                    <?php
                    // Bağlamsal Yenileme CTA: 30 gün veya daha az kaldıysa göster
                    $daysLeft = $cd['days'] ?? 999;
                    $isExpiringSoon = (!($cd['expired'] ?? false) && $daysLeft <= 30);
                    $isExpiredState = ($cd['expired'] ?? false);
                    if ($isExpiringSoon || $isExpiredState):
                        $urgencyColor = ($daysLeft <= 7 || $isExpiredState) ? '#ef4444' : '#f59e0b';
                    ?>
                    <!-- Contextual Renewal CTA — Bağlamsal Yenileme Kartı -->
                    <div class="glass-panel" style="border-left: 4px solid <?php echo $urgencyColor; ?>; margin-top: 1.5rem; padding: 1.5rem;">
                        <div style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1.25rem;">
                            <span style="font-size: 1.5rem; flex-shrink: 0;"><?php echo $isExpiredState ? '🚨' : ($daysLeft <= 7 ? '⚠️' : '🔔'); ?></span>
                            <div>
                                <h4 style="font-family: var(--font-display); font-size: 1rem; color: <?php echo $urgencyColor; ?>; margin-bottom: 0.3rem;">
                                    <?php if ($isExpiredState): ?>
                                        Bu Domain Süresi Dolmuş — Kurtarabilirsiniz!
                                    <?php elseif ($daysLeft <= 7): ?>
                                        Sadece <strong><?php echo $daysLeft; ?> gün</strong> kaldı — Hemen yenileyin!
                                    <?php else: ?>
                                        <strong><?php echo $daysLeft; ?> gün</strong> içinde süresi dolacak
                                    <?php endif; ?>
                                </h4>
                                <p style="font-size: 0.82rem; color: var(--color-text-secondary); margin: 0; line-height: 1.5;">
                                    En düşük fiyatlarla yenileyin. Affiliate ortaklarımız aracılığıyla kayıt veya yenileme yapabilirsiniz.
                                </p>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                            <a href="<?php echo url('go?to=namecheap&utm_source=renewal_cta'); ?>" target="_blank" rel="noopener"
                               style="display:flex;align-items:center;justify-content:center;gap:0.4rem;padding:0.55rem;border-radius:8px;background:rgba(222,55,33,0.1);border:1px solid rgba(222,55,33,0.3);color:#fc9183;font-size:0.8rem;font-weight:600;text-decoration:none;transition:all 0.2s;">
                                🏷️ Namecheap
                            </a>
                            <a href="<?php echo url('go?to=godaddy&utm_source=renewal_cta'); ?>" target="_blank" rel="noopener"
                               style="display:flex;align-items:center;justify-content:center;gap:0.4rem;padding:0.55rem;border-radius:8px;background:rgba(0,130,138,0.1);border:1px solid rgba(0,130,138,0.3);color:#6ee7e7;font-size:0.8rem;font-weight:600;text-decoration:none;transition:all 0.2s;">
                                🌐 GoDaddy
                            </a>
                            <a href="<?php echo url('go?to=dynadot&utm_source=renewal_cta'); ?>" target="_blank" rel="noopener"
                               style="display:flex;align-items:center;justify-content:center;gap:0.4rem;padding:0.55rem;border-radius:8px;background:rgba(185,28,28,0.1);border:1px solid rgba(185,28,28,0.3);color:#fca5a5;font-size:0.8rem;font-weight:600;text-decoration:none;transition:all 0.2s;">
                                📈 Dynadot
                            </a>
                            <a href="<?php echo url('go?to=porkbun&utm_source=renewal_cta'); ?>" target="_blank" rel="noopener"
                               style="display:flex;align-items:center;justify-content:center;gap:0.4rem;padding:0.55rem;border-radius:8px;background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.3);color:#a5b4fc;font-size:0.8rem;font-weight:600;text-decoration:none;transition:all 0.2s;">
                                🐷 Porkbun
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Domain Details Specification Card -->

                    <div class="glass-panel specs-card">
                        <h3><?php echo __('domain_specs'); ?></h3>
                        <table class="specs-table">
                            <tbody>
                                <tr>
                                    <td><?php echo __('col_registrar'); ?></td>
                                    <td class="highlight-text"><?php echo esc($domainData['registrar']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo __('registration_date_label'); ?></td>
                                    <td><?php echo formatDate($domainData['registration_date']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo __('expiration_date_label'); ?></td>
                                    <td><?php echo formatDate($domainData['expiration_date']); ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo __('updated_on_label'); ?></td>
                                    <td><?php echo formatDate($domainData['last_changed_date'] ?? null); ?></td>
                                </tr>
                                <tr>
                                    <td><?php echo __('status_label'); ?></td>
                                    <td>
                                        <div class="status-tags">
                                            <?php 
                                                $statuses = array_filter(array_map('trim', explode(',', $domainData['status'] ?? '')));
                                                if (empty($statuses)) {
                                                    echo '<span class="status-tag">Active</span>';
                                                } else {
                                                    foreach ($statuses as $st) {
                                                        echo '<span class="status-tag">' . esc($st) . '</span>';
                                                    }
                                                }
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php echo __('nameservers_label'); ?></td>
                                    <td>
                                        <div class="ns-list">
                                            <?php 
                                                $nsList = array_filter(array_map('trim', explode(',', $domainData['nameservers'] ?? '')));
                                                if (empty($nsList)) {
                                                    echo '<span>N/A</span>';
                                                } else {
                                                    foreach ($nsList as $ns) {
                                                        echo '<code>' . esc($ns) . '</code>';
                                                    }
                                                }
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php echo __('last_checked_label'); ?></td>
                                    <td><?php echo formatDate($domainData['last_checked']); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Check History Log -->
                    <?php 
                        $stmtHist = $pdo->prepare("SELECT * FROM domain_history WHERE domain_id = ? ORDER BY created_at DESC LIMIT 10");
                        $stmtHist->execute([$domainData['id']]);
                        $history = $stmtHist->fetchAll();
                        if (!empty($history)):
                    ?>
                        <div class="glass-panel specs-card">
                            <h3><?php echo __('whois_history'); ?></h3>
                            <ul class="history-timeline">
                                <?php foreach ($history as $h): ?>
                                    <li>
                                        <span class="timeline-date"><?php echo formatDate($h['created_at']); ?></span>
                                        <span class="timeline-event <?php echo esc($h['event_type']); ?>">
                                            <?php echo esc($h['event_description']); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    
                    <!-- AVAILABLE DOMAIN STATE -->
                    <div class="glass-panel available-card text-center">
                        <div class="available-icon">✓</div>
                        <h2><?php echo __('domain_available_title'); ?></h2>
                        <p class="available-desc"><?php echo sprintf(__('domain_available_desc'), esc($domainName)); ?></p>
                        
                        <div class="registration-cta-box">
                            <a href="<?php echo esc($config['affiliate_namecheap']); ?>&query=<?php echo urlencode($domainName); ?>" target="_blank" rel="noopener" class="btn btn-primary btn-large">
                                <?php echo __('register_with_namecheap'); ?>
                            </a>
                            <p class="cta-note"><?php echo __('register_note'); ?></p>
                        </div>

                        <!-- Price Comparison Table -->
                        <div class="price-comparison-widget" style="margin-top: 3rem; text-align: left;">
                            <h3 style="font-family: var(--font-display); font-size: 1.25rem; color: #ffffff; margin-bottom: 0.5rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;"><?php echo __('price_comparison_title'); ?></h3>
                            <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 1.5rem;">
                                <?php echo __('price_comparison_sub'); ?>
                            </p>
                            
                            <div class="table-responsive-container">
                                <table class="trending-table" style="background: rgba(255, 255, 255, 0.01);">
                                    <thead>
                                        <tr>
                                            <th><?php echo __('col_provider_name'); ?></th>
                                            <th><?php echo __('col_priority_type'); ?></th>
                                            <th><?php echo __('col_yearly_price'); ?></th>
                                            <th class="text-right"><?php echo __('col_actions'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $priceComparison = getDomainPriceComparison($domainName, $config);
                                        
                                        // Priority metadata for stars/badges
                                        $priorityMeta = [
                                            'Namecheap' => ['stars' => '⭐⭐⭐⭐⭐', 'badge' => 'Affiliate'],
                                            'Hostinger' => ['stars' => '⭐⭐⭐⭐⭐', 'badge' => 'Affiliate'],
                                            'NameSilo' => ['stars' => '⭐⭐⭐⭐', 'badge' => 'Affiliate + API'],
                                            'Porkbun' => ['stars' => '⭐⭐⭐⭐', 'badge' => 'Affiliate'],
                                            'Spaceship' => ['stars' => '⭐⭐', 'badge' => 'Affiliate'],
                                            'Dynadot' => ['stars' => '⭐⭐', 'badge' => 'Affiliate'],
                                            'Domain Name API' => ['stars' => '⭐⭐⭐', 'badge' => 'Reseller API']
                                        ];
                                        
                                        foreach ($priceComparison as $comp): 
                                            $meta = $priorityMeta[$comp['provider']] ?? ['stars' => '⭐⭐', 'badge' => 'Affiliate'];
                                        ?>
                                            <tr class="table-row-hover">
                                                <td style="font-weight: bold; color: #ffffff; display: flex; align-items: center;">
                                                    <?php echo getRegistrarLogo($comp['provider']); ?>
                                                    <?php echo esc($comp['provider']); ?>
                                                </td>
                                                <td>
                                                    <span style="color: #fbbf24; font-size: 0.8rem; margin-right: 0.5rem;"><?php echo $meta['stars']; ?></span>
                                                    <span class="status-tag" style="font-size: 0.7rem;"><?php echo $meta['badge']; ?></span>
                                                </td>
                                                <td>
                                                    <strong style="color: <?php echo ($comp['price'] === 'N/A') ? 'var(--color-text-muted)' : 'var(--color-success)'; ?>;">
                                                        <?php echo esc($comp['price']); ?>
                                                    </strong>
                                                </td>
                                                <td class="text-right">
                                                    <?php if ($comp['price'] === 'N/A'): ?>
                                                        <span class="text-muted" style="font-size: 0.85rem;"><?php echo __('not_supported'); ?></span>
                                                    <?php else: ?>
                                                        <a href="<?php echo esc($comp['aff_url']); ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" style="border-color: rgba(99, 102, 241, 0.4); color: #a5b4fc;">
                                                            <?php echo __('buy_now'); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

            </div>

            <!-- RIGHT COLUMN: Follow, Affiliate Banners, Sidebar Ads -->
            <div class="sidebar-column">
                
                <?php if ($isRegistered): ?>
                    <!-- Follow Form Card -->
                    <div class="glass-panel follow-card">
                        <h3><?php echo __('watch_domain'); ?></h3>
                        <p><?php echo __('follow_domain_sub'); ?></p>
                        
                        <?php if (isset($followMessage)): ?>
                            <div class="alert alert-success"><?php echo esc($followMessage); ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($followError)): ?>
                            <div class="alert alert-error"><?php echo esc($followError); ?></div>
                        <?php endif; ?>

                        <div id="ajaxFollowAlert" class="alert" style="display:none;"></div>

                        <form action="" method="POST" class="follow-form" id="followForm">
                            <input type="hidden" name="action" value="follow">
                            <div class="form-group">
                                <label for="followEmail"><?php echo __('label_email'); ?></label>
                                <input type="email" id="followEmail" name="email" placeholder="ornek@domain.com" required autocomplete="email">
                            </div>
                            <button type="submit" class="btn btn-primary w-full"><?php echo __('btn_follow'); ?></button>
                        </form>
                        <p class="follow-notice"><?php echo __('follow_notice'); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Hosting Affiliate Card -->
                <div class="glass-panel affiliate-card">
                    <span class="affiliate-badge"><?php echo __('recommendation_badge'); ?></span>
                    <h3><?php echo __('hosting_recommendation_title'); ?></h3>
                    <p><?php echo __('hosting_recommendation_desc'); ?></p>
                    
                    <div class="affiliate-buttons">
                        <a href="<?php echo url('go?to=hostinger'); ?>" target="_blank" rel="noopener" class="btn btn-secondary affiliate-btn hostinger">
                            <span><?php echo __('hostinger_promo'); ?></span>
                        </a>
                        <a href="<?php echo url('go?to=bluehost'); ?>" target="_blank" rel="noopener" class="btn btn-secondary affiliate-btn bluehost">
                            <span><?php echo __('bluehost_promo'); ?></span>
                        </a>
                    </div>
                </div>

                <!-- SSL Affiliate Card -->
                <div class="glass-panel affiliate-card" style="margin-top: 1.5rem;">
                    <span class="affiliate-badge" style="background: linear-gradient(135deg, #10b981, #059669);"><?php echo __('recommendation_badge'); ?></span>
                    <h3><?php echo __('ssl_recommendation_title'); ?></h3>
                    <p><?php echo __('ssl_recommendation_desc'); ?></p>
                    
                    <div class="affiliate-buttons" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <a href="<?php echo url('go?to=namecheap_ssl'); ?>" target="_blank" rel="noopener" class="btn btn-secondary affiliate-btn" style="border-color: rgba(16, 185, 129, 0.4); color: #a7f3d0; display: flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none; padding: 0.75rem;">
                            <span>🔐 <?php echo __('ssl_btn_namecheap'); ?></span>
                        </a>
                        <a href="<?php echo url('go?to=ssls'); ?>" target="_blank" rel="noopener" class="btn btn-secondary affiliate-btn" style="border-color: rgba(16, 185, 129, 0.4); color: #a7f3d0; display: flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none; padding: 0.75rem;">
                            <span>🔐 <?php echo __('ssl_btn_ssls'); ?></span>
                        </a>
                    </div>
                </div>

                <!-- Sidebar Ad Slot -->
                <?php if (($config['ad_status'] ?? 'off') === 'on' && !empty($config['ad_sidebar'])): ?>
                    <div class="ad-container ad-sidebar-slot">
                        <?php echo $config['ad_sidebar']; ?>
                    </div>
                <?php endif; ?>

            </div>

        </div> <!-- Close .domain-grid-layout -->
    <?php endif; ?>
</div>
