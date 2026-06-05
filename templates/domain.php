<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

global $config, $pdo;

// Check if domainData was loaded by the router
$hasData = isset($domainData) && is_array($domainData);
$isRegistered = $hasData && (!isset($domainData['registered']) || $domainData['registered'] !== false);
$primaryDomainProvider = getPrimaryAffiliateProvider($pdo, $config, 'domain_search_primary_provider', 'domain', 'namecheap');
$recommendedHostingProviders = getSelectedAffiliateProviders($pdo, $config, 'recommended_hosting_codes', 'hosting', ['hostinger', 'bluehost', 'siteground']);
$recommendedSslProviders = getSelectedAffiliateProviders($pdo, $config, 'recommended_ssl_codes', 'ssl', ['namecheap_ssl', 'ssls', 'ssldragon']);
$emailProviders = getAffiliateProviders($pdo, $config, 'email', false);
$domainViewerPlan = isLoggedIn() ? getUserPlan($pdo, $_SESSION['user_id']) : 'free';
$canViewDomainHistory = isLoggedIn() && userPlanAllows($domainViewerPlan, 'domain_history');
$domainHistoryDays = getPlanCapability($domainViewerPlan, 'history_days');

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
                                    En düşük fiyatlarla yenileyin. Güvenilir domain sağlayıcıları üzerinden kayıt veya yenileme yapabilirsiniz.
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
                        $history = [];
                        if ($canViewDomainHistory) {
                            $stmtHist = $pdo->prepare("SELECT * FROM domain_history WHERE domain_id = ? ORDER BY created_at DESC LIMIT 20");
                            $stmtHist->execute([$domainData['id']]);
                            $historyRows = $stmtHist->fetchAll();
                            $historyCutoff = $domainHistoryDays === null ? null : strtotime('-' . (int)$domainHistoryDays . ' days');
                            foreach ($historyRows as $historyRow) {
                                $createdAt = strtotime($historyRow['created_at'] ?? '');
                                if ($historyCutoff !== null && $createdAt && $createdAt < $historyCutoff) {
                                    continue;
                                }
                                $history[] = $historyRow;
                                if (count($history) >= 10) {
                                    break;
                                }
                            }
                        }
                    ?>
                    <?php if ($canViewDomainHistory && !empty($history)): ?>
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
                    <?php elseif (!$canViewDomainHistory): ?>
                        <div class="glass-panel specs-card">
                            <h3><?php echo __('whois_history'); ?></h3>
                            <p class="text-muted">Geçmiş kayıtlar premium paketlerde açıktır.</p>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    
                    <!-- AVAILABLE DOMAIN STATE -->
                    <div class="glass-panel available-card">
                        <div class="available-domain-hero">
                            <div class="available-icon">✓</div>
                            <div>
                                <span class="comparison-kicker">Great News!</span>
                                <h2><?php echo esc($domainName); ?> is available</h2>
                                <p class="available-desc"><?php echo sprintf(__('domain_available_desc'), esc($domainName)); ?></p>
                            </div>
                        </div>

                        <?php if ($primaryDomainProvider): ?>
                            <div class="registration-cta-box available-primary-provider">
                                <div>
                                    <strong>Register with <?php echo esc($primaryDomainProvider['name']); ?></strong>
                                    <p class="cta-note"><?php echo __('register_note'); ?></p>
                                </div>
                                <a href="<?php echo url('go?to=' . urlencode($primaryDomainProvider['code']) . '&utm_source=available_domain&query=' . urlencode($domainName)); ?>" target="_blank" rel="noopener" class="btn btn-primary btn-large">
                                    Register Now
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php
                            $priceComparison = getDomainPriceComparison($domainName, $config);
                            $domainBase = preg_replace('/\.[a-z0-9.-]+$/i', '', $domainName);
                            $suggestedTlds = ['com', 'net', 'org', 'co', 'io', 'ai'];
                            $suggestedDomains = [];
                            foreach ($suggestedTlds as $suggestedTld) {
                                $candidate = $domainBase . '.' . $suggestedTld;
                                if ($candidate !== $domainName) {
                                    $suggestedDomains[] = $candidate;
                                }
                            }
                        ?>

                        <div class="price-comparison-widget comparison-tabs" data-comparison-tabs>
                            <div class="comparison-head">
                                <div>
                                    <h3><?php echo __('price_comparison_title'); ?></h3>
                                    <p><?php echo __('price_comparison_sub'); ?></p>
                                </div>
                            </div>

                            <div class="comparison-tab-list" role="tablist">
                                <button type="button" class="comparison-tab active" data-tab-target="domains">Domains</button>
                                <button type="button" class="comparison-tab" data-tab-target="hosting">Hosting</button>
                                <button type="button" class="comparison-tab" data-tab-target="ssl">SSL</button>
                                <button type="button" class="comparison-tab" data-tab-target="email">Email</button>
                            </div>

                            <div class="comparison-panel active" data-tab-panel="domains">
                                <div class="comparison-provider-list">
                                    <?php foreach ($priceComparison as $comp): ?>
                                        <a href="<?php echo esc($comp['aff_url']); ?>" target="_blank" rel="noopener" class="comparison-provider-row">
                                            <span class="comparison-provider-name">
                                                <?php echo getRegistrarLogo($comp['provider']); ?>
                                                <?php echo esc($comp['provider']); ?>
                                            </span>
                                            <span class="comparison-provider-desc"><?php echo esc($comp['description']); ?></span>
                                            <strong><?php echo esc($comp['price']); ?>/yr</strong>
                                            <span class="comparison-provider-action"><?php echo $comp['price'] === 'N/A' ? __('not_supported') : __('buy_now'); ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>

                                <?php if (!empty($suggestedDomains)): ?>
                                    <div class="suggested-domain-block">
                                        <div class="suggested-domain-head">
                                            <h4>Suggested Domains</h4>
                                            <span>Alternative extensions for the same name</span>
                                        </div>
                                        <div class="suggested-domain-list">
                                            <?php foreach ($suggestedDomains as $candidate): ?>
                                                <a href="<?php echo url('domain/' . urlencode($candidate)); ?>"><?php echo esc($candidate); ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
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
                                                <a href="<?php echo url('go?to=' . urlencode($provider['code']) . '&utm_source=price_comparison&query=' . urlencode($domainName)); ?>" target="_blank" rel="noopener" class="comparison-provider-row">
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

                <!-- Hosting Recommendation Card -->
                <div class="glass-panel affiliate-card">
                    <span class="affiliate-badge"><?php echo __('recommendation_badge'); ?></span>
                    <h3><?php echo __('hosting_recommendation_title'); ?></h3>
                    <p><?php echo __('hosting_recommendation_desc'); ?></p>
                    
                    <div class="affiliate-buttons">
                        <?php foreach ($recommendedHostingProviders as $provider): ?>
                            <a href="<?php echo url('go?to=' . urlencode($provider['code']) . '&utm_source=hosting_recommendation&query=' . urlencode($domainName)); ?>" target="_blank" rel="noopener" class="btn btn-secondary affiliate-btn">
                                <span><?php echo esc($provider['name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- SSL Recommendation Card -->
                <div class="glass-panel affiliate-card" style="margin-top: 1.5rem;">
                    <span class="affiliate-badge" style="background: linear-gradient(135deg, #10b981, #059669);"><?php echo __('recommendation_badge'); ?></span>
                    <h3><?php echo __('ssl_recommendation_title'); ?></h3>
                    <p><?php echo __('ssl_recommendation_desc'); ?></p>
                    
                    <div class="affiliate-buttons" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach ($recommendedSslProviders as $provider): ?>
                            <a href="<?php echo url('go?to=' . urlencode($provider['code']) . '&utm_source=ssl_recommendation&query=' . urlencode($domainName)); ?>" target="_blank" rel="noopener" class="btn btn-secondary affiliate-btn affiliate-btn-ssl">
                                <span><?php echo esc($provider['name']); ?></span>
                            </a>
                        <?php endforeach; ?>
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
