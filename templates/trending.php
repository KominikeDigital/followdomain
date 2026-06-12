<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

if (!function_exists('getTrendBadgeLabel')) {
    function getTrendBadgeLabel($index)
    {
        $labels = ['Hot', 'Watched', 'Trending'];
        return $labels[$index % count($labels)];
    }
}
?>

<div class="trending-page">
    <div class="page-header text-center">
        <h1 class="page-title"><?php echo __('trending_page_title'); ?></h1>
        <p class="page-subtitle"><?php echo __('trending_page_subtitle'); ?></p>
    </div>

    <?php if (empty($trendingDomains)): ?>
        <div class="glass-panel text-center" style="padding: 3rem;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: rgba(255,255,255,0.2); margin-bottom: 1rem;">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <h3><?php echo __('no_trending_domains'); ?></h3>
            <p><?php echo __('no_trending_domains_sub'); ?></p>
            <a href="/" class="btn btn-primary" style="margin-top: 1rem;"><?php echo __('btn_track_domain'); ?></a>
        </div>
    <?php else: ?>
        <div class="glass-panel table-responsive-container">
            <table class="trending-table">
                <thead>
                    <tr>
                        <th><?php echo __('col_domain_name'); ?></th>
                        <th><?php echo __('col_time_until'); ?></th>
                        <th class="hide-mobile"><?php echo __('col_expiry_date'); ?></th>
                        <th class="hide-mobile"><?php echo __('col_registrar'); ?></th>
                        <th class="text-center"><?php echo __('col_trend_status', 'Status'); ?></th>
                        <th class="text-right"><?php echo __('col_actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trendingDomains as $trendIndex => $dom): 
                        $cd = getCountdownDetails($dom['expiration_date']);
                    ?>
                        <tr class="table-row-hover">
                            <td class="domain-cell">
                                <span class="domain-icon-bullet"></span>
                                <span class="domain-name-txt"><?php echo esc($dom['domain_name']); ?></span>
                            </td>
                            <td>
                                <span class="countdown-badge <?php echo $cd['expired'] ? 'expired' : ''; ?>">
                                    <?php echo esc($cd['text']); ?>
                                </span>
                            </td>
                            <td class="hide-mobile table-date-txt">
                                <?php echo formatDate($dom['expiration_date'], 'd M Y'); ?>
                            </td>
                            <td class="hide-mobile table-registrar-txt">
                                <?php echo esc($dom['registrar']); ?>
                            </td>
                            <td class="text-center">
                                <span class="trend-label-badge"><?php echo esc(getTrendBadgeLabel($trendIndex)); ?></span>
                            </td>
                            <td class="text-right">
                                <a href="/domain/<?php echo urlencode($dom['domain_name']); ?>" class="btn btn-secondary btn-sm"><?php echo __('btn_details'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
