<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

$isUser = isLoggedIn();
$username = $_SESSION['username'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

$stats = [
    'referred_count' => 0,
    'pending_commission' => 0,
    'paid_commission' => 0,
];
$userCommissions = [];

if ($isUser) {
    // Referred registrations count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by_id = ?");
    $stmt->execute([$userId]);
    $stats['referred_count'] = (int)$stmt->fetchColumn();

    // Pending commissions total
    $stmt = $pdo->prepare("SELECT SUM(commission_amount) FROM affiliate_commissions WHERE referrer_id = ? AND status = 'pending'");
    $stmt->execute([$userId]);
    $stats['pending_commission'] = (float)($stmt->fetchColumn() ?: 0.0);

    // Paid commissions total
    $stmt = $pdo->prepare("SELECT SUM(commission_amount) FROM affiliate_commissions WHERE referrer_id = ? AND status = 'paid'");
    $stmt->execute([$userId]);
    $stats['paid_commission'] = (float)($stmt->fetchColumn() ?: 0.0);

    // History list of commissions
    $stmt = $pdo->prepare("
        SELECT ac.*, u.username as referred_username 
        FROM affiliate_commissions ac 
        LEFT JOIN users u ON ac.referred_id = u.id 
        WHERE ac.referrer_id = ? 
        ORDER BY ac.created_at DESC
    ");
    $stmt->execute([$userId]);
    $userCommissions = $stmt->fetchAll();
}

$refLink = rtrim($config['site_url'] ?? 'https://tldix.com', '/') . '/?ref=' . esc($username);
?>

<div class="affiliate-page-container" style="max-width: 960px; margin: 2rem auto; padding: 0 1rem;">
    <!-- Kicker Header -->
    <div style="text-align: center; margin-bottom: 3rem;">
        <span class="premium-section-kicker" style="background: rgba(20, 184, 166, 0.1); color: var(--color-primary); padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;">
            TLDix Affiliate
        </span>
        <h1 style="font-family: var(--font-display); color: var(--color-text-primary); font-size: 2.5rem; margin-top: 1rem; margin-bottom: 0.75rem;">
            <?php echo __('affiliate_title', 'Affiliate Program'); ?>
        </h1>
        <p style="color: var(--color-text-secondary); max-width: 600px; margin: 0 auto; font-size: 1.05rem; line-height: 1.6;">
            <?php echo __('affiliate_subtitle', 'Recommend TLDix to your network and earn a 40% one-time commission for each paid member registration. Help businesses put an end to the fear of missing domain and SSL renewal dates.'); ?>
        </p>
    </div>

    <?php if (!$isUser): ?>
        <!-- Guest View Page -->
        <div class="glass-panel" style="padding: 3rem; border-radius: 16px; text-align: center; border: 1px solid var(--color-border); background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px);">
            <h2 style="font-family: var(--font-display); color: var(--color-text-primary); margin-bottom: 1rem;"><?php echo __('affiliate_guest_title', 'Start Earning'); ?></h2>
            <p style="color: var(--color-text-secondary); max-width: 500px; margin: 0 auto 2rem; font-size: 0.95rem; line-height: 1.5;">
                <?php echo __('affiliate_guest_desc', 'Sign up for free or log in to your account, copy and share your unique affiliate link.'); ?>
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <a href="<?php echo url('register'); ?>" class="btn btn-primary" style="padding: 0.75rem 2rem;"><?php echo __('affiliate_guest_cta_register', 'Sign Up Now'); ?></a>
                <a href="<?php echo url('login'); ?>" class="btn btn-secondary" style="padding: 0.75rem 2rem;"><?php echo __('affiliate_guest_cta_login', 'Log In'); ?></a>
            </div>
        </div>
        
        <div style="margin-top: 3rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
            <div class="glass-panel" style="padding: 1.5rem; border-radius: 12px; border: 1px solid var(--color-border);">
                <span style="font-size: 2rem;">💰</span>
                <h4 style="margin: 1rem 0 0.5rem; color: var(--color-text-primary);"><?php echo __('affiliate_feat1_title', '40% High Commission'); ?></h4>
                <p style="font-size: 0.88rem; color: var(--color-text-secondary); line-height: 1.4;"><?php echo __('affiliate_feat1_desc', 'You earn a 40% commission on the first successful premium payment (Bronze, Silver, or Agency) of the user you refer.'); ?></p>
            </div>
            <div class="glass-panel" style="padding: 1.5rem; border-radius: 12px; border: 1px solid var(--color-border);">
                <span style="font-size: 2rem;">🍪</span>
                <h4 style="margin: 1rem 0 0.5rem; color: var(--color-text-primary);"><?php echo __('affiliate_feat2_title', '30-Day Cookie Lifespan'); ?></h4>
                <p style="font-size: 0.88rem; color: var(--color-text-secondary); line-height: 1.4;"><?php echo __('affiliate_feat2_desc', 'No matter when visitors sign up within 30 days of clicking your link, they are recorded as your referral.'); ?></p>
            </div>
            <div class="glass-panel" style="padding: 1.5rem; border-radius: 12px; border: 1px solid var(--color-border);">
                <span style="font-size: 2rem;">⚡</span>
                <h4 style="margin: 1rem 0 0.5rem; color: var(--color-text-primary);"><?php echo __('affiliate_feat3_title', 'Fast and Reliable Payouts'); ?></h4>
                <p style="font-size: 0.88rem; color: var(--color-text-secondary); line-height: 1.4;"><?php echo __('affiliate_feat3_desc', 'You can track your commission payments transparently through our panel and request your earnings to your bank account.'); ?></p>
            </div>
        </div>
    <?php else: ?>
        <!-- Member View Page -->
        <div class="glass-panel" style="padding: 2.5rem; border-radius: 16px; border: 1px solid var(--color-border); background: rgba(255, 255, 255, 0.02); margin-bottom: 2rem;">
            <h3 style="color: var(--color-text-primary); margin-bottom: 1.25rem; font-family: var(--font-display);"><?php echo __('affiliate_member_link_title', 'Your Personal Referral Link'); ?></h3>
            <div style="display: flex; gap: 0.5rem;">
                <input type="text" id="refLinkInput" value="<?php echo $refLink; ?>" readonly style="flex: 1; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid var(--color-border); background: rgba(0,0,0,0.3); color: var(--color-text-primary); font-family: monospace; font-size: 0.95rem;">
                <button onclick="copyRefLink()" class="btn btn-primary" style="padding: 0 1.5rem;"><?php echo __('affiliate_btn_copy', 'Copy'); ?></button>
            </div>
            <p style="font-size: 0.82rem; color: var(--color-text-muted); margin-top: 0.75rem; margin-bottom: 0;">
                <?php echo __('affiliate_member_link_note', 'When your visitors arrive via this link, they are tracked with a 30-day cookie.'); ?>
            </p>
        </div>

        <!-- Stats Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
            <div class="glass-panel" style="padding: 1.5rem; border-radius: 12px; border: 1px solid var(--color-border); text-align: center;">
                <span style="color: var(--color-text-muted); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;"><?php echo __('affiliate_stat_referred', 'Referred Signups'); ?></span>
                <div style="font-size: 2.25rem; font-weight: 700; color: var(--color-text-primary); margin-top: 0.5rem;"><?php echo $stats['referred_count']; ?></div>
            </div>
            <div class="glass-panel" style="padding: 1.5rem; border-radius: 12px; border: 1px solid var(--color-border); text-align: center;">
                <span style="color: var(--color-text-muted); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;"><?php echo __('affiliate_stat_pending', 'Pending Commission'); ?></span>
                <div style="font-size: 2.25rem; font-weight: 700; color: var(--color-warning); margin-top: 0.5rem;"><?php echo number_format($stats['pending_commission'], 2); ?> USD</div>
            </div>
            <div class="glass-panel" style="padding: 1.5rem; border-radius: 12px; border: 1px solid var(--color-border); text-align: center;">
                <span style="color: var(--color-text-muted); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;"><?php echo __('affiliate_stat_paid', 'Paid Earnings'); ?></span>
                <div style="font-size: 2.25rem; font-weight: 700; color: var(--color-success); margin-top: 0.5rem;"><?php echo number_format($stats['paid_commission'], 2); ?> USD</div>
            </div>
        </div>

        <!-- Commissions list -->
        <div class="glass-panel" style="padding: 2rem; border-radius: 16px; border: 1px solid var(--color-border);">
            <h3 style="color: var(--color-text-primary); margin-bottom: 1.5rem; font-family: var(--font-display);"><?php echo __('affiliate_history_title', 'Referral Earnings History'); ?></h3>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--color-border); color: var(--color-text-secondary); font-size: 0.85rem;">
                            <th style="padding: 1rem 0.75rem;"><?php echo __('affiliate_history_referred_user', 'Referred User'); ?></th>
                            <th style="padding: 1rem 0.75rem;"><?php echo __('affiliate_history_amount', 'Commission Amount'); ?></th>
                            <th style="padding: 1rem 0.75rem;"><?php echo __('affiliate_history_status', 'Status'); ?></th>
                            <th style="padding: 1rem 0.75rem;"><?php echo __('affiliate_history_date', 'Date'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($userCommissions)): ?>
                            <tr>
                                <td colspan="4" style="padding: 2rem 0.75rem; text-align: center; color: var(--color-text-muted); font-size: 0.9rem;">
                                    <?php echo __('affiliate_history_empty', 'You have not earned any referral commissions yet. Invite your first referral now!'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($userCommissions as $comm): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--color-text-secondary); font-size: 0.9rem;">
                                    <td style="padding: 1rem 0.75rem; font-weight: 600; color: var(--color-text-primary);"><?php echo esc($comm['referred_username'] ?? __('affiliate_customer_fallback', 'Customer')); ?></td>
                                    <td style="padding: 1rem 0.75rem; font-weight: 700; color: var(--color-success);"><?php echo esc($comm['commission_amount']); ?> <?php echo esc($comm['currency']); ?></td>
                                    <td style="padding: 1rem 0.75rem;">
                                        <?php if ($comm['status'] === 'paid'): ?>
                                            <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 2px 8px; border-radius: 4px; font-size: 0.72rem; font-weight: 700;"><?php echo __('affiliate_status_paid', 'PAID'); ?></span>
                                        <?php else: ?>
                                            <span style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; padding: 2px 8px; border-radius: 4px; font-size: 0.72rem; font-weight: 700;"><?php echo __('affiliate_status_pending', 'PENDING'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem 0.75rem; color: var(--color-text-muted); font-size: 0.8rem;"><?php echo formatDate($comm['created_at'], 'd M Y H:i'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        function copyRefLink() {
            var copyText = document.getElementById("refLinkInput");
            copyText.select();
            copyText.setSelectionRange(0, 99999); // Mobile
            navigator.clipboard.writeText(copyText.value);
            alert("<?php echo esc(__('affiliate_copy_success', 'Your referral link has been copied!')); ?>");
        }
        </script>
    <?php endif; ?>
</div>
