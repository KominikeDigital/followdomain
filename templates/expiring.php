<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

global $pdo, $config;

$dbType = $config['db_type'] ?? 'sqlite';

try {
    if ($dbType === 'sqlite') {
        // Today
        $stmtToday = $pdo->query("
            SELECT * FROM domains 
            WHERE date(expiration_date) = date('now') 
            ORDER BY follower_count DESC, domain_name ASC
        ");
        // 7 Days
        $stmt7 = $pdo->query("
            SELECT * FROM domains 
            WHERE date(expiration_date) <= date('now', '+7 days') 
              AND date(expiration_date) > date('now') 
            ORDER BY expiration_date ASC, follower_count DESC
        ");
        // 30 Days
        $stmt30 = $pdo->query("
            SELECT * FROM domains 
            WHERE date(expiration_date) <= date('now', '+30 days') 
              AND date(expiration_date) > date('now') 
            ORDER BY expiration_date ASC, follower_count DESC
        ");
    } else {
        // Today
        $stmtToday = $pdo->query("
            SELECT * FROM domains 
            WHERE DATE(expiration_date) = CURDATE() 
            ORDER BY follower_count DESC, domain_name ASC
        ");
        // 7 Days
        $stmt7 = $pdo->query("
            SELECT * FROM domains 
            WHERE DATE(expiration_date) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
              AND DATE(expiration_date) > CURDATE() 
            ORDER BY expiration_date ASC, follower_count DESC
        ");
        // 30 Days
        $stmt30 = $pdo->query("
            SELECT * FROM domains 
            WHERE DATE(expiration_date) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
              AND DATE(expiration_date) > CURDATE() 
            ORDER BY expiration_date ASC, follower_count DESC
        ");
    }

    $domainsToday = $stmtToday ? $stmtToday->fetchAll() : [];
    $domains7 = $stmt7 ? $stmt7->fetchAll() : [];
    $domains30 = $stmt30 ? $stmt30->fetchAll() : [];

} catch (Exception $e) {
    $domainsToday = [];
    $domains7 = [];
    $domains30 = [];
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'today';
if (!in_array($tab, ['today', '7days', '30days'])) {
    $tab = 'today';
}

$activeList = $domainsToday;
$listTitle = __('seo_list_title_today');
if ($tab === '7days') {
    $activeList = $domains7;
    $listTitle = __('seo_list_title_7days');
} elseif ($tab === '30days') {
    $activeList = $domains30;
    $listTitle = __('seo_list_title_30days');
}

$activeCount = count($activeList);
?>

<div class="user-shell">
    <?php require_once __DIR__ . '/user_sidebar.php'; ?>
    <main class="user-main">
        <div class="expiring-domains-page">
    <div class="page-header text-center">
        <h1 class="page-title"><?php echo __('seo_title'); ?></h1>
        <p class="page-subtitle"><?php echo __('seo_sub'); ?></p>
    </div>

    <!-- SEO Category Tabs -->
    <div class="auth-tabs" style="max-width: 600px; margin: 2rem auto;">
        <a href="<?php echo url('expiring?tab=today'); ?>" class="auth-tab <?php echo ($tab === 'today') ? 'active' : ''; ?>">
            <?php echo sprintf(__('tab_today'), count($domainsToday)); ?>
        </a>
        <a href="<?php echo url('expiring?tab=7days'); ?>" class="auth-tab <?php echo ($tab === '7days') ? 'active' : ''; ?>">
            <?php echo sprintf(__('tab_7days'), count($domains7)); ?>
        </a>
        <a href="<?php echo url('expiring?tab=30days'); ?>" class="auth-tab <?php echo ($tab === '30days') ? 'active' : ''; ?>">
            <?php echo sprintf(__('tab_30days'), count($domains30)); ?>
        </a>
    </div>

    <!-- Listing Table -->
    <div class="glass-panel" style="margin-top: 2rem;">
        <div class="card-header-block" style="margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
            <h2 style="font-family: var(--font-display); font-size: 1.35rem; color: #ffffff; margin: 0;"><?php echo $listTitle; ?></h2>
            <div class="search-input-wrapper" style="width: 100%; max-width: 400px; margin-top: 0.25rem;">
                <span class="search-icon">🔍</span>
                <input type="text" id="expiringSearch" placeholder="<?php echo esc(__('search_list_placeholder')); ?>" onkeyup="filterExpiringList(event)">
            </div>
        </div>

        <?php if ($activeCount === 0): ?>
            <div class="text-center" style="padding: 3rem 1rem;">
                <p class="text-muted"><?php echo __('no_expiring_in_range'); ?></p>
            </div>
        <?php else: ?>
            <div class="table-responsive-container">
                <table class="trending-table" id="expiringTable">
                    <thead>
                        <tr>
                            <th><?php echo __('col_domain_name'); ?></th>
                            <th><?php echo __('col_time_until'); ?></th>
                            <th class="hide-mobile"><?php echo __('col_registrar'); ?></th>
                            <th class="hide-mobile"><?php echo __('col_expiry_date'); ?></th>
                            <th class="text-right"><?php echo __('col_status'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeList as $row): 
                            $cd = getCountdownDetails($row['expiration_date']);
                        ?>
                            <tr class="table-row-hover" data-domain="<?php echo esc($row['domain_name']); ?>">
                                <td class="domain-cell">
                                    <span class="domain-icon-bullet" style="background-color: var(--color-primary);"></span>
                                    <a href="<?php echo url('domain/' . urlencode($row['domain_name'])); ?>" style="font-weight: 600; color: #ffffff;">
                                        <?php echo esc($row['domain_name']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="countdown-badge <?php echo $cd['expired'] ? 'expired' : ''; ?>">
                                        <?php echo esc($cd['text']); ?>
                                    </span>
                                </td>
                                <td class="hide-mobile"><?php echo esc($row['registrar']); ?></td>
                                <td class="hide-mobile table-date-txt"><?php echo formatDate($row['expiration_date'], 'd M Y'); ?></td>
                                <td class="text-right">
                                    <a href="<?php echo url('domain/' . urlencode($row['domain_name'])); ?>" class="btn btn-secondary btn-sm">
                                        <?php echo __('btn_examine_track'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        </div>
    </main>
</div>

<script>
function filterExpiringList(event) {
    const query = event.target.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#expiringTable tbody tr');
    
    rows.forEach(row => {
        const domain = row.getAttribute('data-domain').toLowerCase();
        if (domain.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
