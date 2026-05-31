<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

global $pdo;

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch plan details
$stmtUser = $pdo->prepare("SELECT api_plan FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$userPlan = $stmtUser->fetchColumn();
if (!$userPlan) $userPlan = 'free';

$planLimits = [
    'free' => 5,
    'bronze' => 50,
    'silver' => 500,
    'gold' => PHP_INT_MAX
];
$currentLimit = $planLimits[$userPlan] ?? 5;
$limitDisplay = ($currentLimit === PHP_INT_MAX) ? __('unlimited', 'Sınırsız') : $currentLimit;

// Calculate Stats
// 1. Total domains
$totalTracked = $pdo->query("SELECT COUNT(*) FROM user_domains WHERE user_id = $userId")->fetchColumn();
$overLimit = ($totalTracked >= $currentLimit);

// Fetch all tracked domains
$stmtAllDomains = $pdo->prepare("
    SELECT ud.*, d.expiration_date, d.registrar, d.last_checked
    FROM user_domains ud
    JOIN domains d ON ud.domain_name = d.domain_name
    WHERE ud.user_id = ?
    ORDER BY d.expiration_date ASC
");
$stmtAllDomains->execute([$userId]);
$allDomainsList = $stmtAllDomains->fetchAll();

// 2. Expiring in 30 days
$dbType = $config['db_type'] ?? 'sqlite';
if ($dbType === 'sqlite') {
    $expiring30 = $pdo->query("
        SELECT COUNT(*) FROM user_domains ud
        JOIN domains d ON ud.domain_name = d.domain_name
        WHERE ud.user_id = $userId 
          AND d.expiration_date IS NOT NULL 
          AND (strftime('%s', d.expiration_date) - strftime('%s', 'now')) / 86400 <= 30
          AND (strftime('%s', d.expiration_date) - strftime('%s', 'now')) > 0
    ")->fetchColumn();
} else {
    $expiring30 = $pdo->query("
        SELECT COUNT(*) FROM user_domains ud
        JOIN domains d ON ud.domain_name = d.domain_name
        WHERE ud.user_id = $userId 
          AND d.expiration_date IS NOT NULL 
          AND (UNIX_TIMESTAMP(d.expiration_date) - UNIX_TIMESTAMP(NOW())) / 86400 <= 30
          AND (UNIX_TIMESTAMP(d.expiration_date) - UNIX_TIMESTAMP(NOW())) > 0
    ")->fetchColumn();
}

// 3. Favorites
$totalFavorites = $pdo->query("SELECT COUNT(*) FROM user_domains WHERE user_id = $userId AND is_favorite = 1")->fetchColumn();

// 4. Integrations count
$totalIntegrations = $pdo->query("SELECT COUNT(*) FROM integrations WHERE user_id = $userId")->fetchColumn();

// Expiring soon domains list
if ($dbType === 'sqlite') {
    $stmtExp = $pdo->prepare("
        SELECT ud.*, d.expiration_date 
        FROM user_domains ud
        JOIN domains d ON ud.domain_name = d.domain_name
        WHERE ud.user_id = ? 
          AND d.expiration_date IS NOT NULL
          AND (strftime('%s', d.expiration_date) - strftime('%s', 'now')) > 0
        ORDER BY d.expiration_date ASC 
        LIMIT 5
    ");
} else {
    $stmtExp = $pdo->prepare("
        SELECT ud.*, d.expiration_date 
        FROM user_domains ud
        JOIN domains d ON ud.domain_name = d.domain_name
        WHERE ud.user_id = ? 
          AND d.expiration_date IS NOT NULL
          AND (UNIX_TIMESTAMP(d.expiration_date) - UNIX_TIMESTAMP(NOW())) > 0
        ORDER BY d.expiration_date ASC 
        LIMIT 5
    ");
}
$stmtExp->execute([$userId]);
$expiringSoon = $stmtExp->fetchAll();

// Favorites list
$stmtFavs = $pdo->prepare("
    SELECT ud.*, d.expiration_date 
    FROM user_domains ud
    JOIN domains d ON ud.domain_name = d.domain_name
    WHERE ud.user_id = ? AND ud.is_favorite = 1
    ORDER BY d.expiration_date ASC
    LIMIT 5
");
$stmtFavs->execute([$userId]);
$favoriteDomains = $stmtFavs->fetchAll();

// Integrations connected
$stmtInt = $pdo->prepare("SELECT * FROM integrations WHERE user_id = ?");
$stmtInt->execute([$userId]);
$integrations = $stmtInt->fetchAll();

// Recent Activities
$stmtAct = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmtAct->execute([$userId]);
$recentActivities = $stmtAct->fetchAll();

?>

<div class="user-dashboard">
    
    <!-- Greeting & Action Header -->
    <div class="dashboard-greet-header">
        <div>
            <h1 class="greet-title"><?php echo sprintf(__('welcome_back'), esc($username)); ?></h1>
            <p class="greet-subtitle"><?php echo __('dashboard_sub'); ?></p>
        </div>
        <div>
            <!-- Button opening modal -->
            <button class="btn btn-primary" onclick="openAddDomainModal()"><?php echo __('add_domain_btn'); ?></button>
        </div>
    </div>

    <!-- Active Plan Information Banner -->
    <div class="glass-panel" style="padding: 1rem 1.5rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; border-left: 4px solid var(--color-primary); flex-wrap: wrap; gap: 1rem;">
        <span style="font-size: 0.95rem; color: #ffffff;">
            <?php echo sprintf(__('plan_info_banner'), strtoupper(esc($userPlan)), (int)$totalTracked, esc($limitDisplay)); ?>
        </span>
        <a href="<?php echo url('panel/integrations#pricing'); ?>" class="btn btn-secondary btn-sm" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; text-decoration: none;">
            <?php echo __('plan_info_upgrade_btn'); ?>
        </a>
    </div>

    <?php if ($overLimit): ?>
        <!-- Freemium Conversion Funnel — Limit Dolduğunda Çift Gelir CTA -->
        <div class="glass-panel" style="margin-bottom: 1.5rem; padding: 1.75rem; border-left: 4px solid var(--color-warning); background: rgba(245, 158, 11, 0.04);">
            <div style="display: flex; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
                <span style="font-size: 2rem; flex-shrink: 0;">🚀</span>
                <div style="flex: 1; min-width: 240px;">
                    <h3 style="font-family: var(--font-display); font-size: 1.1rem; color: var(--color-warning); margin-bottom: 0.4rem;">
                        <?php echo $currentLimit; ?> domain limitine ulaştınız!
                    </h3>
                    <p style="font-size: 0.88rem; color: var(--color-text-secondary); margin-bottom: 1.25rem; line-height: 1.6;">
                        Daha fazla domain takip etmek için iki seçeneğiniz var:
                        <strong style="color: var(--color-text-primary);">planınızı yükseltin</strong> veya
                        <strong style="color: var(--color-text-primary);">Namecheap'te direkt yönetin</strong>.
                        Her iki yol da sizi korur — seçim sizin!
                    </p>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <a href="<?php echo url('panel/integrations#pricing'); ?>" class="btn btn-primary btn-sm"
                           style="font-size: 0.85rem; padding: 0.5rem 1.25rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                            👑 Planı Yükselt
                        </a>
                        <a href="<?php echo url('go?to=namecheap&utm_source=limit_cta'); ?>" target="_blank" rel="noopener"
                           class="btn btn-secondary btn-sm"
                           style="font-size: 0.85rem; padding: 0.5rem 1.25rem; border-color: rgba(222,55,33,0.4); color: #fc9183; display: inline-flex; align-items: center; gap: 0.4rem;">
                            🏷️ Namecheap'te Yönet
                        </a>
                        <a href="<?php echo url('go?to=godaddy&utm_source=limit_cta'); ?>" target="_blank" rel="noopener"
                           class="btn btn-secondary btn-sm"
                           style="font-size: 0.85rem; padding: 0.5rem 1.25rem; border-color: rgba(0,130,138,0.4); color: #6ee7e7; display: inline-flex; align-items: center; gap: 0.4rem;">
                            🌐 GoDaddy
                        </a>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 12px; padding: 1rem 1.5rem; text-align: center; min-width: 100px; flex-shrink: 0;">
                    <span style="font-size: 1.75rem; font-weight: 800; color: var(--color-warning); font-family: var(--font-display);"><?php echo (int)$totalTracked; ?>/<?php echo (int)$currentLimit; ?></span>
                    <span style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.2rem;">Domain</span>
                </div>
            </div>
        </div>
    <?php endif; ?>


    <!-- Stats Matrix (4 columns) -->
    <div class="dashboard-stats-grid">
        <div class="panel-stat-card glass-panel">
            <span class="stat-num"><?php echo (int)$totalTracked; ?></span>
            <span class="stat-label"><?php echo __('stat_followed_domains'); ?></span>
        </div>
        <div class="panel-stat-card glass-panel <?php echo ($expiring30 > 0) ? 'expiring-warn' : ''; ?>">
            <span class="stat-num"><?php echo (int)$expiring30; ?></span>
            <span class="stat-label"><?php echo __('stat_expiring_30'); ?></span>
        </div>
        <div class="panel-stat-card glass-panel">
            <span class="stat-num"><?php echo (int)$totalFavorites; ?></span>
            <span class="stat-label"><?php echo __('stat_favorites'); ?></span>
        </div>
        <div class="panel-stat-card glass-panel">
            <span class="stat-num"><?php echo (int)$totalIntegrations; ?></span>
            <span class="stat-label"><?php echo __('stat_integrations'); ?></span>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="dashboard-main-grid">
        
        <!-- Left: Expiring list -->
        <div class="main-column">
            <div class="glass-panel content-list-card">
                <div class="card-header-flex">
                    <h3><?php echo __('expiring_soon'); ?></h3>
                    <a href="<?php echo url('panel/domains'); ?>" class="header-link-btn"><?php echo __('view_all'); ?></a>
                </div>
                
                <div class="list-body">
                    <?php if (empty($expiringSoon)): ?>
                        <p class="empty-list-notice"><?php echo __('no_expiring_soon'); ?></p>
                    <?php else: ?>
                        <div class="expiring-list-items">
                            <?php foreach ($expiringSoon as $item): 
                                $cd = getCountdownDetails($item['expiration_date']);
                            ?>
                                <a href="<?php echo url('domain/' . urlencode($item['domain_name'])); ?>" class="expiring-list-item">
                                    <span class="item-name"><?php echo esc($item['domain_name']); ?></span>
                                    <span class="item-countdown <?php echo $cd['expired'] ? 'expired' : ''; ?>">
                                        <?php echo esc($cd['text']); ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Integrations and Favorites -->
        <div class="sidebar-column">
            
            <!-- Cloudflare Card -->
            <div class="glass-panel content-list-card">
                <div class="card-header-flex">
                    <h3><?php echo __('your_integrations'); ?></h3>
                    <a href="<?php echo url('panel/integrations'); ?>" class="header-link-btn"><?php echo __('manage_link'); ?></a>
                </div>
                <div class="list-body">
                    <div class="integration-status-item">
                        <div class="integration-icon-label">
                            <span class="cf-logo-icon">☁</span>
                            <div class="int-info">
                                <strong>Cloudflare</strong>
                                <span class="int-status-lbl">
                                    <?php echo (!empty($integrations)) ? __('connected') : __('not_connected'); ?>
                                </span>
                            </div>
                        </div>
                        <?php if (empty($integrations)): ?>
                            <a href="<?php echo url('panel/integrations'); ?>" class="btn btn-secondary btn-sm"><?php echo __('connect'); ?></a>
                        <?php endif; ?>
                    </div>
                    <p class="int-description"><?php echo __('cloudflare_integration_desc'); ?></p>
                </div>
            </div>

            <!-- Favorites Card -->
            <div class="glass-panel content-list-card" style="margin-top: 1.5rem;">
                <div class="card-header-flex">
                    <h3><?php echo __('favorites'); ?></h3>
                    <a href="<?php echo url('panel/domains?filter=favorites'); ?>" class="header-link-btn"><?php echo __('view_all'); ?></a>
                </div>
                <div class="list-body">
                    <?php if (empty($favoriteDomains)): ?>
                        <p class="empty-list-notice"><?php echo __('no_favorites'); ?></p>
                    <?php else: ?>
                        <div class="favorites-list-items">
                            <?php foreach ($favoriteDomains as $fav): 
                                $cd = getCountdownDetails($fav['expiration_date']);
                            ?>
                                <div class="favorite-item">
                                    <span class="fav-star-icon">★</span>
                                    <a href="<?php echo url('domain/' . urlencode($fav['domain_name'])); ?>" class="fav-name"><?php echo esc($fav['domain_name']); ?></a>
                                    <span class="fav-days"><?php echo sprintf(__('days_left'), $cd['days']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

    <!-- Bottom: Activity Log -->
    <!-- All Tracked Domains List (New Widget) -->
    <div class="glass-panel content-list-card" style="margin-top: 2rem;">
        <div class="card-header-flex" style="border-bottom: 1px solid var(--color-border); padding-bottom: 1rem; margin-bottom: 1rem;">
            <h3>🔍 <?php echo __('nav_domains', 'Tüm Takip Edilen Alan Adları'); ?> (<?php echo count($allDomainsList); ?>)</h3>
            <a href="<?php echo url('panel/domains'); ?>" class="header-link-btn"><?php echo __('view_all'); ?></a>
        </div>
        
        <div class="table-responsive-container" style="max-height: 350px; overflow-y: auto;">
            <?php if (empty($allDomainsList)): ?>
                <p class="empty-list-notice" style="text-align: center; padding: 2rem;"><?php echo __('no_domains_found'); ?></p>
            <?php else: ?>
                <table class="trending-table" style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--color-border); font-size: 0.85rem; color: var(--color-text-secondary);">
                            <th style="padding: 0.75rem;"><?php echo __('col_domain_name'); ?></th>
                            <th style="padding: 0.75rem;"><?php echo __('col_time_until'); ?></th>
                            <th style="padding: 0.75rem;" class="hide-mobile"><?php echo __('col_registrar'); ?></th>
                            <th style="padding: 0.75rem;" class="hide-mobile"><?php echo __('col_expiry_date'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allDomainsList as $row): 
                            $cd = getCountdownDetails($row['expiration_date']);
                        ?>
                            <tr class="table-row-hover" style="border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 0.9rem;">
                                <td style="padding: 0.75rem; font-weight: 600; color: #ffffff;">
                                    <a href="<?php echo url('domain/' . urlencode($row['domain_name'])); ?>" style="color: inherit; text-decoration: none;">
                                        <?php echo esc($row['domain_name']); ?>
                                    </a>
                                    <?php if ($row['is_favorite'] == 1): ?>
                                        <span style="color: var(--color-primary); margin-left: 0.25rem;">★</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.75rem;">
                                    <span class="countdown-badge <?php echo $cd['expired'] ? 'expired' : ''; ?>" style="font-size: 0.75rem; padding: 2px 6px;">
                                        <?php echo esc($cd['text']); ?>
                                    </span>
                                </td>
                                <td style="padding: 0.75rem;" class="hide-mobile text-muted"><?php echo esc($row['registrar']); ?></td>
                                <td style="padding: 0.75rem;" class="hide-mobile text-muted"><?php echo formatDate($row['expiration_date'], 'd M Y'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="glass-panel activity-log-card" style="margin-top: 2rem;">
        <h3><?php echo __('activity_log'); ?></h3>
        <p class="activity-subtitle"><?php echo __('activity_log_sub'); ?></p>
        
        <div class="activity-timeline-list">
            <?php if (empty($recentActivities)): ?>
                <p class="empty-list-notice"><?php echo __('no_activity_log'); ?></p>
            <?php else: 
                // Time elapsed helper
                $timeElapsed = function($dateStr) {
                    $diff = time() - strtotime($dateStr);
                    if ($diff < 60) return __('time_just_now');
                    if ($diff < 3600) return sprintf(__('time_minutes_ago'), floor($diff/60));
                    if ($diff < 86400) return sprintf(__('time_hours_ago'), floor($diff/3600));
                    return sprintf(__('time_days_ago'), floor($diff/86400));
                };
            ?>
                <?php foreach ($recentActivities as $act): ?>
                    <div class="activity-item">
                        <span class="activity-bullet"></span>
                        <p class="activity-text">
                            <strong><?php echo esc($act['action']); ?></strong>
                            <span class="activity-time"><?php echo $timeElapsed($act['created_at']); ?></span>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Include Common Add Domain Modal -->
<?php require_once __DIR__ . '/modal_add_domain.php'; ?>
