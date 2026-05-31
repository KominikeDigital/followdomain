<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

global $pdo;

$userId = $_SESSION['user_id'];
$integrationMessage = isset($_SESSION['integration_msg']) ? $_SESSION['integration_msg'] : null;
unset($_SESSION['integration_msg']);
$integrationError = null;

// Fetch current user details
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$userData = $stmtUser->fetch();

// Handle Connection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'connect_cloudflare') {
        $email = filter_input(INPUT_POST, 'cf_email', FILTER_SANITIZE_EMAIL);
        $apiKey = trim($_POST['cf_api_key']);
        
        if (empty($email) || empty($apiKey)) {
            $integrationError = __('cf_fill_required');
        } else {
            $now = date('Y-m-d H:i:s');
            try {
                // Delete existing if any
                $stmtDel = $pdo->prepare("DELETE FROM integrations WHERE user_id = ? AND provider = 'cloudflare'");
                $stmtDel->execute([$userId]);
                
                // Insert new
                $stmt = $pdo->prepare("INSERT INTO integrations (user_id, provider, email, api_key, status, created_at) VALUES (?, 'cloudflare', ?, ?, 'active', ?)");
                $stmt->execute([$userId, $email, $apiKey, $now]);
                
                // Mock importing domains (insert some mock records for realistic feedback)
                // For demonstration, let's auto-import 'mysite.com' and 'myproject.xyz' if they aren't tracked
                $mockDomains = ['mysite.com', 'myproject.xyz'];
                foreach ($mockDomains as $md) {
                    $domainData = getOrUpdateDomain($pdo, $md);
                    if ($domainData) {
                        try {
                            $stmtLink = $pdo->prepare("INSERT INTO user_domains (user_id, domain_name, notify_30, notify_7, notify_1, created_at) VALUES (?, ?, 1, 1, 1, ?)");
                            $stmtLink->execute([$userId, $md, $now]);
                        } catch (PDOException $ex) {
                            // User already tracks it
                        }
                    }
                }
                
                logActivity($pdo, $userId, "Cloudflare integration connected ($email).");
                $integrationMessage = __('cf_connect_success');
            } catch (PDOException $e) {
                $integrationError = __('cf_connect_error') . $e->getMessage();
            }
        }
    }

    // Handle Webhook URL Update
    if ($_POST['action'] === 'update_webhook') {
        $webhookUrl = filter_input(INPUT_POST, 'webhook_url', FILTER_SANITIZE_URL);
        
        // Enforce plan verification
        if (in_array($userData['api_plan'], ['bronze', 'silver', 'gold'])) {
            $stmtUpd = $pdo->prepare("UPDATE users SET webhook_url = ? WHERE id = ?");
            $stmtUpd->execute([$webhookUrl, $userId]);
            logActivity($pdo, $userId, "Webhook URL updated: " . $webhookUrl);
            $integrationMessage = __('webhook_update_success');
            
            // Refresh user data
            $stmtUser->execute([$userId]);
            $userData = $stmtUser->fetch();
        } else {
            $integrationError = __('webhook_plan_error');
        }
    }
}

// Handle Disconnect
if (isset($_GET['disconnect'])) {
    $stmtDel = $pdo->prepare("DELETE FROM integrations WHERE user_id = ? AND provider = 'cloudflare'");
    $stmtDel->execute([$userId]);
    logActivity($pdo, $userId, "Cloudflare integration disconnected.");
    $integrationMessage = __('cf_disconnect_success');
}

// Fetch connected integrations
$stmt = $pdo->prepare("SELECT * FROM integrations WHERE user_id = ? AND provider = 'cloudflare'");
$stmt->execute([$userId]);
$cfIntegration = $stmt->fetch();
?>

<div class="integrations-page">
    <div class="page-header">
        <h1 class="page-title"><?php echo __('integrations_title'); ?></h1>
        <p class="page-subtitle"><?php echo __('integrations_subtitle'); ?></p>
    </div>

    <?php if (isset($integrationMessage)): ?>
        <div class="alert alert-success"><?php echo esc($integrationMessage); ?></div>
    <?php endif; ?>
    
    <?php if (isset($integrationError)): ?>
        <div class="alert alert-error"><?php echo esc($integrationError); ?></div>
    <?php endif; ?>

    <div class="integrations-list-container">
        
        <!-- Cloudflare Card -->
        <div class="glass-panel integration-provider-card">
            
            <div class="provider-main-info">
                <div class="provider-logo-title">
                    <span class="cf-logo-orange">☁</span>
                    <div>
                        <h2>Cloudflare</h2>
                        <p class="provider-desc-txt"><?php echo __('cf_integration_desc_full'); ?></p>
                    </div>
                </div>
                
                <div class="provider-status-actions">
                    <?php if ($cfIntegration): ?>
                        <span class="status-badge-active"><?php echo __('connected'); ?></span>
                        <a href="<?php echo url('panel/integrations?disconnect=1'); ?>" class="btn btn-secondary btn-sm" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.2);"><?php echo __('disconnect_btn'); ?></a>
                    <?php else: ?>
                        <button class="btn btn-primary btn-sm" onclick="document.getElementById('cfConnectModal').showModal()"><?php echo __('connect'); ?></button>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($cfIntegration): ?>
                <div class="connection-meta-details">
                    <table class="specs-table" style="margin-top: 1.5rem;">
                        <tbody>
                            <tr>
                                <td><?php echo __('cf_connected_account'); ?></td>
                                <td><?php echo esc($cfIntegration['email']); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo __('cf_connected_date'); ?></td>
                                <td><?php echo formatDate($cfIntegration['created_at']); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo __('cf_sync_frequency'); ?></td>
                                <td><span class="highlight-text"><?php echo __('cf_sync_hourly'); ?></span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>

        <!-- Developer API Credentials Panel -->
        <?php
        $apiLimit = in_array($userData['api_plan'], ['bronze', 'silver', 'gold']) ? 10000 : 100;
        $todayStr = date('Y-m-d');
        $todayQueries = ($userData['last_api_query_date'] === $todayStr) ? (int)$userData['api_queries_today'] : 0;
        $pct = round(($todayQueries / $apiLimit) * 100, 1);
        if ($pct > 100) $pct = 100;
        ?>
        <div class="glass-panel integration-provider-card" style="margin-top: 2rem;">
            <div class="provider-main-info">
                <div class="provider-logo-title">
                    <span class="cf-logo-orange" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); line-height: 1; text-align: center; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">⚡</span>
                    <div>
                        <h2><?php echo __('api_credentials'); ?></h2>
                        <p class="provider-desc-txt"><?php echo __('api_credentials_sub'); ?></p>
                    </div>
                </div>
                
                <div class="provider-status-actions">
                    <span class="status-badge-active" style="background-color: rgba(59, 130, 246, 0.1); color: #3b82f6; border-color: rgba(59, 130, 246, 0.2);">
                        Plan: <?php echo strtoupper(esc($userData['api_plan'])); ?>
                    </span>
                </div>
            </div>
            
            <div class="connection-meta-details" style="margin-top: 1.5rem;">
                <div class="form-group">
                    <label><?php echo __('api_key_label'); ?></label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="apiKeyDisplay" value="<?php echo esc($userData['api_key']); ?>" readonly style="flex: 1; font-family: monospace; background: rgba(0,0,0,0.2); border: 1px solid var(--color-border); color: #fff; padding: 0.75rem; border-radius: 8px;">
                        <button class="btn btn-secondary btn-sm" onclick="copyApiKey()" style="min-width: 100px;"><?php echo __('btn_copy'); ?></button>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.5rem; color: var(--color-text-secondary);">
                        <span><?php echo sprintf(__('api_usage_today_full'), $todayQueries, $apiLimit); ?></span>
                        <span>%<?php echo $pct; ?></span>
                    </div>
                    <div class="lifecycle-bar-container" style="height: 6px; background: rgba(255,255,255,0.03);">
                        <div class="lifecycle-bar" style="width: <?php echo $pct; ?>%; background: linear-gradient(to right, #3b82f6, #10b981);"></div>
                    </div>
                </div>
                
                <!-- Webhook Update Form -->
                <form action="<?php echo url('panel/integrations'); ?>" method="POST" style="margin-top: 2rem; border-top: 1px solid var(--color-border); padding-top: 1.5rem;">
                    <input type="hidden" name="action" value="update_webhook">
                    <div class="form-group">
                        <label for="webhookUrl"><?php echo __('webhook_url_label'); ?></label>
                        <input type="url" id="webhookUrl" name="webhook_url" value="<?php echo esc($userData['webhook_url']); ?>" placeholder="https://api.yourdomain.com/webhooks/domain-alerts" <?php echo !in_array($userData['api_plan'], ['bronze', 'silver', 'gold']) ? 'disabled' : ''; ?> style="width: 100%; max-width: 100%;">
                        <span class="input-helper"><?php echo __('webhook_url_helper'); ?></span>
                    </div>
                    <?php if (in_array($userData['api_plan'], ['bronze', 'silver', 'gold'])): ?>
                        <button type="submit" class="btn btn-primary btn-sm" style="margin-top: 0.5rem;"><?php echo __('webhook_update_btn'); ?></button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Upgrade Pricing Plans Grid -->
        <div class="pricing-plans-section glass-panel" style="margin-top: 2rem; padding: 2rem;">
            <h2 class="pricing-section-title"><?php echo __('upgrade_plan_title'); ?></h2>
            <p class="pricing-section-subtitle text-muted"><?php echo __('upgrade_plan_sub'); ?></p>
            
            <div class="pricing-plans-grid" id="pricing">
                <!-- Free Tier -->
                <div class="pricing-card free-tier <?php echo ($userData['api_plan'] === 'free' || empty($userData['api_plan'])) ? 'active' : ''; ?>">
                    <div class="pricing-card-header">
                        <span class="pricing-badge"><?php echo __('plan_badge_free', 'Basic'); ?></span>
                        <h3 class="pricing-plan-name"><?php echo __('plan_free_name'); ?></h3>
                        <div class="pricing-price"><?php echo __('plan_free_price'); ?> <span class="pricing-period"><?php echo __('plan_free_period'); ?></span></div>
                    </div>
                    <ul class="pricing-features">
                        <?php for($i=1; $i<=8; $i++): 
                            $feat = __('plan_free_feature_' . $i);
                            $isEx = (strpos($feat, '✗') !== false);
                        ?>
                            <li class="<?php echo $isEx ? 'feature-excluded' : 'feature-included'; ?>">
                                <span class="<?php echo $isEx ? 'feature-icon-cross' : 'feature-icon-check'; ?>"><?php echo $isEx ? '✗' : '✓'; ?></span> 
                                <span><?php echo str_replace(['✓ ', '✗ '], '', $feat); ?></span>
                            </li>
                        <?php endfor; ?>
                    </ul>
                    <div class="pricing-action">
                        <?php if ($userData['api_plan'] === 'free' || empty($userData['api_plan'])): ?>
                            <button class="btn btn-secondary w-full active-plan-btn" disabled><?php echo __('plan_active'); ?></button>
                        <?php else: ?>
                            <form action="<?php echo url('panel/integrations/upgrade'); ?>" method="POST">
                                <input type="hidden" name="plan" value="free">
                                <button type="submit" class="btn btn-secondary w-full" onclick="return confirm('Emin misiniz? Limitleriniz düşürülecektir.')"><?php echo __('plan_switch'); ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bronze Tier -->
                <div class="pricing-card bronze-tier <?php echo ($userData['api_plan'] === 'bronze') ? 'active' : ''; ?>">
                    <span class="pricing-popular-tag"><?php echo __('plan_popular'); ?></span>
                    <div class="pricing-card-header">
                        <span class="pricing-badge"><?php echo __('plan_badge_bronze', 'Starter'); ?></span>
                        <h3 class="pricing-plan-name"><?php echo __('plan_bronze_name'); ?></h3>
                        <div class="pricing-price"><?php echo __('plan_bronze_price'); ?> <span class="pricing-period"><?php echo __('plan_bronze_period'); ?></span></div>
                    </div>
                    <ul class="pricing-features">
                        <?php for($i=1; $i<=8; $i++): 
                            $feat = __('plan_bronze_feature_' . $i);
                        ?>
                            <li class="feature-included">
                                <span class="feature-icon-check">✓</span> 
                                <span><?php echo str_replace(['✓ ', '✗ '], '', $feat); ?></span>
                            </li>
                        <?php endfor; ?>
                    </ul>
                    <div class="pricing-action">
                        <?php if ($userData['api_plan'] === 'bronze'): ?>
                            <button class="btn btn-secondary w-full active-plan-btn" disabled><?php echo __('plan_active'); ?></button>
                        <?php else: ?>
                            <a href="<?php echo url('checkout?plan=bronze'); ?>" class="btn btn-primary w-full text-center" style="display: block; line-height: 1.5; font-weight: 600; text-decoration: none;">
                                <?php echo sprintf(__('plan_upgrade'), __('plan_bronze_price')); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Silver Tier -->
                <div class="pricing-card silver-tier <?php echo ($userData['api_plan'] === 'silver') ? 'active' : ''; ?>">
                    <div class="pricing-card-header">
                        <span class="pricing-badge"><?php echo __('plan_badge_silver', 'Professional'); ?></span>
                        <h3 class="pricing-plan-name"><?php echo __('plan_silver_name'); ?></h3>
                        <div class="pricing-price"><?php echo __('plan_silver_price'); ?> <span class="pricing-period"><?php echo __('plan_silver_period'); ?></span></div>
                    </div>
                    <ul class="pricing-features">
                        <?php for($i=1; $i<=9; $i++): 
                            $feat = __('plan_silver_feature_' . $i);
                        ?>
                            <li class="feature-included">
                                <span class="feature-icon-check">✓</span> 
                                <span><?php echo str_replace(['✓ ', '✗ '], '', $feat); ?></span>
                            </li>
                        <?php endfor; ?>
                    </ul>
                    <div class="pricing-action">
                        <?php if ($userData['api_plan'] === 'silver'): ?>
                            <button class="btn btn-secondary w-full active-plan-btn" disabled><?php echo __('plan_active'); ?></button>
                        <?php else: ?>
                            <a href="<?php echo url('checkout?plan=silver'); ?>" class="btn btn-primary w-full text-center" style="display: block; line-height: 1.5; font-weight: 600; text-decoration: none;">
                                <?php echo sprintf(__('plan_upgrade'), __('plan_silver_price')); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Gold Tier -->
                <div class="pricing-card gold-tier <?php echo ($userData['api_plan'] === 'gold') ? 'active' : ''; ?>">
                    <span class="pricing-popular-tag best-value"><?php echo __('plan_badge_gold_right', 'Best Value'); ?></span>
                    <div class="pricing-card-header">
                        <span class="pricing-badge"><?php echo __('plan_badge_gold', 'Enterprise'); ?></span>
                        <h3 class="pricing-plan-name"><?php echo __('plan_gold_name'); ?></h3>
                        <div class="pricing-price"><?php echo __('plan_gold_price'); ?> <span class="pricing-period"><?php echo __('plan_gold_period'); ?></span></div>
                    </div>
                    <ul class="pricing-features">
                        <?php for($i=1; $i<=10; $i++): 
                            $feat = __('plan_gold_feature_' . $i);
                        ?>
                            <li class="feature-included">
                                <span class="feature-icon-check">✓</span> 
                                <span><?php echo str_replace(['✓ ', '✗ '], '', $feat); ?></span>
                            </li>
                        <?php endfor; ?>
                    </ul>
                    <div class="pricing-action">
                        <?php if ($userData['api_plan'] === 'gold'): ?>
                            <button class="btn btn-secondary w-full active-plan-btn" disabled><?php echo __('plan_active'); ?></button>
                        <?php else: ?>
                            <a href="<?php echo url('checkout?plan=gold'); ?>" class="btn btn-primary w-full text-center" style="display: block; line-height: 1.5; font-weight: 600; text-decoration: none;">
                                <?php echo sprintf(__('plan_upgrade'), __('plan_gold_price')); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function copyApiKey() {
    const input = document.getElementById('apiKeyDisplay');
    if (input) {
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value);
        alert('<?php echo esc(__('msg_api_key_copied')); ?>');
    }
}
</script>

<!-- Cloudflare Connect Modal -->
<dialog id="cfConnectModal" class="glass-modal">
    <div class="modal-container">
        <div class="modal-header">
            <h3><?php echo __('cf_modal_title'); ?></h3>
            <button class="modal-close-btn" onclick="document.getElementById('cfConnectModal').close()">×</button>
        </div>
        <p class="modal-subtitle"><?php echo __('cf_modal_sub'); ?></p>
        
        <form action="<?php echo url('panel/integrations'); ?>" method="POST">
            <input type="hidden" name="action" value="connect_cloudflare">
            
            <div class="form-group">
                <label for="cfEmail"><?php echo __('cf_modal_email'); ?></label>
                <input type="email" id="cfEmail" name="cf_email" placeholder="ornek@cloudflare.com" required>
            </div>
            
            <div class="form-group">
                <label for="cfApiKey"><?php echo __('cf_modal_key_label'); ?></label>
                <input type="password" id="cfApiKey" name="cf_api_key" placeholder="<?php echo esc(__('cf_modal_key_placeholder')); ?>" required>
                <span class="input-helper"><?php echo __('cf_modal_key_helper'); ?></span>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('cfConnectModal').close()"><?php echo __('modal_btn_cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo __('cf_modal_btn_connect'); ?></button>
            </div>
        </form>
    </div>
</dialog>
