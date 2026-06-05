<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

$apiUrlBase = "http://" . $_SERVER['HTTP_HOST'];
?>

<div class="docs-page">
    <div class="page-header text-center">
        <h1 class="page-title"><?php echo __('api_docs_title'); ?></h1>
        <p class="page-subtitle"><?php echo __('api_docs_sub'); ?></p>
    </div>

    <!-- Responsive Grid Layout -->
    <div class="docs-grid-flex">
        
        <!-- API Specs (Left Column) -->
        <div class="docs-content glass-panel">
            <h2><?php echo __('api_usage_header'); ?></h2>
            <p><?php echo __('api_usage_desc'); ?></p>
            
            <div class="api-method-card">
                <div class="method-badge">GET</div>
                <div class="endpoint-url">/api/v1/domain/<span>{domain_adi}</span></div>
            </div>
            
            <h3><?php echo __('api_params_title'); ?></h3>
            <table class="specs-table">
                <thead>
                    <tr>
                        <th><?php echo __('api_param_name'); ?></th>
                        <th><?php echo __('api_param_type'); ?></th>
                        <th><?php echo __('api_param_desc'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>domain_adi</code></td>
                        <td>String (Path)</td>
                        <td><?php echo __('api_param_domain_desc'); ?></td>
                    </tr>
                    <tr>
                        <td><code>api_key</code></td>
                        <td>String (Query / Header)</td>
                        <td><?php echo __('api_param_key_desc'); ?></td>
                    </tr>
                    <tr>
                        <td><code>refresh</code></td>
                        <td>Boolean (Query)</td>
                        <td><?php echo __('api_param_refresh_desc'); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <h3><?php echo __('api_example_request'); ?></h3>
            <pre tabindex="0"><code class="language-bash">curl -X GET "<?php echo $apiUrlBase; ?>/api/v1/domain/google.com" \
  -H "X-API-Key: da_sizin_api_anahtariniz"</code></pre>
            
            <h3><?php echo __('api_example_response'); ?></h3>
            <pre tabindex="0"><code class="language-json">{
  "success": true,
  "domain": "google.com",
  "registered": true,
  "registration_date": "1997-09-15 04:00:00",
  "expiration_date": "2028-09-14 04:00:00",
  "last_changed_date": "2019-09-09 15:39:04",
  "last_checked": "2026-05-29 16:30:12",
  "registrar": "MarkMonitor Inc.",
  "follower_count": 12,
  "status": [
    "client delete prohibited",
    "client transfer prohibited",
    "client update prohibited"
  ],
  "nameservers": [
    "ns1.google.com",
    "ns2.google.com"
  ]
}</code></pre>
        </div>

        <!-- Right Column: API Pricing & Limits -->
        <div class="docs-sidebar-plans">
            <div class="glass-panel pricing-plans-card">
                <h2 style="font-family: var(--font-display); font-size: 1.35rem; color: #ffffff; margin-bottom: 0.5rem;"><?php echo __('api_plans_title'); ?></h2>
                <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 1.5rem;"><?php echo __('api_plans_desc'); ?></p>
                
                <div class="api-plans-vertical" style="display: flex; flex-direction: column; gap: 1.25rem;">
                    
                    <!-- Free Plan -->
                    <div style="border: 1px solid var(--color-border); border-radius: 8px; padding: 1.25rem; background: rgba(255,255,255,0.01);">
                        <div class="api-plan-card-head">
                            <strong><?php echo __('plan_free_name'); ?></strong>
                            <span><?php echo __('plan_free_price'); ?> <?php echo __('plan_free_period'); ?></span>
                        </div>
                        <ul style="list-style: none; font-size: 0.85rem; color: var(--color-text-secondary); padding: 0; display: flex; flex-direction: column; gap: 0.4rem;">
                            <li>✓ <?php echo __('plan_free_feature_1'); ?></li>
                            <li style="<?php echo (strpos(__('plan_free_feature_2'), '✗') !== false) ? 'text-decoration: line-through; opacity: 0.5;' : ''; ?>"><?php echo __('plan_free_feature_2'); ?></li>
                            <li style="<?php echo (strpos(__('plan_free_feature_3'), '✗') !== false) ? 'text-decoration: line-through; opacity: 0.5;' : ''; ?>"><?php echo __('plan_free_feature_3'); ?></li>
                        </ul>
                    </div>

                    <!-- Bronze Plan -->
                    <div style="border: 1px solid rgba(15, 118, 110, 0.4); border-radius: 8px; padding: 1.25rem; background: rgba(15, 118, 110, 0.03); position: relative;">
                        <span class="status-tag" style="position: absolute; top: -10px; right: 10px; font-size: 0.65rem; background: var(--color-primary); color: #ffffff; padding: 0.1rem 0.4rem; border-radius: 3px; font-weight: 600;"><?php echo __('plan_popular'); ?></span>
                        <div class="api-plan-card-head">
                            <strong><?php echo __('plan_bronze_name'); ?></strong>
                            <span class="success-price"><?php echo __('plan_bronze_price'); ?> <?php echo __('plan_bronze_period'); ?></span>
                        </div>
                        <ul style="list-style: none; font-size: 0.85rem; color: var(--color-text-secondary); padding: 0; display: flex; flex-direction: column; gap: 0.4rem;">
                            <li>✓ <?php echo __('plan_bronze_feature_1'); ?></li>
                            <li><?php echo __('plan_bronze_feature_2'); ?></li>
                            <li><?php echo __('plan_bronze_feature_3'); ?></li>
                        </ul>
                    </div>

                    <!-- Silver Plan -->
                    <div style="border: 1px solid var(--color-border); border-radius: 8px; padding: 1.25rem; background: rgba(255,255,255,0.01);">
                        <div class="api-plan-card-head">
                            <strong><?php echo __('plan_silver_name'); ?></strong>
                            <span class="success-price"><?php echo __('plan_silver_price'); ?> <?php echo __('plan_silver_period'); ?></span>
                        </div>
                        <ul style="list-style: none; font-size: 0.85rem; color: var(--color-text-secondary); padding: 0; display: flex; flex-direction: column; gap: 0.4rem;">
                            <li>✓ <?php echo __('plan_silver_feature_1'); ?></li>
                            <li><?php echo __('plan_silver_feature_2'); ?></li>
                            <li><?php echo __('plan_silver_feature_3'); ?></li>
                        </ul>
                    </div>

                    <!-- Gold Plan -->
                    <div style="border: 1px solid var(--color-border); border-radius: 8px; padding: 1.25rem; background: rgba(255,255,255,0.01);">
                        <div class="api-plan-card-head">
                            <strong><?php echo __('plan_gold_name'); ?></strong>
                            <span class="success-price"><?php echo __('plan_gold_price'); ?> <?php echo __('plan_gold_period'); ?></span>
                        </div>
                        <ul style="list-style: none; font-size: 0.85rem; color: var(--color-text-secondary); padding: 0; display: flex; flex-direction: column; gap: 0.4rem;">
                            <li>✓ <?php echo __('plan_gold_feature_1'); ?></li>
                            <li><?php echo __('plan_gold_feature_2'); ?></li>
                            <li><?php echo __('plan_gold_feature_3'); ?></li>
                        </ul>
                    </div>
                </div>

                <p style="font-size: 0.8rem; color: var(--color-text-muted); margin-top: 1.5rem; text-align: center; line-height: 1.4;">
                    <?php echo sprintf(__('api_upgrade_hint'), url('panel/integrations')); ?>
                </p>
            </div>
        </div>
        
    </div>
</div>
