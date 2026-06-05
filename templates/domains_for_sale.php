<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

global $config, $pdo;
$recommendedSslProviders = getSelectedAffiliateProviders($pdo, $config, 'recommended_ssl_codes', 'ssl', ['namecheap_ssl', 'ssls', 'ssldragon']);

// Define marketplace providers list with metadata
$marketplaces = [
    [
        'id'    => 'namecheap',
        'name'  => 'Namecheap',
        'badge' => __('sale_badge_affiliate'),
        'color' => 'linear-gradient(135deg, #de3721 0%, #ff5c36 100%)',
        'desc'  => __('provider_namecheap_desc'),
        'url'   => url('go?to=namecheap'),
        'icon'  => '🏷️'
    ],
    [
        'id'    => 'afternic',
        'name'  => 'Afternic',
        'badge' => __('sale_badge_godaddy_net'),
        'color' => 'linear-gradient(135deg, #00828a 0%, #00a699 100%)',
        'desc'  => __('provider_afternic_desc'),
        'url'   => url('go?to=afternic'),
        'icon'  => '🤝'
    ],
    [
        'id'    => 'sedo',
        'name'  => 'Sedo',
        'badge' => __('sale_badge_broker'),
        'color' => 'linear-gradient(135deg, #0f5195 0%, #1e88e5 100%)',
        'desc'  => __('provider_sedo_desc'),
        'url'   => url('go?to=sedo'),
        'icon'  => '⚖️'
    ],
    [
        'id'    => 'dan',
        'name'  => 'Dan.com',
        'badge' => __('sale_badge_godaddy_brand'),
        'color' => 'linear-gradient(135deg, #164e63 0%, #115e59 100%)',
        'desc'  => __('provider_dan_desc'),
        'url'   => url('go?to=dan'),
        'icon'  => '⚡'
    ],
    [
        'id'    => 'atom',
        'name'  => 'Atom',
        'badge' => __('sale_badge_escrow'),
        'color' => 'linear-gradient(135deg, #0f766e 0%, #0f4c75 100%)',
        'desc'  => __('provider_atom_desc'),
        'url'   => url('go?to=atom'),
        'icon'  => '💎'
    ],
    [
        'id'    => 'dynadot_mkt',
        'name'  => 'Dynadot Marketplace',
        'badge' => __('sale_badge_marketplace'),
        'color' => 'linear-gradient(135deg, #b91c1c 0%, #dc2626 100%)',
        'desc'  => __('provider_dynadot_mkt_desc'),
        'url'   => url('go?to=dynadot_mkt'),
        'icon'  => '📈'
    ]
];
?>

<div class="domains-for-sale-page">
    <div class="page-intro text-center" style="margin-bottom: 3.5rem; max-width: 800px; margin-left: auto; margin-right: auto;">
        <h1 class="glow-text" style="font-family: var(--font-display); font-size: 2.75rem; font-weight: 800; line-height: 1.2; margin-bottom: 1rem; color: var(--color-text-primary);">
            <?php echo __('sale_title'); ?>
        </h1>
        <p style="font-size: 1.15rem; line-height: 1.6; color: var(--color-text-secondary);">
            <?php echo __('sale_subtitle'); ?>
        </p>
    </div>

    <!-- Marketplace Cards Grid -->
    <div class="marketplaces-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 2rem;">
        <?php foreach ($marketplaces as $mkt): ?>
            <div class="glass-panel marketplace-card" style="position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; border-radius: 16px; padding: 2.25rem; transition: transform 0.3s ease, border-color 0.3s ease; min-height: 380px;">
                
                <!-- Background glow overlay matching the brand color -->
                <div class="brand-glow-bg" style="position: absolute; top: -100px; right: -100px; width: 250px; height: 250px; background: <?php echo $mkt['color']; ?>; filter: blur(80px); opacity: 0.15; pointer-events: none; border-radius: 50%;"></div>
                
                <div>
                    <!-- Card Top Header -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
                        <span class="mkt-icon-badge" style="font-size: 2rem; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); padding: 0.5rem; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; width: 60px; height: 60px;">
                            <?php echo $mkt['icon']; ?>
                        </span>
                        <span class="mkt-badge-tag" style="font-size: 0.72rem; padding: 4px 10px; border-radius: 20px; font-weight: bold; text-transform: uppercase;">
                            <?php echo esc($mkt['badge']); ?>
                        </span>
                    </div>

                    <!-- Marketplace Brand Title -->
                    <h3 class="mkt-title" style="font-family: var(--font-display); font-size: 1.5rem; font-weight: 700; color: var(--color-text-primary); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?php echo $mkt['color']; ?>;"></span>
                        <?php echo esc($mkt['name']); ?>
                    </h3>

                    <!-- Marketplace Description -->
                    <p style="font-size: 0.95rem; line-height: 1.6; margin-bottom: 2rem; color: var(--color-text-secondary);">
                        <?php echo esc($mkt['desc']); ?>
                    </p>
                </div>

                <!-- Action Button -->
                <div style="margin-top: auto;">
                    <a href="<?php echo esc($mkt['url']); ?>" target="_blank" rel="noopener" class="btn btn-primary w-full" style="background: <?php echo $mkt['color']; ?>; border: none; font-weight: bold; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.2); justify-content: center; gap: 0.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                        <span><?php echo __('sale_btn_visit'); ?></span>
                        <span style="font-size: 0.85rem;">↗</span>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- SSL Certificate Partners -->
    <div class="ssl-partners-section" style="margin-top: 4rem;">
        <h2 style="font-family: var(--font-display); font-size: 2rem; font-weight: 700; color: var(--color-text-primary); margin-bottom: 1rem; text-align: center;">
            <?php echo __('ssl_recommendation_title'); ?>
        </h2>
        <p style="font-size: 1rem; margin-bottom: 2.5rem; max-width: 600px; margin-left: auto; margin-right: auto; color: var(--color-text-secondary); text-align: center;">
            <?php echo __('ssl_recommendation_desc'); ?>
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 2rem; max-width: 1100px; margin: 0 auto;">
            <?php foreach ($recommendedSslProviders as $provider): ?>
                <div class="glass-panel" style="border-radius: 16px; padding: 2rem; display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden; min-height: 220px;">
                    <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: linear-gradient(135deg, #047857 0%, #065f46 100%); filter: blur(60px); opacity: 0.15; pointer-events: none; border-radius: 50%;"></div>
                    <div>
                        <h3 style="font-family: var(--font-display); font-size: 1.25rem; font-weight: 700; color: var(--color-text-primary); margin-bottom: 0.5rem;"><?php echo esc($provider['name']); ?></h3>
                        <p style="font-size: 0.9rem; line-height: 1.5; margin-bottom: 1.5rem; color: var(--color-text-secondary);">
                            <?php echo esc($provider['description']); ?>
                        </p>
                    </div>
                    <a href="<?php echo url('go?to=' . urlencode($provider['code']) . '&utm_source=ssl_sale_recommendation'); ?>" target="_blank" rel="noopener" class="btn btn-secondary affiliate-btn-ssl" style="text-align: center; justify-content: center; gap: 0.5rem; font-weight: bold; width: 100%;">
                        <span><?php echo esc($provider['button_label']); ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Extra Information Section -->
    <div class="glass-panel info-banner" style="margin-top: 4rem; padding: 2.5rem; border-radius: 16px; border: 1px dashed rgba(15, 118, 110, 0.3); background: rgba(15, 118, 110, 0.02); display: flex; align-items: center; gap: 2rem; flex-wrap: wrap;">
        <div style="font-size: 2.5rem;">💡</div>
        <div style="flex: 1; min-width: 280px;">
            <h4 style="font-family: var(--font-display); font-size: 1.2rem; color: var(--color-text-primary); margin-bottom: 0.5rem;">Premium Domain Satış Kanalları</h4>
            <p style="font-size: 0.92rem; line-height: 1.6; margin: 0; color: var(--color-text-secondary);">
                Bu sayfadaki platformlar aracılığıyla alan adlarınızı satabilir veya açık artırmaları inceleyebilirsiniz. Premium alan adı alımlarında güvenli ödeme, transfer ve fiyat karşılaştırma seçeneklerini tek ekranda değerlendirebilirsiniz.
            </p>
        </div>
    </div>
</div>
