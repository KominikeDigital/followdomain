<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

global $pdo;

$userId = $_SESSION['user_id'];

// Check filters
$filterFav = isset($_GET['filter']) && $_GET['filter'] === 'favorites';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'expiry'; // 'expiry' or 'name'

// Build SQL
$sql = "
    SELECT ud.*, d.expiration_date, d.registration_date, d.registrar, d.status, d.nameservers, d.last_checked
    FROM user_domains ud
    LEFT JOIN domains d ON ud.domain_name = d.domain_name
    WHERE ud.user_id = ?
";
$params = [$userId];

if ($filterFav) {
    $sql .= " AND ud.is_favorite = 1";
}

if (!empty($searchQuery)) {
    $sql .= " AND ud.domain_name LIKE ?";
    $params[] = '%' . $searchQuery . '%';
}

if ($sortBy === 'name') {
    $sql .= " ORDER BY ud.domain_name ASC";
} else {
    $sql .= " ORDER BY (d.expiration_date IS NULL) ASC, d.expiration_date ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$myDomains = $stmt->fetchAll();

$totalDomainCount = getUserDomainCount($pdo, $userId);
$domainCount = $totalDomainCount;
$userPlan = getUserPlan($pdo, $userId);
$domainLimitStatus = getUserDomainLimitStatus($pdo, $userId, 1);
$canAddDomain = $domainLimitStatus['allowed'];
$canExportCsv = userPlanAllows($userPlan, 'csv_export');
?>

<div class="user-shell">
    <?php require_once __DIR__ . '/user_sidebar.php'; ?>
    <main class="user-main">
        <div class="domains-list-page">
    
    <?php if (isset($_SESSION['export_error'])): ?>
        <div class="alert alert-error" style="margin-top: 1rem; margin-bottom: 1.5rem;">
            <?php 
            echo esc($_SESSION['export_error']); 
            unset($_SESSION['export_error']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['domain_msg'])): ?>
        <div class="alert alert-success" style="margin-top: 1rem; margin-bottom: 1.5rem;">
            <?php
            echo esc($_SESSION['domain_msg']);
            unset($_SESSION['domain_msg']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['domain_error'])): ?>
        <div class="alert alert-error" style="margin-top: 1rem; margin-bottom: 1.5rem;">
            <?php
            echo esc($_SESSION['domain_error']);
            unset($_SESSION['domain_error']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (!$canAddDomain): ?>
        <div class="alert alert-error" style="margin-top: 1rem; margin-bottom: 1.5rem;">
            <?php echo esc($domainLimitStatus['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Header Block -->
    <div class="dashboard-greet-header">
        <div>
            <h1 class="greet-title"><?php echo __('my_domains_title'); ?></h1>
            <p class="greet-subtitle"><?php echo sprintf(__('my_domains_sub'), $domainCount); ?></p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if ($canExportCsv): ?>
                <a href="<?php echo url('panel/domains/export'); ?>" class="btn btn-secondary"><?php echo __('export_csv'); ?></a>
            <?php else: ?>
                <a href="<?php echo url('panel/integrations'); ?>" class="btn btn-secondary" title="CSV dışa aktarma premium paketlerde açıktır."><?php echo __('export_csv'); ?></a>
            <?php endif; ?>
            <button class="btn btn-primary" <?php echo $canAddDomain ? 'onclick="openAddDomainModal()"' : 'disabled'; ?>><?php echo __('add_domain_btn'); ?></button>
        </div>
    </div>

    <!-- Filter & Controls Toolbar -->
    <div class="toolbar-controls glass-panel">
        
        <!-- Search -->
        <div class="search-input-wrapper">
            <span class="search-icon">🔍</span>
            <input type="text" id="panelDomainSearch" placeholder="<?php echo esc(__('search_domains_placeholder')); ?>" value="<?php echo esc($searchQuery); ?>" onkeyup="handleSearch(event)">
        </div>
        
        <!-- Action Options -->
        <div class="toolbar-options">
            
            <!-- Favorites toggle -->
            <a href="<?php echo $filterFav ? url('panel/domains') : url('panel/domains?filter=favorites'); ?>" class="btn btn-secondary btn-sm <?php echo $filterFav ? 'active-filter' : ''; ?>">
                <?php echo __('favorites_filter'); ?>
            </a>
            
            <!-- Preview screen toggle (handled client side via main.js) -->
            <button class="btn btn-secondary btn-sm" id="btnTogglePreviews" onclick="togglePreviews()"
                    data-text-on="<?php echo esc(__('toggle_previews_on')); ?>"
                    data-text-off="<?php echo esc(__('toggle_previews_off')); ?>">
                <?php echo __('toggle_previews_on'); ?>
            </button>
            
            <!-- Sort selector -->
            <div class="sort-selector-wrapper">
                <label for="sortSelector"><?php echo __('sort_label'); ?></label>
                <select id="sortSelector" onchange="handleSortChange(this)">
                    <option value="expiry" <?php echo ($sortBy === 'expiry') ? 'selected' : ''; ?>><?php echo __('sort_expiry'); ?></option>
                    <option value="name" <?php echo ($sortBy === 'name') ? 'selected' : ''; ?>><?php echo __('sort_name'); ?></option>
                </select>
            </div>
            
            <!-- Layout toggle -->
            <div class="layout-toggle-btns">
                <button class="layout-btn active" id="btnLayoutGrid" onclick="changeLayout('grid')" aria-label="Izgara Görünümü">⊞</button>
                <button class="layout-btn" id="btnLayoutList" onclick="changeLayout('list')" aria-label="Liste Görünümü">☰</button>
            </div>
        </div>
    </div>

    <!-- Grid / List Container -->
    <?php if (empty($myDomains)): ?>
        <div class="glass-panel text-center" style="padding: 4rem 2rem; margin-top: 2rem;">
            <h3><?php echo __('no_domains_found'); ?></h3>
            <p class="text-muted" style="margin-top: 0.5rem;"><?php echo __('no_domains_found_sub'); ?></p>
            <button class="btn btn-primary" style="margin-top: 1.5rem;" <?php echo $canAddDomain ? 'onclick="openAddDomainModal()"' : 'disabled'; ?>><?php echo __('add_first_domain'); ?></button>
        </div>
    <?php else: ?>
        <div class="domains-cards-grid" id="domainsCardsContainer">
            <?php foreach ($myDomains as $item): 
                $domName = $item['domain_name'];
                $cd = getCountdownDetails($item['expiration_date']);
                
                // Calculate age of domain
                $age = 'N/A';
                if ($item['registration_date']) {
                    $regYear = date('Y', strtotime($item['registration_date']));
                    $currentYear = date('Y');
                    $ageVal = $currentYear - $regYear;
                    $age = sprintf(__('years_old'), $ageVal);
                }
                
                // Screenshot URL using Microlink free API
                $screenshotUrl = "https://api.microlink.io/?url=https://" . urlencode($domName) . "&screenshot=true&embed=screenshot.url";
            ?>
                <!-- Individual Domain Card -->
                <div class="domain-info-card glass-panel" data-domain="<?php echo esc($domName); ?>">
                    
                    <!-- Card Top Screenshot (Hideable) -->
                    <div class="card-screenshot-frame">
                        <img src="<?php echo $screenshotUrl; ?>" alt="<?php echo esc($domName); ?>" loading="lazy" onerror="this.src='data:image/svg+xml;charset=utf-8,%3Csvg xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22 width%3D%22400%22 height%3D%22250%22 viewBox%3D%220 0 400 250%22%3E%3Crect width%3D%22100%25%22 height%3D%22100%25%22 fill%3D%22%23111827%22%2F%3E%3Ctext x%3D%2250%25%22 y%3D%2250%25%22 fill%3D%22%234B5563%22 font-family%3D%22sans-serif%22 font-size%3D%2216%22 text-anchor%3D%22middle%22%3E' . esc($domName) . '%3C%2Ftext%3E%3C%2Fsvg%3E'">
                    </div>

                    <!-- Card Body -->
                    <div class="card-body-content">
                        
                        <!-- Domain Title & Actions -->
                        <div class="card-title-row">
                            <h4 class="card-domain-title"><?php echo esc($domName); ?></h4>
                            <div class="card-action-icons">
                                <!-- Star Button -->
                                <button type="button" class="action-icon-btn fav-btn <?php echo $item['is_favorite'] ? 'active' : ''; ?>" onclick="toggleFavorite('<?php echo esc($domName); ?>', this)" title="<?php echo esc(__('favorites')); ?>">★</button>
                                <!-- Alert Bell Config -->
                                <button type="button" class="action-icon-btn bell-btn" onclick="openAlertConfig('<?php echo esc($domName); ?>', <?php echo (int)$item['notify_60']; ?>, <?php echo (int)$item['notify_30']; ?>, <?php echo (int)$item['notify_14']; ?>, <?php echo (int)$item['notify_7']; ?>, <?php echo (int)$item['notify_3']; ?>, <?php echo (int)$item['notify_1']; ?>)" title="<?php echo esc(__('action_alerts')); ?>">🔔</button>
                                <!-- Delete Button -->
                                <button type="button" class="action-icon-btn delete-btn" onclick="deleteDomain('<?php echo esc($domName); ?>', '<?php echo esc(sprintf(__('action_unfollow_confirm'), $domName)); ?>')" title="<?php echo esc(__('action_unfollow')); ?>">🗑</button>
                            </div>
                        </div>

                        <!-- Days remaining -->
                        <div class="card-countdown-section">
                            <?php if ($cd['expired']): ?>
                                <span class="days-remaining-num expired">
                                    <?php echo __('days_left_expired'); ?>
                                </span>
                            <?php else: ?>
                                <span class="days-remaining-num">
                                    <?php echo esc($cd['days']); ?>
                                </span>
                                <span class="days-remaining-lbl">
                                    <?php echo ($cd['days'] == 1) ? __('time_day') : __('time_days'); ?>
                                </span>
                                <span class="days-remaining-lbl"><?php echo __('days_left_suffix'); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Expiry text and age -->
                        <p class="card-meta-desc">
                            <?php echo __('expires_on'); ?> <?php echo formatDate($item['expiration_date'], 'd M Y'); ?>
                            <?php if ($item['registration_date']): ?>
                                <span class="meta-dot">•</span> <?php echo esc($age); ?>
                            <?php endif; ?>
                        </p>

                        <!-- Transfer badge -->
                        <div class="card-badge-row">
                            <span class="card-badge"><?php echo __('badge_transfers'); ?></span>
                        </div>

                        <!-- Card Footer Registrar -->
                        <div class="card-footer-info">
                            <?php echo __('registrar'); ?> <strong><?php echo esc($item['registrar']); ?></strong>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Alert Configurations Subdialog (Bell Click Modal) -->
<dialog id="alertConfigDialog" class="glass-modal inline-dialog">
    <div class="modal-container">
        <div class="modal-header">
            <h3><?php echo __('alert_config_title'); ?></h3>
            <button class="modal-close-btn" onclick="closeAlertConfig()">×</button>
        </div>
        <p class="modal-subtitle" id="configDialogDomainName">domain.com</p>
        
        <form action="<?php echo url('panel/domains'); ?>" method="POST" id="alertConfigForm">
            <input type="hidden" name="action" value="update_alerts">
            <input type="hidden" name="domain_name" id="configAlertDomainInput">
            
            <div class="form-group" style="margin-top: 1.5rem;">
                <label><?php echo __('alert_config_sub'); ?></label>
                <div class="alert-tags-container" id="configDialogTags">
                    <button type="button" class="alert-tag-btn" data-val="60" onclick="toggleAlertTag(this)">60d</button>
                    <button type="button" class="alert-tag-btn" data-val="30" onclick="toggleAlertTag(this)">30d</button>
                    <button type="button" class="alert-tag-btn" data-val="14" onclick="toggleAlertTag(this)">14d</button>
                    <button type="button" class="alert-tag-btn" data-val="7" onclick="toggleAlertTag(this)">7d</button>
                    <button type="button" class="alert-tag-btn" data-val="3" onclick="toggleAlertTag(this)">3d</button>
                    <button type="button" class="alert-tag-btn" data-val="1" onclick="toggleAlertTag(this)">1d</button>
                </div>
                
                <input type="hidden" name="alerts[60]" id="config_alert_val_60" value="0">
                <input type="hidden" name="alerts[30]" id="config_alert_val_30" value="0">
                <input type="hidden" name="alerts[14]" id="config_alert_val_14" value="0">
                <input type="hidden" name="alerts[7]" id="config_alert_val_7" value="0">
                <input type="hidden" name="alerts[3]" id="config_alert_val_3" value="0">
                <input type="hidden" name="alerts[1]" id="config_alert_val_1" value="0">
            </div>

            <div class="modal-actions" style="margin-top: 2rem;">
                <button type="button" class="btn btn-secondary" onclick="closeAlertConfig()"><?php echo __('btn_close'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo __('btn_save'); ?></button>
            </div>
        </form>
        </div>
    </main>
</div>

</dialog>

<!-- Common Add Domain Modal -->
<?php require_once __DIR__ . '/modal_add_domain.php'; ?>
