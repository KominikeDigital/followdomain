<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

global $config, $pdo;

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// ── Handle POST actions ──────────────────────────────────────────────────────
$settingsSaved = false;
$adminError    = $adminError ?? null;

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Save Settings
    if ($action === 'save_settings' && isset($_POST['settings']) && is_array($_POST['settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            $pdo->prepare("DELETE FROM settings WHERE key_name = ?")->execute([$key]);
            $pdo->prepare("INSERT INTO settings (key_name, val_value) VALUES (?, ?)")->execute([$key, $value]);
            $config[$key] = $value;
        }
        $settingsSaved = true;
    }

    // Confirm / reject payment
    if ($action === 'confirm_payment' && !empty($_POST['payment_id'])) {
        try {
            $pid = (int)$_POST['payment_id'];
            $pay = $pdo->prepare("SELECT * FROM payments WHERE id = ?")->execute([$pid]) 
                   ? $pdo->prepare("SELECT * FROM payments WHERE id = ?"): null;
            $pay = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
            $pay->execute([$pid]);
            $payRow = $pay->fetch();
            if ($payRow) {
                $pdo->prepare("UPDATE payments SET status = 'confirmed', confirmed_at = ? WHERE id = ?")
                    ->execute([date('Y-m-d H:i:s'), $pid]);
                $pdo->prepare("UPDATE users SET api_plan = ? WHERE id = ?")
                    ->execute([$payRow['plan'], $payRow['user_id']]);
                $settingsSaved = true;
            }
        } catch (Exception $e) {}
    }
    if ($action === 'reject_payment' && !empty($_POST['payment_id'])) {
        try {
            $pdo->prepare("UPDATE payments SET status = 'rejected' WHERE id = ?")
                ->execute([(int)$_POST['payment_id']]);
            $settingsSaved = true;
        } catch (Exception $e) {}
    }

    // Manual plan change by admin
    if ($action === 'admin_set_plan' && !empty($_POST['user_id']) && !empty($_POST['plan'])) {
        $plan = in_array($_POST['plan'], ['free','bronze','silver','gold']) ? $_POST['plan'] : 'free';
        $pdo->prepare("UPDATE users SET api_plan = ? WHERE id = ?")
            ->execute([$plan, (int)$_POST['user_id']]);
        $settingsSaved = true;
    }

    // Delete user
    if ($action === 'delete_user' && !empty($_POST['user_id'])) {
        $uid = (int)$_POST['user_id'];
        $pdo->prepare("DELETE FROM user_domains WHERE user_id = ?")->execute([$uid]);
        $pdo->prepare("DELETE FROM user_hostings WHERE user_id = ?")->execute([$uid]);
        $pdo->prepare("DELETE FROM payments WHERE user_id = ?")->execute([$uid]);
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
        $settingsSaved = true;
    }
}

// ── Fetch data ───────────────────────────────────────────────────────────────
$totalDomains   = 0;
$totalFollowers = 0;
$totalClicks    = 0;
$totalUsers     = 0;
$members        = [];
$affiliateStats = [];
$recentClicks   = [];
$payments       = [];
$adminDomains   = [];

if ($isAdmin) {
    $totalDomains   = $pdo->query("SELECT COUNT(*) FROM domains")->fetchColumn();
    $totalFollowers = $pdo->query("SELECT COUNT(*) FROM followers")->fetchColumn();
    $totalUsers     = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    try { $adminDomains = $pdo->query("SELECT * FROM domains ORDER BY follower_count DESC LIMIT 50")->fetchAll(); } catch(Exception $e){}
    try {
        $members = $pdo->query("SELECT id, username, email, created_at, api_plan, (SELECT COUNT(*) FROM user_domains WHERE user_id = users.id) AS domain_count FROM users ORDER BY created_at DESC")->fetchAll();
    } catch(Exception $e){ $members = []; }
    try {
        $totalClicks   = $pdo->query("SELECT COUNT(*) FROM affiliate_clicks")->fetchColumn();
        $affiliateStats= $pdo->query("SELECT provider, COUNT(*) as click_count, MAX(clicked_at) as last_clicked FROM affiliate_clicks GROUP BY provider ORDER BY click_count DESC")->fetchAll();
        $recentClicks  = $pdo->query("SELECT ac.id, ac.provider, ac.target_url, ac.ip_address, ac.clicked_at, ac.utm_source, ac.referrer, u.username FROM affiliate_clicks ac LEFT JOIN users u ON ac.user_id = u.id ORDER BY ac.clicked_at DESC LIMIT 50")->fetchAll();
    } catch(Exception $e){}
    try {
        $payments = $pdo->query("SELECT p.*, u.username, u.email FROM payments p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC")->fetchAll();
    } catch(Exception $e){ $payments = []; }
}

// Active tab
$activeTab = $_GET['tab'] ?? 'dashboard';
$validTabs = ['dashboard','general','affiliate','ads','email','email-templates','domains','members','payments','affiliate-stats','integrations'];
if (!in_array($activeTab, $validTabs)) $activeTab = 'dashboard';
?>

<style>
/* ── Admin Panel Layout ── */
.admin-shell {
    display: flex;
    gap: 0;
    min-height: calc(100vh - 130px);
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid var(--color-border);
}

/* Sidebar */
.admin-sidebar {
    width: 220px;
    flex-shrink: 0;
    background: rgba(9, 13, 22, 0.6);
    backdrop-filter: blur(20px);
    border-right: 1px solid var(--color-border);
    padding: 1.5rem 0;
    display: flex;
    flex-direction: column;
}
html[data-theme="light"] .admin-sidebar {
    background: rgba(241, 245, 249, 0.9);
}

.admin-sidebar-logo {
    padding: 0 1.25rem 1.25rem;
    border-bottom: 1px solid var(--color-border);
    margin-bottom: 1rem;
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--color-text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sidebar-section-label {
    padding: 0.4rem 1.25rem;
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--color-text-muted);
    font-weight: 600;
    margin-top: 0.75rem;
}

.sidebar-nav a {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.6rem 1.25rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--color-text-secondary);
    transition: all 0.15s ease;
    border-left: 3px solid transparent;
    text-decoration: none;
}
.sidebar-nav a:hover {
    color: var(--color-text-primary);
    background: rgba(99,102,241,0.06);
}
.sidebar-nav a.active {
    color: var(--color-primary);
    background: rgba(99,102,241,0.1);
    border-left-color: var(--color-primary);
    font-weight: 600;
}
html[data-theme="light"] .sidebar-nav a.active {
    background: rgba(99,102,241,0.08);
}

.sidebar-bottom {
    margin-top: auto;
    padding: 1rem 1.25rem 0;
    border-top: 1px solid var(--color-border);
}

/* Main content */
.admin-main {
    flex: 1;
    padding: 2rem;
    overflow-x: hidden;
    min-width: 0;
}

.admin-tab-pane { display: none; }
.admin-tab-pane.active { display: block; }

/* Stat cards */
.admin-stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.admin-stat-box {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    padding: 1.25rem;
}
html[data-theme="light"] .admin-stat-box {
    background: rgba(255,255,255,0.7);
}
.admin-stat-box .s-label { font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.8px; }
.admin-stat-box .s-value { font-family: var(--font-display); font-size: 2rem; font-weight: 800; color: var(--color-text-primary); }

/* Form section card */
.admin-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--color-border);
    border-radius: 14px;
    padding: 1.75rem;
    margin-bottom: 1.5rem;
}
html[data-theme="light"] .admin-card {
    background: rgba(255,255,255,0.8);
}
.admin-card h3 {
    font-family: var(--font-display);
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--color-text-primary);
    margin-bottom: 1.25rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.admin-card h4 {
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--color-text-muted);
    font-weight: 600;
    margin: 1.5rem 0 0.75rem;
}
.admin-card h4:first-child { margin-top: 0; }

.admin-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
@media (max-width: 700px) { .admin-form-row { grid-template-columns: 1fr; } }

/* Form inputs */
.admin-card .form-group { margin-bottom: 1rem; }
.admin-card label {
    display: block;
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--color-text-secondary);
    margin-bottom: 0.4rem;
}
.admin-card input[type="text"],
.admin-card input[type="email"],
.admin-card input[type="password"],
.admin-card input[type="url"],
.admin-card select,
.admin-card textarea {
    width: 100%;
    padding: 0.6rem 0.8rem;
    border-radius: 8px;
    background: rgba(0,0,0,0.18);
    border: 1px solid var(--color-border);
    color: var(--color-text-primary);
    font-size: 0.875rem;
    font-family: var(--font-sans);
    transition: border-color 0.2s;
}
html[data-theme="light"] .admin-card input,
html[data-theme="light"] .admin-card select,
html[data-theme="light"] .admin-card textarea {
    background: rgba(255,255,255,0.9);
    border-color: rgba(0,0,0,0.1);
    color: #0f172a;
}
.admin-card input:focus, .admin-card select:focus, .admin-card textarea:focus {
    outline: none;
    border-color: var(--color-primary);
}
.admin-card textarea { resize: vertical; min-height: 80px; }

/* Commission badge */
.comm-badge { font-size: 0.72rem; color: var(--color-success); font-weight: 600; margin-left: 0.35rem; }

/* Page header */
.admin-page-header {
    margin-bottom: 1.75rem;
}
.admin-page-header h2 {
    font-family: var(--font-display);
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--color-text-primary);
}
.admin-page-header p {
    color: var(--color-text-muted);
    font-size: 0.85rem;
    margin-top: 0.2rem;
}

/* Responsive sidebar toggle */
.admin-sidebar-toggle {
    display: none;
    background: none;
    border: 1px solid var(--color-border);
    color: var(--color-text-primary);
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    margin-bottom: 1rem;
}
@media (max-width: 900px) {
    .admin-shell { flex-direction: column; }
    .admin-sidebar { width: 100%; border-right: none; border-bottom: 1px solid var(--color-border); padding: 1rem 0 0.5rem; }
    .admin-sidebar-toggle { display: block; }
    .sidebar-nav { display: none; }
    .sidebar-nav.open { display: block; }
}
</style>

<div class="admin-page">
<?php if (!$isAdmin): ?>
    <!-- ADMIN LOGIN FORM -->
    <div class="login-card glass-panel" style="max-width: 420px; margin: 3rem auto;">
        <h2 class="text-center" style="color: var(--color-text-primary);"><?php echo __('admin_title'); ?></h2>
        <p class="text-center" style="color: var(--color-text-muted); margin-bottom: 1.5rem;"><?php echo __('admin_sub'); ?></p>
        <?php if (isset($loginError)): ?>
            <div class="alert alert-error"><?php echo esc($loginError); ?></div>
        <?php endif; ?>
        <form action="" method="POST">
            <input type="hidden" name="login" value="1">
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="adminEmail" style="color: var(--color-text-secondary);"><?php echo __('label_email'); ?></label>
                <input type="email" id="adminEmail" name="email" required placeholder="admin@example.com">
            </div>
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="adminPass" style="color: var(--color-text-secondary);"><?php echo __('label_password'); ?></label>
                <input type="password" id="adminPass" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary w-full"><?php echo __('nav_login'); ?></button>
        </form>
    </div>

<?php else: ?>

    <?php if ($settingsSaved): ?>
        <div class="alert alert-success" style="margin-bottom: 1rem;">✅ İşlem başarıyla tamamlandı.</div>
    <?php endif; ?>
    <?php if ($adminError): ?>
        <div class="alert alert-error" style="margin-bottom: 1rem;"><?php echo esc($adminError); ?></div>
    <?php endif; ?>

    <!-- ADMIN SHELL: Sidebar + Main -->
    <div class="admin-shell">

        <!-- ── LEFT SIDEBAR ────────────────────────────────────── -->
        <aside class="admin-sidebar">
            <div class="admin-sidebar-logo">
                🛡️ Admin Panel
            </div>

            <button class="admin-sidebar-toggle" onclick="document.querySelector('.sidebar-nav').classList.toggle('open')">
                ☰ Menü
            </button>

            <nav class="sidebar-nav">
                <div class="sidebar-section-label">Genel Bakış</div>
                <a href="?tab=dashboard" class="<?php echo $activeTab==='dashboard'?'active':''; ?>">
                    📊 Dashboard
                </a>

                <div class="sidebar-section-label">Ayarlar</div>
                <a href="?tab=general" class="<?php echo $activeTab==='general'?'active':''; ?>">
                    ⚙️ Genel & SEO
                </a>
                <a href="?tab=email" class="<?php echo $activeTab==='email'?'active':''; ?>">
                    📧 E-posta & SMTP
                </a>
                <a href="?tab=email-templates" class="<?php echo $activeTab==='email-templates'?'active':''; ?>">
                    📝 E-posta Şablonları
                </a>
                <a href="?tab=ads" class="<?php echo $activeTab==='ads'?'active':''; ?>">
                    📢 Reklam & Entegrasyon
                </a>

                <div class="sidebar-section-label">Gelir</div>
                <a href="?tab=affiliate" class="<?php echo $activeTab==='affiliate'?'active':''; ?>">
                    🔗 Affiliate URL'leri
                </a>
                <a href="?tab=payments" class="<?php echo $activeTab==='payments'?'active':''; ?>">
                    💳 Ödemeler
                </a>
                <a href="?tab=affiliate-stats" class="<?php echo $activeTab==='affiliate-stats'?'active':''; ?>">
                    📈 Affiliate İstatistik
                </a>

                <div class="sidebar-section-label">Kullanıcı & Domain</div>
                <a href="?tab=members" class="<?php echo $activeTab==='members'?'active':''; ?>">
                    👥 Üyeler
                </a>
                <a href="?tab=domains" class="<?php echo $activeTab==='domains'?'active':''; ?>">
                    🌐 İzlenen Domainler
                </a>
                <a href="?tab=integrations" class="<?php echo $activeTab==='integrations'?'active':''; ?>">
                    🔌 Entegrasyon Kodları
                </a>
            </nav>

            <div class="sidebar-bottom">
                <a href="<?php echo url(''); ?>" class="btn btn-secondary btn-sm w-full" style="margin-bottom: 0.5rem; text-align: center; display: block;">🏠 Siteye Git</a>
                <a href="<?php echo url('manage-secure-panel?logout=1'); ?>" class="btn btn-secondary btn-sm w-full" style="text-align: center; display: block; color: var(--color-error); border-color: rgba(239,68,68,0.3);">🚪 Çıkış Yap</a>
            </div>
        </aside>

        <!-- ── MAIN CONTENT ────────────────────────────────────── -->
        <main class="admin-main">

            <!-- ════════════════════════════════════════════════════
                 TAB: DASHBOARD
            ════════════════════════════════════════════════════ -->
            <div class="admin-tab-pane <?php echo $activeTab==='dashboard'?'active':''; ?>">
                <div class="admin-page-header">
                    <h2>📊 Dashboard</h2>
                    <p>Sistem genel durumu ve hızlı istatistikler</p>
                </div>

                <div class="admin-stats-row">
                    <div class="admin-stat-box">
                        <div class="s-label">İzlenen Domain</div>
                        <div class="s-value"><?php echo (int)$totalDomains; ?></div>
                    </div>
                    <div class="admin-stat-box">
                        <div class="s-label">Toplam Üye</div>
                        <div class="s-value"><?php echo (int)$totalUsers; ?></div>
                    </div>
                    <div class="admin-stat-box">
                        <div class="s-label">Takipçi</div>
                        <div class="s-value"><?php echo (int)$totalFollowers; ?></div>
                    </div>
                    <div class="admin-stat-box">
                        <div class="s-label">Affiliate Tıklama</div>
                        <div class="s-value"><?php echo (int)$totalClicks; ?></div>
                    </div>
                    <div class="admin-stat-box">
                        <div class="s-label">Veritabanı</div>
                        <div class="s-value" style="font-size: 1.4rem;"><?php echo strtoupper(esc($config['db_type'])); ?></div>
                        <?php if ($config['db_type'] === 'sqlite'): ?>
                            <a href="<?php echo url('manage-secure-panel?action=backup_db'); ?>" class="btn btn-secondary btn-sm" style="margin-top: 0.75rem; width: 100%; text-align: center; display: block; font-size: 0.75rem;">💾 Yedek Al</a>
                        <?php endif; ?>
                    </div>
                    <div class="admin-stat-box">
                        <div class="s-label">Bekleyen Ödeme</div>
                        <?php
                        try {
                            $pendingCount = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
                        } catch(Exception $e) { $pendingCount = 0; }
                        ?>
                        <div class="s-value" style="<?php echo $pendingCount > 0 ? 'color: var(--color-warning);' : ''; ?>"><?php echo (int)$pendingCount; ?></div>
                        <?php if ($pendingCount > 0): ?>
                            <a href="?tab=payments" style="font-size: 0.75rem; color: var(--color-warning); display: block; margin-top: 0.5rem;">→ Ödemeleri incele</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="admin-card">
                    <h3>⚡ Hızlı Erişim</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                        <?php foreach(['general'=>'⚙️ Genel Ayarlar','email'=>'📧 SMTP Ayarları','affiliate'=>'🔗 Affiliate URL','payments'=>'💳 Ödemeler','members'=>'👥 Üyeler','domains'=>'🌐 Domainler'] as $t=>$l): ?>
                            <a href="?tab=<?php echo $t; ?>" class="btn btn-secondary btn-sm"><?php echo $l; ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════
                 TAB: GENERAL & SEO
            ════════════════════════════════════════════════════ -->
            <div class="admin-tab-pane <?php echo $activeTab==='general'?'active':''; ?>">
                <div class="admin-page-header">
                    <h2>⚙️ Genel & SEO Ayarları</h2>
                    <p>Site başlığı, açıklaması ve SEO meta bilgileri</p>
                </div>
                <form action="?tab=general" method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    <div class="admin-card">
                        <h3>📝 Site Bilgileri</h3>
                        <div class="form-group">
                            <label><?php echo __('label_site_title'); ?></label>
                            <input type="text" name="settings[site_title]" value="<?php echo esc($config['site_title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?php echo __('label_site_description'); ?></label>
                            <textarea name="settings[site_description]" rows="3" required><?php echo esc($config['site_description']); ?></textarea>
                        </div>
                    </div>
                    <div class="admin-card">
                        <h3>🔍 SEO Ayarları</h3>
                        <div class="admin-form-row">
                            <div class="form-group">
                                <label>SEO Anahtar Kelimeler (Keywords)</label>
                                <input type="text" name="settings[seo_keywords]" value="<?php echo esc($config['seo_keywords'] ?? ''); ?>" placeholder="domain, hosting, whois">
                            </div>
                            <div class="form-group">
                                <label>SEO Yazar (Author)</label>
                                <input type="text" name="settings[seo_author]" value="<?php echo esc($config['seo_author'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>OG Image URL (Sosyal Medya Önizleme Görseli)</label>
                            <input type="text" name="settings[seo_og_image]" value="<?php echo esc($config['seo_og_image'] ?? ''); ?>" placeholder="https://yourdomain.com/assets/images/og-image.png">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">💾 Kaydet</button>
                </form>
            </div>

            <!-- ════════════════════════════════════════════════════
                 TAB: EMAIL & SMTP
            ════════════════════════════════════════════════════ -->
            <div class="admin-tab-pane <?php echo $activeTab==='email'?'active':''; ?>">
                <div class="admin-page-header">
                    <h2>📧 E-posta & SMTP Ayarları</h2>
                    <p>SMTP yapılandırması, test maili ve bildirim ayarları</p>
                </div>
                <form action="?tab=email" method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    <div class="admin-card">
                        <h3>📮 SMTP Yapılandırması</h3>
                        <div class="form-group">
                            <label>E-posta Servis Tipi</label>
                            <select name="settings[email_use_smtp]">
                                <option value="1" <?php echo ((int)($config['email_use_smtp'] ?? 0) === 1) ? 'selected' : ''; ?>>SMTP Servisi</option>
                                <option value="0" <?php echo ((int)($config['email_use_smtp'] ?? 0) === 0) ? 'selected' : ''; ?>>PHP mail() (Varsayılan)</option>
                            </select>
                        </div>
                        <div class="admin-form-row">
                            <div class="form-group">
                                <label>SMTP Sunucusu (Host)</label>
                                <input type="text" name="settings[smtp_host]" value="<?php echo esc($config['smtp_host'] ?? ''); ?>" placeholder="smtp.gmail.com">
                            </div>
                            <div class="form-group">
                                <label>SMTP Port</label>
                                <input type="text" name="settings[smtp_port]" value="<?php echo esc($config['smtp_port'] ?? '587'); ?>" placeholder="587">
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <div class="form-group">
                                <label>SMTP Kullanıcı Adı</label>
                                <input type="text" name="settings[smtp_user]" value="<?php echo esc($config['smtp_user'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>SMTP Şifresi</label>
                                <input type="password" name="settings[smtp_pass]" value="<?php echo esc($config['smtp_pass'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <div class="form-group">
                                <label>Gönderici E-posta</label>
                                <input type="email" name="settings[smtp_from_email]" value="<?php echo esc($config['smtp_from_email'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Gönderici Adı</label>
                                <input type="text" name="settings[smtp_from_name]" value="<?php echo esc($config['smtp_from_name'] ?? 'TLDix'); ?>">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top: 1rem;">
                            <label>Admin Bildirim E-posta Adresi</label>
                            <input type="email" name="settings[admin_notification_email]" value="<?php echo esc($config['admin_notification_email'] ?? ''); ?>" placeholder="admin@tldix.com">
                            <span style="font-size: 0.8rem; color: var(--color-text-muted); display: block; margin-top: 0.25rem;">Yeni üye kayıtları ve şifre sıfırlama taleplerinde bu adrese bildirim gönderilir.</span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">💾 Kaydet</button>
                </form>

                <!-- Test Email -->
                <div class="admin-card" style="margin-top: 1.5rem;">
                    <h3>🧪 SMTP Live Connection Tester</h3>
                    <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 1rem;">
                        Test connection logs in real-time using the input values above (without needing to save first).
                    </p>
                    
                    <div style="display: flex; gap: 0.75rem; align-items: center; margin-bottom: 1rem;">
                        <input type="email" id="test_smtp_target" placeholder="test@example.com" value="<?php echo esc($config['smtp_from_email'] ?? ''); ?>" style="flex: 1; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid var(--color-border); background: rgba(255,255,255,0.03); color: var(--color-text-primary);">
                        <button type="button" class="btn btn-primary btn-sm" id="btn_test_smtp" onclick="runLiveSmtpTest()">📤 Run Test</button>
                    </div>

                    <!-- Collapsible Terminal Output -->
                    <div id="smtp_test_log_container" style="display: none; border-radius: 8px; overflow: hidden; border: 1px solid var(--color-border); margin-top: 1rem;">
                        <div style="background: rgba(255,255,255,0.05); padding: 0.5rem 1rem; font-size: 0.78rem; font-weight: 700; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
                            <span>SMTP Socket Communication Logs</span>
                            <button type="button" onclick="document.getElementById('smtp_test_log_body').style.display = document.getElementById('smtp_test_log_body').style.display === 'none' ? 'block' : 'none';" style="background:none; border:none; color:var(--color-primary); cursor:pointer; font-weight:700;">Toggle Logs</button>
                        </div>
                        <pre id="smtp_test_log_body" style="margin: 0; padding: 1rem; background: #000; color: #00ff00; font-family: monospace; font-size: 0.85rem; line-height: 1.4; overflow-x: auto; white-space: pre-wrap; height: 250px; text-align: left;"></pre>
                    </div>
                </div>

                <script>
                function runLiveSmtpTest() {
                    const targetEmail = document.getElementById('test_smtp_target').value.trim();
                    if (!targetEmail) {
                        alert('Please enter a target email address.');
                        return;
                    }

                    const host = document.querySelector('input[name="settings[smtp_host]"]').value.trim();
                    const port = document.querySelector('input[name="settings[smtp_port]"]').value.trim();
                    const user = document.querySelector('input[name="settings[smtp_user]"]').value.trim();
                    const pass = document.querySelector('input[name="settings[smtp_pass]"]').value.trim();
                    const fromEmail = document.querySelector('input[name="settings[smtp_from_email]"]').value.trim();
                    const fromName = document.querySelector('input[name="settings[smtp_from_name]"]').value.trim();

                    const btn = document.getElementById('btn_test_smtp');
                    const container = document.getElementById('smtp_test_log_container');
                    const body = document.getElementById('smtp_test_log_body');

                    btn.disabled = true;
                    btn.innerText = 'Testing...';
                    container.style.display = 'block';
                    body.style.display = 'block';
                    body.textContent = 'Initializing test socket...\n';

                    const formData = new FormData();
                    formData.append('smtp_host', host);
                    formData.append('smtp_port', port);
                    formData.append('smtp_user', user);
                    formData.append('smtp_pass', pass);
                    formData.append('smtp_from_email', fromEmail);
                    formData.append('smtp_from_name', fromName);
                    formData.append('test_target_email', targetEmail);

                    fetch('<?php echo url("manage-secure-panel/test-smtp-live"); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        body.textContent = data.log;
                        if (data.success) {
                            body.textContent += '\n\n🎉 TEST SUCCESSFUL! SMTP settings are correctly verified.';
                            body.style.color = '#00ff00';
                        } else {
                            body.textContent += '\n\n❌ TEST FAILED. Check DNS/host, SSL port, port blocks, or credentials.';
                            body.style.color = '#ff3333';
                        }
                    })
                    .catch(err => {
                        body.textContent += '\n\n❌ Error communicating with the test endpoint: ' + err;
                        body.style.color = '#ff3333';
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerText = '📤 Run Test';
                    });
                }
                </script>
            </div>

            <!-- ════════════════════════════════════════════════════
                 TAB: EMAIL TEMPLATES
            ════════════════════════════════════════════════════ -->
            <div class="admin-tab-pane <?php echo $activeTab==='email-templates'?'active':''; ?>">
                <div class="admin-page-header">
                    <h2>📝 E-posta Şablonları Yönetimi</h2>
                    <p>Sistem genelinde gönderilen tüm otomatik e-postaların içeriklerini HTML formatında özelleştirin.</p>
                </div>
                
                <?php if ($settingsSaved && $activeTab === 'email-templates'): ?>
                    <div class="admin-alert success" style="margin-bottom:1.5rem; background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.3); color:#10b981; padding:0.75rem 1rem; border-radius:8px;">💾 Şablon başarıyla güncellendi!</div>
                <?php endif; ?>
                
                <div class="admin-card" style="margin-bottom: 2rem;">
                    <h3>Şablon Seçimi</h3>
                    <div class="form-group">
                        <label>Özelleştirilecek E-posta Şablonu</label>
                        <select id="tpl_selector" onchange="switchTemplate(this.value)">
                            <option value="mail_tpl_user_register">Hoş Geldiniz E-postası (Yeni Üye)</option>
                            <option value="mail_tpl_user_verify">E-posta Doğrulama E-postası (Yeni Üye)</option>
                            <option value="mail_tpl_user_forgot">Şifre Sıfırlama E-postası (Kullanıcı)</option>
                            <option value="mail_tpl_admin_register">Yeni Üye Bildirimi (Yönetici)</option>
                            <option value="mail_tpl_admin_forgot">Şifre Sıfırlama Bildirimi (Yönetici)</option>
                            <option value="mail_tpl_domain_expiry">Domain Süresi Sona Eriyor Hatırlatması</option>
                            <option value="mail_tpl_hosting_expiry">Hosting Süresi Sona Eriyor Hatırlatması</option>
                        </select>
                    </div>
                </div>

                <form action="?tab=email-templates" method="POST" id="template_form">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <!-- User Welcome Email Template -->
                    <div class="tpl-editor-pane" id="pane_mail_tpl_user_register">
                        <div class="admin-card">
                            <h3>👋 Hoş Geldiniz E-postası (Yeni Üye)</h3>
                            <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 1rem;">
                                Kullanıcı kayıt işlemini tamamladığında gönderilir.<br>
                                <strong>Kullanılabilir Değişkenler:</strong> <code>{username}</code> (Kullanıcı Adı), <code>{login_url}</code> (Giriş Sayfası Bağlantısı)
                            </p>
                            <div class="form-group">
                                <label>HTML İçerik (Body)</label>
                                <textarea name="settings[mail_tpl_user_register]" rows="15" class="code-editor"><?php 
                                    $default = '<h2>Hoş Geldiniz, {username}!</h2><p>TLDix platformuna başarıyla üye oldunuz. Artık alan adlarınızı ve barındırma (hosting) sürelerinizi tek bir noktadan güvenle takip edebilirsiniz.</p><p>Takip listenize yeni alan adları eklemek için hemen kullanıcı panelinize giriş yapabilirsiniz:</p><p><a href="{login_url}" class="btn">Panel Girişi Yap</a></p><p>Herhangi bir sorunuz olursa bizimle iletişime geçebilirsiniz.</p>';
                                    echo esc($config['mail_tpl_user_register'] ?? $default); 
                                ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">💾 Şablonu Kaydet</button>
                        </div>
                    </div>

                    <!-- User Email Verification Template -->
                    <div class="tpl-editor-pane" id="pane_mail_tpl_user_verify" style="display:none;">
                        <div class="admin-card">
                            <h3>✉️ E-posta Doğrulama E-postası (Yeni Üye)</h3>
                            <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 1rem;">
                                Kullanıcı yeni kayıt oluşturduğunda doğrulaması için gönderilir.<br>
                                <strong>Kullanılabilir Değişkenler:</strong> <code>{username}</code> (Kullanıcı Adı), <code>{verify_url}</code> (E-posta Doğrulama Bağlantısı)
                            </p>
                            <div class="form-group">
                                <label>HTML İçerik (Body)</label>
                                <textarea name="settings[mail_tpl_user_verify]" rows="15" class="code-editor"><?php 
                                    $default = '<h2>E-posta Adresinizi Doğrulayın</h2><p>Merhaba {username},</p><p>TLDix platformuna başarıyla üye oldunuz. Hesabınızı aktifleştirmek ve hizmetleri kullanmaya başlamak için lütfen aşağıdaki bağlantıya tıklayarak e-posta adresinizi doğrulayın:</p><p><a href="{verify_url}" class="btn">E-postamı Doğrula</a></p><p>Bağlantı çalışmıyorsa aşağıdaki URL\'yi kopyalayıp tarayıcınıza yapıştırabilirsiniz:</p><p>{verify_url}</p>';
                                    echo esc($config['mail_tpl_user_verify'] ?? $default); 
                                ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">💾 Şablonu Kaydet</button>
                        </div>
                    </div>

                    <!-- User Password Reset Email Template -->
                    <div class="tpl-editor-pane" id="pane_mail_tpl_user_forgot" style="display:none;">
                        <div class="admin-card">
                            <h3>🔑 Şifre Sıfırlama E-postası (Kullanıcı)</h3>
                            <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 1rem;">
                                Şifresini unutan kullanıcılara geçici şifre iletilirken gönderilir.<br>
                                <strong>Kullanılabilir Değişkenler:</strong> <code>{username}</code> (Kullanıcı Adı), <code>{temp_password}</code> (Geçici Şifre), <code>{login_url}</code> (Giriş Sayfası Bağlantısı)
                            </p>
                            <div class="form-group">
                                <label>HTML İçerik (Body)</label>
                                <textarea name="settings[mail_tpl_user_forgot]" rows="15" class="code-editor"><?php 
                                    $default = '<h2>Şifre Sıfırlama Talebi</h2><p>Hesabınız için şifre sıfırlama talebinde bulundunuz. Sizin için geçici bir şifre oluşturuldu:</p><div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; font-size: 18px; font-weight: bold; text-align: center; border: 1px dashed rgba(255,255,255,0.2); color: #6366f1; margin: 20px 0;">{temp_password}</div><p>Lütfen bu şifreyi kullanarak sisteme giriş yapın ve profil ayarlarınızdan şifrenizi hemen güncelleyin:</p><p><a href="{login_url}" class="btn">Giriş Yap</a></p><p>Bu talebi siz yapmadıysanız lütfen bu e-postayı dikkate almayın.</p>';
                                    echo esc($config['mail_tpl_user_forgot'] ?? $default); 
                                ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">💾 Şablonu Kaydet</button>
                        </div>
                    </div>

                    <!-- Admin New User Alert Email Template -->
                    <div class="tpl-editor-pane" id="pane_mail_tpl_admin_register" style="display:none;">
                        <div class="admin-card">
                            <h3>👥 Yeni Üye Bildirimi (Yönetici)</h3>
                            <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 1rem;">
                                Yeni bir üye kaydolduğunda belirlenen admin bildirim e-postasına gönderilir.<br>
                                <strong>Kullanılabilir Değişkenler:</strong> <code>{username}</code> (Yeni Üyenin Adı), <code>{email}</code> (Yeni Üyenin E-postası), <code>{date}</code> (Kayıt Tarihi)
                            </p>
                            <div class="form-group">
                                <label>HTML İçerik (Body)</label>
                                <textarea name="settings[mail_tpl_admin_register]" rows="15" class="code-editor"><?php 
                                    $default = '<h2>Yeni Üye Kaydı Bildirimi</h2><p>Sisteminizde yeni bir kullanıcı başarıyla kaydoldu:</p><ul><li><strong>Kullanıcı Adı:</strong> {username}</li><li><strong>E-posta:</strong> {email}</li><li><strong>Kayıt Tarihi:</strong> {date}</li></ul><p>Kullanıcı detaylarını incelemek için yönetici panelinizi ziyaret edebilirsiniz.</p>';
                                    echo esc($config['mail_tpl_admin_register'] ?? $default); 
                                ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">💾 Şablonu Kaydet</button>
                        </div>
                    </div>

                    <!-- Admin Forgot Password Alert Email Template -->
                    <div class="tpl-editor-pane" id="pane_mail_tpl_admin_forgot" style="display:none;">
                        <div class="admin-card">
                            <h3>🔒 Şifre Sıfırlama Bildirimi (Yönetici)</h3>
                            <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 1rem;">
                                Bir kullanıcı şifre sıfırlama işlemi gerçekleştirdiğinde admin bildirim e-postasına gönderilir.<br>
                                <strong>Kullanılabilir Değişkenler:</strong> <code>{username}</code> (Kullanıcı Adı), <code>{email}</code> (Kullanıcı E-postası), <code>{temp_password}</code> (Geçici Şifre), <code>{login_url}</code> (Giriş Sayfası Bağlantısı), <code>{date}</code> (Tarih)
                            </p>
                            <div class="form-group">
                                <label>HTML İçerik (Body)</label>
                                <textarea name="settings[mail_tpl_admin_forgot]" rows="15" class="code-editor"><?php 
                                    $default = '<h2>Şifre Sıfırlama Bildirimi</h2><p>Aşağıdaki kullanıcı şifre sıfırlama talebinde bulundu. Kullanıcıya gönderilen geçici şifre ve giriş bağlantısı aşağıdadır:</p><ul><li><strong>Kullanıcı Adı:</strong> {username}</li><li><strong>E-posta:</strong> {email}</li><li><strong>Geçici Şifre:</strong> <code>{temp_password}</code></li><li><strong>Giriş Bağlantısı:</strong> <a href="{login_url}">{login_url}</a></li><li><strong>Tarih:</strong> {date}</li></ul><p>Güvenlik için kullanıcı giriş yaptıktan sonra şifresini profil ayarlarından değiştirmelidir.</p>';
                                    echo esc($config['mail_tpl_admin_forgot'] ?? $default); 
                                ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">💾 Şablonu Kaydet</button>
                        </div>
                    </div>

                    <!-- Domain Expiry Alert Email Template -->
                    <div class="tpl-editor-pane" id="pane_mail_tpl_domain_expiry" style="display:none;">
                        <div class="admin-card">
                            <h3>⏰ Domain Süresi Sona Eriyor Hatırlatması</h3>
                            <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 1rem;">
                                Takip edilen bir alan adının süresi sona yaklaştığında kullanıcıya gönderilir.<br>
                                <strong>Kullanılabilir Değişkenler:</strong> <code>{domain_name}</code> (Alan Adı), <code>{expiry_date}</code> (Son Geçerlilik Tarihi), <code>{days_left}</code> (Kalan Gün Sayısı), <code>{panel_url}</code> (Panel Domain Detay Bağlantısı)
                            </p>
                            <div class="form-group">
                                <label>HTML İçerik (Body)</label>
                                <textarea name="settings[mail_tpl_domain_expiry]" rows="15" class="code-editor"><?php 
                                    $default = '<h2>Domain Süresi Sona Eriyor!</h2><p>Takip listenizdeki <strong>{domain_name}</strong> alan adınızın süresi yakında doluyor.</p><ul><li><strong>Alan Adı:</strong> {domain_name}</li><li><strong>Bitiş Tarihi:</strong> {expiry_date}</li><li><strong>Kalan Gün:</strong> {days_left}</li></ul><p>Alan adınızı kaybetmemek ve kesintisiz hizmet almaya devam etmek için hemen yenileme işlemlerini yapmanızı öneririz.</p><p><a href="{panel_url}" class="btn">Domain Listeme Git</a></p>';
                                    echo esc($config['mail_tpl_domain_expiry'] ?? $default); 
                                ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">💾 Şablonu Kaydet</button>
                        </div>
                    </div>

                    <!-- Hosting Expiry Alert Email Template -->
                    <div class="tpl-editor-pane" id="pane_mail_tpl_hosting_expiry" style="display:none;">
                        <div class="admin-card">
                            <h3>🖥️ Hosting Süresi Sona Eriyor Hatırlatması</h3>
                            <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 1rem;">
                                Takip edilen bir hosting hizmetinin süresi sona yaklaştığında kullanıcıya gönderilir.<br>
                                <strong>Kullanılabilir Değişkenler:</strong> <code>{domain_name}</code> (Alan Adı), <code>{hosting_provider}</code> (Hosting Sağlayıcı Adı), <code>{expiry_date}</code> (Bitiş Tarihi), <code>{days_left}</code> (Kalan Gün), <code>{panel_url}</code> (Panel Hosting Sayfası Bağlantısı)
                            </p>
                            <div class="form-group">
                                <label>HTML İçerik (Body)</label>
                                <textarea name="settings[mail_tpl_hosting_expiry]" rows="15" class="code-editor"><?php 
                                    $default = '<h2>Hosting Hizmet Süresi Sona Eriyor!</h2><p>Takip listenizdeki <strong>{domain_name}</strong> alan adına ait hosting (barındırma) paketinizin süresi yakında doluyor.</p><ul><li><strong>Hizmet Sunucusu:</strong> {hosting_provider}</li><li><strong>Bitiş Tarihi:</strong> {expiry_date}</li><li><strong>Kalan Gün:</strong> {days_left}</li></ul><p>Web sitenizin yayınının kesilmesini önlemek için hosting paketinizi yenilemeyi unutmayın.</p><p><a href="{panel_url}" class="btn">Hosting Listeme Git</a></p>';
                                    echo esc($config['mail_tpl_hosting_expiry'] ?? $default); 
                                ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">💾 Şablonu Kaydet</button>
                        </div>
                    </div>
                </form>

                <script>
                function switchTemplate(val) {
                    document.querySelectorAll('.tpl-editor-pane').forEach(el => el.style.display = 'none');
                    const targetPane = document.getElementById('pane_' + val);
                    if (targetPane) targetPane.style.display = 'block';
                    
                    // Auto update action tab parameter on selector change
                    const urlParams = new URLSearchParams(window.location.search);
                    urlParams.set('tab', 'email-templates');
                    urlParams.set('sub', val);
                    window.history.replaceState({}, '', '?' + urlParams.toString());
                }
                
                // On load, activate selected template if any
                window.addEventListener('DOMContentLoaded', () => {
                    const urlParams = new URLSearchParams(window.location.search);
                    const sub = urlParams.get('sub');
                    if (sub) {
                        const sel = document.getElementById('tpl_selector');
                        if (sel) {
                            sel.value = sub;
                            switchTemplate(sub);
                        }
                    }
                });
                </script>
            </div>

            <!-- ════════════════════════════════════════════════════
                 TAB: ADS & INTEGRATIONS
            ════════════════════════════════════════════════════ -->
            <div class="admin-tab-pane <?php echo $activeTab==='ads'?'active':''; ?>">
                <div class="admin-page-header">
                    <h2>📢 Reklam & Entegrasyon Kodları</h2>
                    <p>Google Analytics, Search Console, AdSense ve diğer servis entegrasyonları</p>
                </div>
                <form action="?tab=ads" method="POST">
                    <input type="hidden" name="action" value="save_settings">

                    <div class="admin-card">
                        <h3>🔍 Google Servisleri</h3>
                        <div class="form-group">
                            <label>Google Search Console — Doğrulama Meta Tag</label>
                            <input type="text" name="settings[google_search_console]" value="<?php echo esc($config['google_search_console'] ?? ''); ?>" placeholder='&lt;meta name="google-site-verification" content="ABC123..." /&gt;'>
                            <small style="color: var(--color-text-muted); font-size: 0.75rem;">Search Console → Ayarlar → Mülk sahipliği doğrulama → HTML etiketi'nden alın.</small>
                        </div>
                        <div class="form-group">
                            <label>Google Analytics 4 — Ölçüm ID (G-XXXXXXXX)</label>
                            <input type="text" name="settings[google_analytics_id]" value="<?php echo esc($config['google_analytics_id'] ?? ''); ?>" placeholder="G-XXXXXXXXXX">
                        </div>
                        <div class="form-group">
                            <label>Google Tag Manager — Container ID (GTM-XXXXXXX)</label>
                            <input type="text" name="settings[google_tag_manager]" value="<?php echo esc($config['google_tag_manager'] ?? ''); ?>" placeholder="GTM-XXXXXXX">
                        </div>
                        <div class="form-group">
                            <label>Google AdSense — Publisher ID (pub-XXXXXXX)</label>
                            <input type="text" name="settings[google_adsense_id]" value="<?php echo esc($config['google_adsense_id'] ?? ''); ?>" placeholder="pub-XXXXXXXXXXXXXXXX">
                        </div>
                    </div>

                    <div class="admin-card">
                        <h3>📊 Diğer Analitik</h3>
                        <div class="form-group">
                            <label>Bing Webmaster — Doğrulama Kodu</label>
                            <input type="text" name="settings[bing_verification]" value="<?php echo esc($config['bing_verification'] ?? ''); ?>" placeholder="XXXXXXXXXXXXXXXXXXXX">
                        </div>
                        <div class="form-group">
                            <label>Cloudflare Web Analytics — Beacon Token</label>
                            <input type="text" name="settings[cf_analytics_token]" value="<?php echo esc($config['cf_analytics_token'] ?? ''); ?>" placeholder="...">
                        </div>
                        <div class="form-group">
                            <label>&lt;head&gt; Özel Script (Custom Head Code)</label>
                            <textarea name="settings[custom_head_code]" rows="5" placeholder="<!-- Buraya &lt;head&gt; içine eklenecek kodları girin -->"><?php echo esc($config['custom_head_code'] ?? ''); ?></textarea>
                            <small style="color: var(--color-text-muted); font-size: 0.75rem;">Bu alan tüm sayfaların &lt;head&gt; bölümüne eklenir.</small>
                        </div>
                    </div>

                    <div class="admin-card">
                        <h3>💰 Reklam Alanları (AdSense Script)</h3>
                        <div class="form-group">
                            <label>Reklam Gösterim Durumu</label>
                            <select name="settings[ad_status]">
                                <option value="on" <?php echo (($config['ad_status'] ?? 'off') === 'on') ? 'selected' : ''; ?>>Açık (On)</option>
                                <option value="off" <?php echo (($config['ad_status'] ?? 'off') === 'off') ? 'selected' : ''; ?>>Kapalı (Off)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo __('label_ad_header'); ?></label>
                            <textarea name="settings[ad_header]" rows="4"><?php echo esc($config['ad_header']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label><?php echo __('label_ad_sidebar'); ?></label>
                            <textarea name="settings[ad_sidebar]" rows="4"><?php echo esc($config['ad_sidebar']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label><?php echo __('label_ad_footer'); ?></label>
                            <textarea name="settings[ad_footer]" rows="4"><?php echo esc($config['ad_footer']); ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">💾 Kaydet</button>
                </form>
            </div>

            <!-- ════════════════════════════════════════════════════
                 TAB: AFFILIATE URLs
            ════════════════════════════════════════════════════ -->
            <div class="admin-tab-pane <?php echo $activeTab==='affiliate'?'active':''; ?>">
                <div class="admin-page-header">
                    <h2>🔗 Affiliate URL Yönetimi</h2>
                    <p>Tüm affiliate bağlantılarınızı buradan düzenleyin. Tıklamalar otomatik takip edilir.</p>
                </div>
                <form action="?tab=affiliate" method="POST">
                    <input type="hidden" name="action" value="save_settings">

                    <div class="admin-card">
                        <h3>🏷️ Domain Kaydediciler</h3>
                        <div class="admin-form-row">
                            <div class="form-group">
                                <label>Namecheap</label>
                                <input type="url" name="settings[affiliate_namecheap]" value="<?php echo esc($config['affiliate_namecheap'] ?? ''); ?>" placeholder="https://namecheap.com/?aff=...">
                            </div>
                            <div class="form-group">
                                <label>GoDaddy <span class="comm-badge">%15-30</span></label>
                                <input type="url" name="settings[affiliate_godaddy]" value="<?php echo esc($config['affiliate_godaddy'] ?? ''); ?>" placeholder="https://godaddy.com/?isc=...">
                            </div>
                            <div class="form-group">
                                <label>NameSilo</label>
                                <input type="url" name="settings[affiliate_namesilo]" value="<?php echo esc($config['affiliate_namesilo'] ?? ''); ?>" placeholder="https://namesilo.com/?rid=...">
                            </div>
                            <div class="form-group">
                                <label>Porkbun</label>
                                <input type="url" name="settings[affiliate_porkbun]" value="<?php echo esc($config['affiliate_porkbun'] ?? ''); ?>" placeholder="https://porkbun.com/?aff=...">
                            </div>
                            <div class="form-group">
                                <label>Dynadot</label>
                                <input type="url" name="settings[affiliate_dynadot]" value="<?php echo esc($config['affiliate_dynadot'] ?? ''); ?>" placeholder="https://dynadot.com/?aff=...">
                            </div>
                            <div class="form-group">
                                <label>Spaceship</label>
                                <input type="url" name="settings[affiliate_spaceship]" value="<?php echo esc($config['affiliate_spaceship'] ?? ''); ?>" placeholder="https://spaceship.com/?aff=...">
                            </div>
                            <div class="form-group">
                                <label>DomainNameAPI</label>
                                <input type="url" name="settings[affiliate_domainnameapi]" value="<?php echo esc($config['affiliate_domainnameapi'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="admin-card">
                        <h3>🏢 Web Hosting <span style="font-size:0.75rem; color:var(--color-success); font-weight:500;">(Yüksek Komisyon)</span></h3>
                        <div class="admin-form-row">
                            <div class="form-group">
                                <label>Hostinger</label>
                                <input type="url" name="settings[affiliate_hostinger]" value="<?php echo esc($config['affiliate_hostinger'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Bluehost</label>
                                <input type="url" name="settings[affiliate_bluehost]" value="<?php echo esc($config['affiliate_bluehost'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>SiteGround <span class="comm-badge">$50-75/satış</span></label>
                                <input type="url" name="settings[affiliate_siteground]" value="<?php echo esc($config['affiliate_siteground'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Kinsta <span class="comm-badge">$50-500/satış</span></label>
                                <input type="url" name="settings[affiliate_kinsta]" value="<?php echo esc($config['affiliate_kinsta'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>WP Engine <span class="comm-badge">$200+/satış</span></label>
                                <input type="url" name="settings[affiliate_wpengine]" value="<?php echo esc($config['affiliate_wpengine'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>InterServer <span class="comm-badge">$100+/satış</span></label>
                                <input type="url" name="settings[affiliate_interserver]" value="<?php echo esc($config['affiliate_interserver'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="admin-card">
                        <h3>🔐 SSL Sertifikaları</h3>
                        <div class="admin-form-row">
                            <div class="form-group">
                                <label>Namecheap SSL</label>
                                <input type="url" name="settings[affiliate_namecheap_ssl]" value="<?php echo esc($config['affiliate_namecheap_ssl'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>SSLs.com</label>
                                <input type="url" name="settings[affiliate_ssls]" value="<?php echo esc($config['affiliate_ssls'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>SSL Dragon <span class="comm-badge">%30+</span></label>
                                <input type="url" name="settings[affiliate_ssldragon]" value="<?php echo esc($config['affiliate_ssldragon'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="admin-card">
                        <h3>🏛️ Domain Satış Platformları</h3>
                        <div class="admin-form-row">
                            <div class="form-group">
                                <label>Afternic (GoDaddy)</label>
                                <input type="url" name="settings[affiliate_afternic]" value="<?php echo esc($config['affiliate_afternic'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Sedo</label>
                                <input type="url" name="settings[affiliate_sedo]" value="<?php echo esc($config['affiliate_sedo'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Dan.com</label>
                                <input type="url" name="settings[affiliate_dan]" value="<?php echo esc($config['affiliate_dan'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Atom (Squadhelp)</label>
                                <input type="url" name="settings[affiliate_atom]" value="<?php echo esc($config['affiliate_atom'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Dynadot Marketplace</label>
                                <input type="url" name="settings[affiliate_dynadot_mkt]" value="<?php echo esc($config['affiliate_dynadot_mkt'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">💾 Kaydet</button>
                </form>
            </div>

            <!-- ════════════════════════════════════════════════════
                 TAB: PAYMENTS
            ════════════════════════════════════════════════════ -->
            <div class="admin-tab-pane <?php echo $activeTab==='payments'?'active':''; ?>">
                <div class="admin-page-header">
                    <h2>💳 Ödeme Yönetimi</h2>
                    <p>Havale/EFT banka bilgileri, Whop entegrasyonu ve ödeme kayıtları</p>
                </div>

                <!-- Bank Transfer Settings -->
                <form action="?tab=payments" method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    <div class="admin-card">
                        <h3>🏦 Havale / EFT Banka Bilgileri</h3>
                        <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 1rem;">Bu bilgiler kullanıcılara ödeme sayfasında gösterilir.</p>
                        <div class="admin-form-row">
                            <div class="form-group">
                                <label>Banka Adı</label>
                                <input type="text" name="settings[bank_name]" value="<?php echo esc($config['bank_name'] ?? ''); ?>" placeholder="Örn: Ziraat Bankası">
                            </div>
                            <div class="form-group">
                                <label>Hesap Sahibi</label>
                                <input type="text" name="settings[bank_account_name]" value="<?php echo esc($config['bank_account_name'] ?? ''); ?>" placeholder="Ad Soyad / Şirket">
                            </div>
                            <div class="form-group">
                                <label>IBAN</label>
                                <input type="text" name="settings[bank_iban]" value="<?php echo esc($config['bank_iban'] ?? ''); ?>" placeholder="TR00 0000 0000 0000 0000 0000 00">
                            </div>
                            <div class="form-group">
                                <label>Hesap No</label>
                                <input type="text" name="settings[bank_account_no]" value="<?php echo esc($config['bank_account_no'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Şube Kodu</label>
                                <input type="text" name="settings[bank_branch_code]" value="<?php echo esc($config['bank_branch_code'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Para Birimi</label>
                                <select name="settings[bank_currency]">
                                    <option value="TRY" <?php echo ($config['bank_currency'] ?? 'TRY') === 'TRY' ? 'selected':'' ?>>TRY (Türk Lirası)</option>
                                    <option value="USD" <?php echo ($config['bank_currency'] ?? '') === 'USD' ? 'selected':'' ?>>USD</option>
                                    <option value="EUR" <?php echo ($config['bank_currency'] ?? '') === 'EUR' ? 'selected':'' ?>>EUR</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Açıklama / Notlar</label>
                            <textarea name="settings[bank_notes]" rows="2" placeholder="Havale açıklamasına kullanıcı adını yazmalarını isteyin..."><?php echo esc($config['bank_notes'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="admin-card">
                        <h3>🔵 Whop.com Entegrasyonu</h3>
                        <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 1rem;">Whop.com üzerinden ödeme almak için API anahtarınızı ve ürün linklerinizi girin.</p>
                        <div class="admin-form-row">
                            <div class="form-group">
                                <label>Whop API Key</label>
                                <input type="password" name="settings[whop_api_key]" value="<?php echo esc($config['whop_api_key'] ?? ''); ?>" placeholder="whop_...">
                            </div>
                            <div class="form-group">
                                <label>Whop Webhook Secret</label>
                                <input type="password" name="settings[whop_webhook_secret]" value="<?php echo esc($config['whop_webhook_secret'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Whop Bronze Plan Link</label>
                            <input type="url" name="settings[whop_link_bronze]" value="<?php echo esc($config['whop_link_bronze'] ?? ''); ?>" placeholder="https://whop.com/checkout/...">
                        </div>
                        <div class="form-group">
                            <label>Whop Silver Plan Link</label>
                            <input type="url" name="settings[whop_link_silver]" value="<?php echo esc($config['whop_link_silver'] ?? ''); ?>" placeholder="https://whop.com/checkout/...">
                        </div>
                        <div class="form-group">
                            <label>Whop Gold Plan Link</label>
                            <input type="url" name="settings[whop_link_gold]" value="<?php echo esc($config['whop_link_gold'] ?? ''); ?>" placeholder="https://whop.com/checkout/...">
                        </div>
                        <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.2); font-size: 0.82rem; color: var(--color-text-secondary);">
                            💡 <strong style="color: var(--color-text-primary);">Whop Kurulumu:</strong> whop.com'da hesap açın → Yeni ürün oluşturun → Her plan için ayrı fiyat girin → API Keys bölümünden key alın → Webhook URL: <code style="background: rgba(0,0,0,0.2); padding: 2px 6px; border-radius: 4px;"><?php echo url('webhook/whop'); ?></code>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">💾 Kaydet</button>
                </form>

                <!-- Payment Records -->
                <div class="admin-card" style="margin-top: 1.5rem;">
                    <h3>📋 Ödeme Kayıtları</h3>
                    <?php if (empty($payments)): ?>
                        <p style="color: var(--color-text-muted); text-align: center; padding: 1.5rem;">Henüz kayıtlı ödeme bulunmamaktadır.</p>
                    <?php else: ?>
                        <div class="table-responsive-container">
                            <table class="trending-table" style="font-size: 0.82rem;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Kullanıcı</th>
                                        <th>Plan</th>
                                        <th>Tutar</th>
                                        <th>Yöntem</th>
                                        <th>Referans</th>
                                        <th>Durum</th>
                                        <th>Tarih</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $pay): ?>
                                        <tr class="table-row-hover">
                                            <td style="color: var(--color-text-muted);">#<?php echo (int)$pay['id']; ?></td>
                                            <td>
                                                <strong style="color: var(--color-text-primary);"><?php echo esc($pay['username'] ?? '—'); ?></strong>
                                                <br><small style="color: var(--color-text-muted);"><?php echo esc($pay['email'] ?? ''); ?></small>
                                            </td>
                                            <td>
                                                <span style="text-transform:uppercase; font-weight:600; font-size:0.75rem;"><?php echo esc($pay['plan']); ?></span>
                                            </td>
                                            <td style="font-weight:700; color: var(--color-success);">
                                                <?php echo esc($pay['amount']); ?> <?php echo esc($pay['currency']); ?>
                                            </td>
                                            <td style="color: var(--color-text-secondary);"><?php echo esc($pay['method']); ?></td>
                                            <td style="color: var(--color-text-muted); font-size:0.75rem;"><?php echo esc($pay['reference'] ?? '—'); ?></td>
                                            <td>
                                                <?php
                                                $sc = ['pending'=>'#f59e0b','confirmed'=>'#10b981','rejected'=>'#ef4444'];
                                                $sl = ['pending'=>'Bekliyor','confirmed'=>'Onaylandı','rejected'=>'Reddedildi'];
                                                $st = $pay['status'] ?? 'pending';
                                                ?>
                                                <span style="background: <?php echo $sc[$st]??'#6b7280'; ?>22; color: <?php echo $sc[$st]??'#6b7280'; ?>; padding: 2px 8px; border-radius: 20px; font-size: 0.75rem; font-weight:600;">
                                                    <?php echo $sl[$st] ?? $st; ?>
                                                </span>
                                            </td>
                                            <td style="color: var(--color-text-muted); white-space:nowrap;"><?php echo formatDate($pay['created_at'], 'd M Y'); ?></td>
                                            <td>
                                                <?php if ($pay['status'] === 'pending'): ?>
                                                    <form method="POST" action="?tab=payments" style="display:inline;">
                                                        <input type="hidden" name="action" value="confirm_payment">
                                                        <input type="hidden" name="payment_id" value="<?php echo (int)$pay['id']; ?>">
                                                        <button type="submit" class="btn btn-sm" style="background: rgba(16,185,129,0.15); color:#10b981; border:1px solid rgba(16,185,129,0.3); padding: 3px 8px; font-size:0.75rem;" onclick="return confirm('Ödemeyi onaylayıp planı aktif hale getireyim mi?')">✓ Onayla</button>
                                                    </form>
                                                    <form method="POST" action="?tab=payments" style="display:inline; margin-left:4px;">
                                                        <input type="hidden" name="action" value="reject_payment">
                                                        <input type="hidden" name="payment_id" value="<?php echo (int)$pay['id']; ?>">
                                                        <button type="submit" class="btn btn-sm" style="background: rgba(239,68,68,0.15); color:#ef4444; border:1px solid rgba(239,68,68,0.3); padding: 3px 8px; font-size:0.75rem;" onclick="return confirm('Ödemeyi reddet?')">✗ Reddet</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════
                 TAB: AFFILIATE STATS
            ════════════════════════════════════════════════════ -->
            <div class="admin-tab-pane <?php echo $activeTab==='affiliate-stats'?'active':''; ?>">
                <div class="admin-page-header">
                    <h2>📈 Affiliate Tıklama İstatistikleri</h2>
                    <p>Sağlayıcı bazlı özet ve son 50 tıklama detayı</p>
                </div>

                <?php if (empty($affiliateStats)): ?>
                    <div class="admin-card" style="text-align:center; padding: 3rem;">
                        <div style="font-size:3rem;">📊</div>
                        <p style="color: var(--color-text-muted); margin-top: 1rem;">Henüz affiliate tıklaması kaydedilmedi.</p>
                        <p style="font-size:0.82rem; color: var(--color-text-muted);">Ziyaretçiler <code>/go?to=provider</code> linklerine tıkladığında burada görünür.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-card">
                        <h3>📊 Sağlayıcı Bazlı Özet</h3>
                        <div class="table-responsive-container">
                            <table class="trending-table">
                                <thead><tr><th>Sağlayıcı</th><th style="text-align:center;">Tıklama</th><th>Son Tıklama</th></tr></thead>
                                <tbody>
                                    <?php foreach($affiliateStats as $s): ?>
                                        <tr class="table-row-hover">
                                            <td><strong style="color: var(--color-text-primary); text-transform:uppercase; font-size:0.85rem;"><?php echo esc(str_replace('_',' ',$s['provider'])); ?></strong></td>
                                            <td style="text-align:center;"><span style="background:rgba(99,102,241,0.15);color:var(--color-primary);padding:3px 12px;border-radius:20px;font-weight:700;"><?php echo (int)$s['click_count']; ?></span></td>
                                            <td style="color:var(--color-text-secondary); font-size:0.85rem;"><?php echo formatDate($s['last_clicked']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="admin-card" style="margin-top: 1.5rem;">
                        <h3>🕐 Son 50 Tıklama Detayı</h3>
                        <div class="table-responsive-container">
                            <table class="trending-table" style="font-size:0.82rem;">
                                <thead><tr><th>#</th><th>Sağlayıcı</th><th>Kullanıcı</th><th>IP</th><th>UTM Kaynak</th><th>Referrer</th><th>Tarih</th></tr></thead>
                                <tbody>
                                    <?php foreach($recentClicks as $cl): ?>
                                        <tr class="table-row-hover">
                                            <td style="color:var(--color-text-muted);"><?php echo (int)$cl['id']; ?></td>
                                            <td><span style="background:rgba(99,102,241,0.12);color:var(--color-primary);padding:2px 8px;border-radius:4px;font-size:0.75rem;font-weight:600;text-transform:uppercase;"><?php echo esc(str_replace('_',' ',$cl['provider'])); ?></span></td>
                                            <td style="color:var(--color-text-secondary);"><?php echo !empty($cl['username']) ? '@'.esc($cl['username']) : '<em style="color:var(--color-text-muted)">Ziyaretçi</em>'; ?></td>
                                            <td style="font-family:monospace;font-size:0.75rem;color:var(--color-text-muted);"><?php echo esc($cl['ip_address'] ?? '—'); ?></td>
                                            <td><?php if(!empty($cl['utm_source'])): ?><span style="background:rgba(16,185,129,0.12);color:#6ee7b7;padding:2px 7px;border-radius:4px;font-size:0.75rem;font-weight:600;"><?php echo esc($cl['utm_source']); ?></span><?php else: ?><span style="color:var(--color-text-muted);">—</span><?php endif; ?></td>
                                            <td style="color:var(--color-text-muted);font-size:0.75rem;" title="<?php echo esc($cl['referrer'] ?? ''); ?>">
                                                <?php echo !empty($cl['referrer']) ? esc(parse_url($cl['referrer'], PHP_URL_HOST) ?: $cl['referrer']) : '—'; ?>
                                            </td>
                                            <td style="color:var(--color-text-secondary);white-space:nowrap;"><?php echo formatDate($cl['clicked_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ════════════════════════════════════════════════════
                 TAB: MEMBERS
            ════════════════════════════════════════════════════ -->
            <div class="admin-tab-pane <?php echo $activeTab==='members'?'active':''; ?>">
                <div class="admin-page-header">
                    <h2>👥 Üye Yönetimi</h2>
                    <p>Kayıtlı kullanıcılar, planları ve domain sayıları</p>
                </div>
                <div class="admin-card">
                    <h3>📋 Tüm Üyeler (<?php echo count($members); ?>)</h3>
                    <?php if (empty($members)): ?>
                        <p style="color: var(--color-text-muted);">Kayıtlı üye bulunmamaktadır.</p>
                    <?php else: ?>
                        <div class="table-responsive-container">
                            <table class="trending-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Kullanıcı</th>
                                        <th>E-posta</th>
                                        <th>Plan</th>
                                        <th>Domain</th>
                                        <th>Kayıt Tarihi</th>
                                        <th>Plan Değiştir</th>
                                        <th>Sil</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $m): ?>
                                        <?php
                                        $pc = ['free'=>'#6b7280','bronze'=>'#d97706','silver'=>'#94a3b8','gold'=>'#eab308'];
                                        $pc2 = $pc[$m['api_plan']] ?? '#6b7280';
                                        ?>
                                        <tr class="table-row-hover">
                                            <td style="color:var(--color-text-muted);">#<?php echo (int)$m['id']; ?></td>
                                            <td><strong style="color: var(--color-text-primary);"><?php echo esc($m['username']); ?></strong></td>
                                            <td style="color:var(--color-text-secondary); font-size:0.85rem;"><?php echo esc($m['email']); ?></td>
                                            <td>
                                                <span style="background:<?php echo $pc2; ?>22; color:<?php echo $pc2; ?>; padding:2px 8px; border-radius:4px; font-size:0.75rem; font-weight:700; text-transform:uppercase;">
                                                    <?php echo esc($m['api_plan']); ?>
                                                </span>
                                            </td>
                                            <td style="color: var(--color-primary); font-weight:700;"><?php echo (int)$m['domain_count']; ?></td>
                                            <td style="color:var(--color-text-muted); font-size:0.82rem;"><?php echo formatDate($m['created_at'], 'd M Y'); ?></td>
                                            <td>
                                                <form method="POST" action="?tab=members" style="display:flex; gap:0.3rem;">
                                                    <input type="hidden" name="action" value="admin_set_plan">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$m['id']; ?>">
                                                    <select name="plan" style="padding:3px 6px; border-radius:5px; font-size:0.75rem; background:rgba(0,0,0,0.2); border:1px solid var(--color-border); color: var(--color-text-primary);">
                                                        <?php foreach(['free','bronze','silver','gold'] as $pl): ?>
                                                            <option value="<?php echo $pl; ?>" <?php echo $m['api_plan']===$pl?'selected':''; ?>><?php echo strtoupper($pl); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm" style="padding:3px 8px; font-size:0.75rem; background:rgba(99,102,241,0.15); color:var(--color-primary); border:1px solid rgba(99,102,241,0.3);">Set</button>
                                                </form>
                                            </td>
                                            <td>
                                                <form method="POST" action="?tab=members" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$m['id']; ?>">
                                                    <button type="submit" class="btn btn-sm" style="padding:3px 8px; font-size:0.75rem; background:rgba(239,68,68,0.12); color:#ef4444; border:1px solid rgba(239,68,68,0.25);" onclick="return confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?')">🗑 Sil</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════
                 TAB: DOMAINS
            ════════════════════════════════════════════════════ -->
            <div class="admin-tab-pane <?php echo $activeTab==='domains'?'active':''; ?>">
                <div class="admin-page-header">
                    <h2>🌐 İzlenen Domainler</h2>
                    <p>Sisteme eklenen tüm domainler. Anasayfada trend alanında görünürler.</p>
                </div>
                <div class="admin-card">
                    <h3>➕ Domain Ekle</h3>
                    <form action="" method="POST" style="display: flex; gap: 0.75rem; align-items: flex-end;">
                        <input type="hidden" name="action" value="admin_add_domain">
                        <div style="flex: 1;">
                            <input type="text" name="domain_name" placeholder="ornekdomain.com" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Ekle</button>
                    </form>
                </div>

                <div class="admin-card" style="margin-top: 1rem;">
                    <h3>📋 Domain Listesi (<?php echo count($adminDomains); ?>)</h3>
                    <?php if (empty($adminDomains)): ?>
                        <p style="color: var(--color-text-muted);">Henüz domain eklenmedi.</p>
                    <?php else: ?>
                        <div class="table-responsive-container">
                            <table class="trending-table" style="font-size: 0.85rem;">
                                <thead><tr><th>Domain</th><th>Takipçi</th><th>Son Kontrol</th><th>Bitiş Tarihi</th><th>İşlem</th></tr></thead>
                                <tbody>
                                    <?php foreach($adminDomains as $dom): ?>
                                        <tr class="table-row-hover">
                                            <td><strong style="color: var(--color-text-primary);"><?php echo esc($dom['domain_name']); ?></strong></td>
                                            <td style="color:var(--color-primary); font-weight:600;"><?php echo (int)$dom['follower_count']; ?></td>
                                            <td style="color:var(--color-text-muted); font-size:0.8rem;"><?php echo formatDate($dom['last_checked'] ?? '', 'd M Y'); ?></td>
                                            <td style="color:var(--color-text-secondary); font-size:0.8rem;"><?php echo formatDate($dom['expiration_date'] ?? '', 'd M Y'); ?></td>
                                            <td>
                                                <a href="<?php echo url('domain/' . urlencode($dom['domain_name'])); ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding:3px 8px; font-size:0.75rem;">Görüntüle</a>
                                                <a href="<?php echo url('manage-secure-panel?delete_domain=' . (int)$dom['id']); ?>" onclick="return confirm('Bu domaini silmek istediğinize emin misiniz?')" class="btn btn-sm" style="padding:3px 8px; font-size:0.75rem; background:rgba(239,68,68,0.12); color:#ef4444; border:1px solid rgba(239,68,68,0.25);">Sil</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════
                 TAB: INTEGRATIONS
            ════════════════════════════════════════════════════ -->
            <div class="admin-tab-pane <?php echo $activeTab==='integrations'?'active':''; ?>">
                <div class="admin-page-header">
                    <h2>🔌 Entegrasyon Kodları</h2>
                    <p>Üçüncü parti servis API anahtarları ve yapılandırmaları</p>
                </div>
                <form action="?tab=integrations" method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    <div class="admin-card">
                        <h3>🌩️ Cloudflare API</h3>
                        <div class="admin-form-row">
                            <div class="form-group">
                                <label>Cloudflare API Token</label>
                                <input type="password" name="settings[cloudflare_api_token]" value="<?php echo esc($config['cloudflare_api_token'] ?? ''); ?>" placeholder="Bearer token...">
                            </div>
                            <div class="form-group">
                                <label>Cloudflare Zone ID</label>
                                <input type="text" name="settings[cloudflare_zone_id]" value="<?php echo esc($config['cloudflare_zone_id'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="admin-card">
                        <h3>📡 Whois API</h3>
                        <div class="form-group">
                            <label>WhoisXML API Key</label>
                            <input type="password" name="settings[whoisxml_api_key]" value="<?php echo esc($config['whoisxml_api_key'] ?? ''); ?>" placeholder="at_XXXX">
                            <small style="color: var(--color-text-muted); font-size:0.75rem;">whoisxmlapi.com → API Keys bölümünden alın. Aylık 500 sorgu ücretsiz.</small>
                        </div>
                    </div>
                    <div class="admin-card">
                        <h3>🔔 Webhook & Bildirim</h3>
                        <div class="form-group">
                            <label>Sistem Genel Webhook URL</label>
                            <input type="url" name="settings[system_webhook_url]" value="<?php echo esc($config['system_webhook_url'] ?? ''); ?>" placeholder="https://hooks.slack.com/...">
                            <small style="color: var(--color-text-muted); font-size:0.75rem;">Slack, Discord veya özel webhook. Kritik sistem olaylarında bildirim alırsınız.</small>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">💾 Kaydet</button>
                </form>
            </div>

        </main>
    </div>

<?php endif; ?>
</div>
