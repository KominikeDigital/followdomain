<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

$isUser = isLoggedIn();
$planHref = function ($plan) use ($isUser) {
    if ($plan === 'free') {
        return $isUser ? url('panel') : url('register?plan=free');
    }
    return $isUser ? url('checkout?plan=' . urlencode($plan)) : url('register?plan=' . urlencode($plan));
};

$premiumPlans = [
    'free' => ['class' => 'free-tier', 'badge' => __('plan_badge_free'), 'feature_count' => 9, 'cta' => __('home_plan_cta_free'), 'highlight' => false],
    'bronze' => ['class' => 'bronze-tier', 'badge' => __('plan_badge_bronze'), 'feature_count' => 10, 'cta' => __('home_plan_cta_paid'), 'highlight' => true],
    'silver' => ['class' => 'silver-tier', 'badge' => __('plan_badge_silver'), 'feature_count' => 12, 'cta' => __('home_plan_cta_paid'), 'highlight' => false],
    'agency' => ['class' => 'agency-tier', 'badge' => __('plan_badge_agency'), 'feature_count' => 18, 'cta' => __('home_plan_cta_paid'), 'highlight' => false],
];
?>

<section class="home-pricing-section" id="premium">
    <div class="section-header">
        <span class="premium-section-kicker">PREMIUM</span>
        <h2 class="section-title"><?php echo __('home_pricing_title'); ?></h2>
        <p class="section-subtitle"><?php echo __('home_pricing_subtitle'); ?></p>
    </div>

    <div class="home-pricing-grid">
        <?php foreach ($premiumPlans as $planKey => $plan): ?>
            <a href="<?php echo esc($planHref($planKey)); ?>" class="home-plan-card pricing-card <?php echo esc($plan['class']); ?> <?php echo $plan['highlight'] ? 'featured' : ''; ?>">
                <?php if ($plan['highlight']): ?>
                    <span class="pricing-popular-tag"><?php echo __('plan_popular'); ?></span>
                <?php endif; ?>

                <div class="pricing-card-header">
                    <span class="pricing-badge"><?php echo esc($plan['badge']); ?></span>
                    <h3 class="pricing-plan-name"><?php echo __('plan_' . $planKey . '_name'); ?></h3>
                    <div class="pricing-price">
                        <?php echo __('plan_' . $planKey . '_price'); ?>
                        <span class="pricing-period"><?php echo __('plan_' . $planKey . '_period'); ?></span>
                    </div>
                </div>

                <ul class="pricing-features">
                    <?php for ($i = 1; $i <= $plan['feature_count']; $i++):
                        $feature = __('plan_' . $planKey . '_feature_' . $i);
                        $isExcluded = strpos($feature, '✗') !== false;
                    ?>
                        <li class="<?php echo $isExcluded ? 'feature-excluded' : 'feature-included'; ?><?php echo $i > 9 ? ' extra-feature' : ''; ?>"<?php echo $i > 9 ? ' style="display: none;"' : ''; ?>>
                            <span class="<?php echo $isExcluded ? 'feature-icon-cross' : 'feature-icon-check'; ?>"><?php echo $isExcluded ? '✗' : '✓'; ?></span>
                            <span><?php echo esc(str_replace(['✓ ', '✗ '], '', $feature)); ?></span>
                        </li>
                    <?php endfor; ?>
                </ul>

                <?php if ($plan['feature_count'] > 9): ?>
                    <span role="button" class="btn-toggle-features" 
                          onclick="togglePlanFeatures(this, event)" 
                          data-collapsed="true"
                          data-text-more="<?php echo esc(__('show_more_features', 'More Features')); ?>"
                          data-text-less="<?php echo esc(__('show_less_features', 'Less Features')); ?>"
                          style="background:none; border:none; color:var(--color-primary); cursor:pointer; font-size:0.8rem; margin:-0.5rem auto 1rem; display:block; font-weight:600; text-align:center; position:relative; z-index:10; padding: 4px 8px;">
                        <?php echo esc(__('show_more_features', 'More Features')); ?> &darr;
                    </span>
                <?php endif; ?>

                <span class="<?php echo $planKey === 'free' ? 'btn btn-secondary' : 'btn btn-primary'; ?> w-full home-plan-cta">
                    <?php echo esc($plan['cta']); ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <p class="home-pricing-note"><?php echo __('home_pricing_note'); ?></p>
</section>

<script>
function togglePlanFeatures(btn, event) {
    event.preventDefault();
    event.stopPropagation();
    
    const card = btn.closest('.home-plan-card');
    const extraFeatures = card.querySelectorAll('.pricing-features li.extra-feature');
    const isCollapsed = btn.getAttribute('data-collapsed') !== 'false';
    
    if (isCollapsed) {
        extraFeatures.forEach(li => {
            li.style.display = 'flex';
        });
        btn.innerHTML = btn.getAttribute('data-text-less') + ' &uarr;';
        btn.setAttribute('data-collapsed', 'false');
    } else {
        extraFeatures.forEach(li => {
            li.style.display = 'none';
        });
        btn.innerHTML = btn.getAttribute('data-text-more') + ' &darr;';
        btn.setAttribute('data-collapsed', 'true');
    }
}
</script>
