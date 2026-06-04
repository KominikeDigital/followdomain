<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

global $config, $pdo;

$userId   = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? '';
$currentUser = function_exists('getCurrentUser') ? getCurrentUser($pdo) : null;
$userEmail = $currentUser['email'] ?? '';

$plan = isset($_GET['plan']) ? trim($_GET['plan']) : 'bronze';
if (!in_array($plan, ['bronze', 'silver', 'gold'])) $plan = 'bronze';

$planDetails = [
    'bronze' => ['name' => 'BRONZE', 'price' => 9,  'currency' => 'USD', 'label' => '$9/ay'],
    'silver' => ['name' => 'SILVER', 'price' => 29, 'currency' => 'USD', 'label' => '$29/ay'],
    'gold'   => ['name' => 'GOLD',   'price' => 99, 'currency' => 'USD', 'label' => '$99/ay'],
];
$pd = $planDetails[$plan];

// Handle bank transfer submission
$checkoutMsg   = null;
$checkoutError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_wire') {
    $ref   = trim($_POST['wire_reference'] ?? '');
    $notes = trim($_POST['wire_notes'] ?? '');
    if (empty($ref)) {
        $checkoutError = 'Lütfen havale referans / dekont numarasını girin.';
    } else {
        try {
            $pdo->prepare("INSERT INTO payments (user_id, plan, amount, currency, method, status, reference, notes, created_at) VALUES (?, ?, ?, ?, 'wire', 'pending', ?, ?, ?)")
                ->execute([$userId, $plan, $pd['price'], $pd['currency'], $ref, $notes, date('Y-m-d H:i:s')]);
            $checkoutMsg = 'Ödeme bildiriminiz alındı! Admin onayından sonra planınız aktif hale getirilecektir. Referans: ' . htmlspecialchars($ref);
        } catch (Exception $e) {
            $checkoutError = 'Bir hata oluştu. Lütfen tekrar deneyin.';
        }
    }
}

// Whop links from config
$whopLinks = [
    'bronze' => $config['whop_link_bronze'] ?? '',
    'silver' => $config['whop_link_silver'] ?? '',
    'gold'   => $config['whop_link_gold']   ?? '',
];
$whopPlanIds = [
    'bronze' => trim((string)($config['whop_plan_bronze'] ?? '')),
    'silver' => trim((string)($config['whop_plan_silver'] ?? '')),
    'gold'   => trim((string)($config['whop_plan_gold'] ?? '')),
];
if (empty($whopPlanIds[$plan]) && !empty($whopLinks[$plan])) {
    $whopPlanIds[$plan] = extractWhopPlanId($whopLinks[$plan]);
}
$checkoutRefSecret = $config['whop_webhook_secret'] ?? ($config['admin_password'] ?? 'tldix');
$checkoutRef = hash_hmac('sha256', $userId . '|' . $userEmail . '|' . $plan, $checkoutRefSecret);
$whopReturnUrl = absolute_url('checkout?plan=' . urlencode($plan));
$whopCheckoutUrl = !empty($whopLinks[$plan]) ? buildWhopCheckoutUrl($whopLinks[$plan], [
    'prefill_user' => $username,
    'prefill_email' => $userEmail,
    'tldix_user_id' => $userId,
    'tldix_plan' => $plan,
    'tldix_ref' => $checkoutRef,
    'metadata[tldix_user_id]' => $userId,
    'metadata[tldix_plan]' => $plan,
    'metadata[tldix_ref]' => $checkoutRef
]) : '';

// Bank info from config
$bankName    = $config['bank_name']         ?? 'Belirtilmemiş';
$bankHolder  = $config['bank_account_name'] ?? 'Belirtilmemiş';
$bankIban    = $config['bank_iban']         ?? 'Belirtilmemiş';
$bankAccNo   = $config['bank_account_no']   ?? '';
$bankBranch  = $config['bank_branch_code']  ?? '';
$bankNotes   = $config['bank_notes']        ?? 'Havale açıklamasına kullanıcı adınızı (' . $username . ') ve seçtiğiniz planı yazınız.';
?>

<div class="checkout-page-container" style="max-width: 960px; margin: 2rem auto; padding: 0 1rem;">

    <?php if ($checkoutMsg): ?>
        <div class="alert alert-success" style="margin-bottom: 1.5rem;"><?php echo $checkoutMsg; ?></div>
    <?php endif; ?>
    <?php if ($checkoutError): ?>
        <div class="alert alert-error" style="margin-bottom: 1.5rem;"><?php echo $checkoutError; ?></div>
    <?php endif; ?>

    <div class="glass-panel" style="padding: 2.5rem; border-radius: 16px;">

        <!-- Header -->
        <div style="text-align: center; margin-bottom: 2.5rem;">
            <h1 style="font-family: var(--font-display); color: var(--color-text-primary); font-size: 2rem; margin-bottom: 0.5rem;">
                <?php echo __('checkout_title', 'Plan Yükselt'); ?>
            </h1>
            <p style="color: var(--color-text-secondary); font-size: 0.95rem;">
                Seçili plan: <strong style="color: var(--color-primary);"><?php echo $pd['name']; ?></strong>
                — <strong style="color: var(--color-success);"><?php echo $pd['label']; ?></strong>
            </p>
        </div>

        <!-- Plan selector buttons -->
        <div style="display: flex; gap: 0.75rem; justify-content: center; margin-bottom: 2.5rem; flex-wrap: wrap;">
            <?php foreach ($planDetails as $pk => $pv): ?>
                <a href="<?php echo url('checkout?plan=' . $pk); ?>"
                   class="btn <?php echo $pk === $plan ? 'btn-primary' : 'btn-secondary'; ?>"
                   style="min-width: 110px; text-align: center;">
                    <?php echo $pv['name']; ?><br>
                    <small style="font-size:0.75rem; opacity:0.85;"><?php echo $pv['label']; ?></small>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Two columns: payment method tabs + order summary -->
        <div style="display: grid; grid-template-columns: 1.7fr 1fr; gap: 2rem; align-items: start;">

            <!-- LEFT: Payment tabs -->
            <div>
                <h3 style="color: var(--color-text-primary); font-family: var(--font-display); margin-bottom: 1.25rem;">
                    Ödeme Yöntemi
                </h3>

                <!-- Tab buttons -->
                <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; border-bottom: 1px solid var(--color-border); padding-bottom: 0.75rem;">
                    <?php if (!empty($whopLinks[$plan]) || !empty($whopPlanIds[$plan])): ?>
                        <button type="button" class="btn btn-secondary btn-sm checkout-tab-btn active" data-tab="whop" onclick="switchTab('whop')">
                            🔵 Whop.com
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary btn-sm checkout-tab-btn <?php echo (empty($whopLinks[$plan]) && empty($whopPlanIds[$plan])) ? 'active' : ''; ?>" data-tab="wire" onclick="switchTab('wire')">
                        🏦 Havale / EFT
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm checkout-tab-btn" data-tab="card" onclick="switchTab('card')">
                        💳 Kart (Yakında)
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm checkout-tab-btn" data-tab="paypal" onclick="switchTab('paypal')">
                        🅿 PayPal (Yakında)
                    </button>
                </div>

                <!-- WHOP tab -->
                <?php if (!empty($whopLinks[$plan]) || !empty($whopPlanIds[$plan])): ?>
                <div id="tab-whop" class="checkout-tab-content" style="">
                    <div class="whop-checkout-box">
                        <div class="whop-checkout-head">
                            <span class="whop-dot">W</span>
                            <div>
                                <h4>Whop.com ile Güvenli Ödeme</h4>
                                <p>Kredi kartı, Apple Pay, Google Pay ve daha fazlasını destekleyen Whop altyapısıyla ödeme yapın. Plan aktivasyonu webhook ile otomatik gerçekleşir.</p>
                            </div>
                        </div>

                        <?php if (!empty($whopPlanIds[$plan])): ?>
                            <script>
                                window.tldixWhopComplete = function(planId, receiptId) {
                                    const notice = document.getElementById('whopCompleteNotice');
                                    if (notice) {
                                        notice.hidden = false;
                                        notice.querySelector('strong').textContent = receiptId || planId || 'OK';
                                    }
                                };
                            </script>
                            <div id="whopCompleteNotice" class="alert alert-success" hidden>
                                Ödeme Whop tarafından alındı. Aktivasyon webhook doğrulaması tamamlanınca paneliniz güncellenecek. Referans: <strong></strong>
                            </div>
                            <div
                                id="whop-embedded-checkout"
                                class="whop-embed-frame"
                                data-whop-checkout-plan-id="<?php echo esc($whopPlanIds[$plan]); ?>"
                                data-whop-checkout-return-url="<?php echo esc($whopReturnUrl); ?>"
                                data-whop-checkout-theme="system"
                                data-whop-checkout-theme-accent-color="violet"
                                data-whop-checkout-skip-redirect="true"
                                data-whop-checkout-on-complete="tldixWhopComplete"
                                data-whop-checkout-prefill-email="<?php echo esc($userEmail); ?>"
                                data-whop-checkout-prefill-name="<?php echo esc($username); ?>"
                                data-whop-checkout-style-container-padding-x="0"
                                data-whop-checkout-style-container-padding-y="0"
                            ></div>
                        <?php elseif (!empty($whopCheckoutUrl)): ?>
                            <a href="<?php echo esc($whopCheckoutUrl); ?>" target="_blank" class="btn btn-primary" style="padding: 0.85rem 2.5rem; font-size: 1rem;">
                                🔐 Güvenli Ödeme Yap — <?php echo $pd['label']; ?>
                            </a>
                            <p style="color: var(--color-text-muted); font-size: 0.75rem; margin-top: 1rem;">Sayfa içi ödeme için admin panelden bu paket için Whop Plan ID girin.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- WIRE tab -->
                <div id="tab-wire" class="checkout-tab-content" style="<?php echo (!empty($whopLinks[$plan]) || !empty($whopPlanIds[$plan])) ? 'display:none;' : ''; ?>">
                    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--color-border); border-radius: 12px; padding: 1.75rem;">
                        <h4 style="color: var(--color-text-primary); margin-bottom: 1rem;">🏦 Havale / EFT Banka Bilgileri</h4>
                        <table style="width: 100%; font-size: 0.875rem; border-collapse: collapse;">
                            <tr>
                                <td style="color: var(--color-text-muted); padding: 0.45rem 0; width: 130px;">Banka</td>
                                <td style="color: var(--color-text-primary); font-weight: 600;"><?php echo esc($bankName); ?></td>
                            </tr>
                            <tr>
                                <td style="color: var(--color-text-muted); padding: 0.45rem 0;">Hesap Sahibi</td>
                                <td style="color: var(--color-text-primary); font-weight: 600;"><?php echo esc($bankHolder); ?></td>
                            </tr>
                            <tr>
                                <td style="color: var(--color-text-muted); padding: 0.45rem 0;">IBAN</td>
                                <td><code style="font-family: monospace; color: var(--color-primary); font-size: 0.9rem; letter-spacing: 0.5px;"><?php echo esc($bankIban); ?></code></td>
                            </tr>
                            <?php if ($bankAccNo): ?>
                            <tr>
                                <td style="color: var(--color-text-muted); padding: 0.45rem 0;">Hesap No</td>
                                <td style="color: var(--color-text-secondary);"><?php echo esc($bankAccNo); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td style="color: var(--color-text-muted); padding: 0.45rem 0;">Tutar</td>
                                <td style="color: var(--color-success); font-weight: 700; font-size: 1rem;"><?php echo $pd['label']; ?></td>
                            </tr>
                            <tr>
                                <td style="color: var(--color-text-muted); padding: 0.45rem 0;">Açıklama</td>
                                <td style="color: var(--color-warning); font-weight: 600;"><?php echo esc($username); ?> - <?php echo $pd['name']; ?></td>
                            </tr>
                        </table>
                        <?php if ($bankNotes): ?>
                            <p style="margin-top: 1rem; font-size: 0.82rem; color: var(--color-text-muted); line-height: 1.5; padding: 0.75rem; background: rgba(245,158,11,0.06); border: 1px solid rgba(245,158,11,0.15); border-radius: 6px;">
                                ⚠️ <?php echo esc($bankNotes); ?>
                            </p>
                        <?php endif; ?>

                        <!-- Wire confirmation form -->
                        <form action="<?php echo url('checkout?plan=' . urlencode($plan)); ?>" method="POST" style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--color-border);">
                            <input type="hidden" name="action" value="submit_wire">
                            <p style="color: var(--color-text-primary); font-size: 0.875rem; font-weight: 600; margin-bottom: 0.75rem;">Havaleyi yaptıktan sonra bildirin:</p>
                            <div class="form-group" style="margin-bottom: 0.75rem;">
                                <label style="font-size: 0.8rem; color: var(--color-text-secondary); display: block; margin-bottom: 0.35rem;">Dekont / Referans No *</label>
                                <input type="text" name="wire_reference" placeholder="Dekont no veya işlem numarası" required style="width: 100%; padding: 0.6rem 0.8rem; border-radius: 8px; background: rgba(0,0,0,0.2); border: 1px solid var(--color-border); color: var(--color-text-primary); font-size: 0.875rem;">
                            </div>
                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label style="font-size: 0.8rem; color: var(--color-text-secondary); display: block; margin-bottom: 0.35rem;">Ek Not (isteğe bağlı)</label>
                                <textarea name="wire_notes" placeholder="Ödeme ile ilgili eklemek istediğiniz notlar..." rows="2" style="width: 100%; padding: 0.6rem 0.8rem; border-radius: 8px; background: rgba(0,0,0,0.2); border: 1px solid var(--color-border); color: var(--color-text-primary); font-size: 0.875rem; resize: vertical;"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-full">📩 Ödeme Bildirimini Gönder</button>
                        </form>
                    </div>
                </div>

                <!-- CARD tab (coming soon) -->
                <div id="tab-card" class="checkout-tab-content" style="display:none;">
                    <div style="background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1); border-radius: 12px; padding: 2.5rem; text-align: center; color: var(--color-text-muted);">
                        <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">🚧</div>
                        <p style="font-weight: 600; color: var(--color-text-primary);">Kart ile ödeme yakında!</p>
                        <p style="font-size: 0.85rem; margin-top: 0.5rem;">Stripe entegrasyonu aktif edildiğinde burada görünecek. Şimdilik Whop veya Havale ile devam edebilirsiniz.</p>
                    </div>
                </div>

                <!-- PAYPAL tab (coming soon) -->
                <div id="tab-paypal" class="checkout-tab-content" style="display:none;">
                    <div style="background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1); border-radius: 12px; padding: 2.5rem; text-align: center; color: var(--color-text-muted);">
                        <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">🚧</div>
                        <p style="font-weight: 600; color: var(--color-text-primary);">PayPal yakında!</p>
                        <p style="font-size: 0.85rem; margin-top: 0.5rem;">PayPal entegrasyonu çok yakında aktif olacak.</p>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Order Summary -->
            <div style="border-left: 1px solid var(--color-border); padding-left: 2rem;">
                <h3 style="color: var(--color-text-primary); font-family: var(--font-display); margin-bottom: 1.25rem;">
                    Sipariş Özeti
                </h3>
                <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--color-border); border-radius: 12px; padding: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.75rem;">
                        <span style="color: var(--color-text-muted);">Plan</span>
                        <strong style="color: var(--color-primary);"><?php echo $pd['name']; ?></strong>
                    </div>
                    <?php
                    $features = [
                        'bronze' => ['50 Domain Takibi','CSV Dışa Aktarma','5 Webhook','30 Gün Geçmiş'],
                        'silver' => ['500 Domain Takibi','CSV Dışa Aktarma','50 Webhook','1 Yıl Geçmiş','Öncelikli Kuyruk'],
                        'gold'   => ['Sınırsız Domain','Sınırsız Webhook','Sınırsız Geçmiş','SLA & Premium Destek'],
                    ];
                    foreach ($features[$plan] as $f):
                    ?>
                        <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0; font-size: 0.85rem;">
                            <span style="color: var(--color-success);">✓</span>
                            <span style="color: var(--color-text-secondary);"><?php echo $f; ?></span>
                        </div>
                    <?php endforeach; ?>
                    <hr style="border: 0; border-top: 1px solid var(--color-border); margin: 1rem 0;">
                    <div style="display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: 700;">
                        <span style="color: var(--color-text-primary);">Toplam</span>
                        <span style="color: var(--color-success);"><?php echo $pd['label']; ?></span>
                    </div>
                    <p style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.75rem; text-align: center;">
                        Aylık faturalandırma. İstediğiniz zaman iptal edebilirsiniz.
                    </p>
                </div>

                <!-- Security badges -->
                <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.78rem; color: var(--color-text-muted);">
                        <span>🔒</span><span>SSL ile korunan bağlantı</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.78rem; color: var(--color-text-muted);">
                        <span>✅</span><span>Admin onayı sonrası anında aktivasyon</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.78rem; color: var(--color-text-muted);">
                        <span>📧</span><span>Onay e-postası gönderilir</span>
                    </div>
                </div>

                <!-- Back to plans link -->
                <div style="margin-top: 1.5rem;">
                    <a href="<?php echo url('panel/integrations'); ?>" class="btn btn-secondary btn-sm w-full" style="text-align: center; display: block;">← Planlara Geri Dön</a>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function switchTab(tab) {
    // Hide all tab contents
    document.querySelectorAll('.checkout-tab-content').forEach(el => {
        el.style.display = 'none';
    });
    // Remove active from all buttons
    document.querySelectorAll('.checkout-tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    // Show selected
    const el = document.getElementById('tab-' + tab);
    if (el) el.style.display = 'block';
    const btn = document.querySelector(`.checkout-tab-btn[data-tab="${tab}"]`);
    if (btn) btn.classList.add('active');
}

// Light mode form input fix
(function() {
    const theme = document.documentElement.getAttribute('data-theme');
    if (theme === 'light') {
        document.querySelectorAll('.checkout-page-container input, .checkout-page-container textarea').forEach(el => {
            el.style.background = 'rgba(255,255,255,0.9)';
            el.style.color = '#0f172a';
            el.style.borderColor = 'rgba(0,0,0,0.1)';
        });
    }
})();
</script>
