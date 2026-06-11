<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

global $pdo;

$userId = $_SESSION['user_id'];
$licenseMessage = null;
$licenseError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_license') {
        $licenseName = $_POST['license_name'] ?? '';
        $provider = $_POST['provider'] ?? '';
        $category = $_POST['category'] ?? '';
        $referenceCode = $_POST['reference_code'] ?? '';
        $expDate = $_POST['expiration_date'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $alertSettings = isset($_POST['alerts']) ? $_POST['alerts'] : [];

        $res = addUserLicense($pdo, $userId, $licenseName, $provider, $category, $referenceCode, $expDate, $notes, $alertSettings);
        if ($res['success']) {
            $licenseMessage = $res['message'];
        } else {
            $licenseError = $res['message'];
        }
    }
}

if (isset($_GET['delete_license'])) {
    $delId = intval($_GET['delete_license']);

    $stmtCheck = $pdo->prepare("SELECT license_name FROM user_licenses WHERE id = ? AND user_id = ?");
    $stmtCheck->execute([$delId, $userId]);
    $license = $stmtCheck->fetch();

    if ($license) {
        $stmtDel = $pdo->prepare("DELETE FROM user_licenses WHERE id = ? AND user_id = ?");
        $stmtDel->execute([$delId, $userId]);

        logActivity($pdo, $userId, "Lisans takibi silindi: " . $license['license_name']);
        $licenseMessage = __('msg_license_deleted');
    }
}

$stmtLicenses = $pdo->prepare("SELECT * FROM user_licenses WHERE user_id = ? ORDER BY (expiration_date IS NULL) ASC, expiration_date ASC, license_name ASC");
$stmtLicenses->execute([$userId]);
$myLicenses = $stmtLicenses->fetchAll();
?>

<div class="user-shell">
    <?php require_once __DIR__ . '/user_sidebar.php'; ?>
    <main class="user-main">
        <div class="hosting-page licenses-page">
            <div class="dashboard-greet-header">
                <div>
                    <h1 class="greet-title"><?php echo __('licenses_title'); ?></h1>
                    <p class="greet-subtitle"><?php echo __('licenses_subtitle'); ?></p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="document.getElementById('addLicenseDialog').showModal()"><?php echo __('add_license_btn'); ?></button>
                </div>
            </div>

            <?php if (isset($licenseMessage)): ?>
                <div class="alert alert-success"><?php echo esc($licenseMessage); ?></div>
            <?php endif; ?>

            <?php if (isset($licenseError)): ?>
                <div class="alert alert-error"><?php echo esc($licenseError); ?></div>
            <?php endif; ?>

            <?php if (empty($myLicenses)): ?>
                <div class="glass-panel text-center" style="padding: 4rem 2rem;">
                    <h3><?php echo __('no_licenses'); ?></h3>
                    <p class="text-muted" style="margin-top: 0.5rem;"><?php echo __('no_licenses_sub'); ?></p>
                    <button class="btn btn-primary" style="margin-top: 1.5rem;" onclick="document.getElementById('addLicenseDialog').showModal()"><?php echo __('add_first_license'); ?></button>
                </div>
            <?php else: ?>
                <div class="glass-panel table-responsive-container">
                    <table class="trending-table">
                        <thead>
                            <tr>
                                <th><?php echo __('col_license'); ?></th>
                                <th class="hide-mobile"><?php echo __('col_license_category'); ?></th>
                                <th><?php echo __('label_license_provider'); ?></th>
                                <th><?php echo __('col_time_left'); ?></th>
                                <th class="hide-mobile"><?php echo __('col_renewal_date'); ?></th>
                                <th class="text-right"><?php echo __('col_actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myLicenses as $license):
                                $cd = getCountdownDetails($license['expiration_date']);
                                $category = trim((string)$license['category']) !== '' ? $license['category'] : __('custom_license_default_category');
                                $provider = trim((string)$license['provider']) !== '' ? $license['provider'] : __('custom_license_no_provider');
                            ?>
                                <tr class="table-row-hover">
                                    <td class="domain-cell">
                                        <span class="domain-icon-bullet" style="background-color: #7c3aed;"></span>
                                        <div>
                                            <strong><?php echo esc($license['license_name']); ?></strong>
                                            <?php if (!empty($license['reference_code']) || !empty($license['notes'])): ?>
                                                <div class="text-muted" style="font-size: 0.78rem; margin-top: 0.2rem;">
                                                    <?php if (!empty($license['reference_code'])): ?>
                                                        <?php echo esc($license['reference_code']); ?>
                                                    <?php endif; ?>
                                                    <?php if (!empty($license['reference_code']) && !empty($license['notes'])): ?>
                                                        &middot;
                                                    <?php endif; ?>
                                                    <?php if (!empty($license['notes'])): ?>
                                                        <?php echo esc($license['notes']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="hide-mobile"><?php echo esc($category); ?></td>
                                    <td><?php echo esc($provider); ?></td>
                                    <td>
                                        <span class="countdown-badge <?php echo $cd['expired'] ? 'expired' : ''; ?>">
                                            <?php echo esc($cd['text']); ?>
                                        </span>
                                    </td>
                                    <td class="hide-mobile table-date-txt">
                                        <?php echo formatDate($license['expiration_date'], 'd M Y'); ?>
                                    </td>
                                    <td class="text-right">
                                        <a href="<?php echo url('panel/licenses?delete_license=' . (int)$license['id']); ?>" onclick="return confirm('<?php echo esc(__('action_license_delete_confirm')); ?>')" class="btn btn-secondary btn-sm" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.2);"><?php echo __('action_delete'); ?></a>
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

<dialog id="addLicenseDialog" class="glass-modal">
    <div class="modal-container">
        <div class="modal-header">
            <h3><?php echo __('modal_license_title'); ?></h3>
            <button class="modal-close-btn" onclick="document.getElementById('addLicenseDialog').close()">x</button>
        </div>
        <p class="modal-subtitle"><?php echo __('modal_license_sub'); ?></p>

        <form action="<?php echo url('panel/licenses'); ?>" method="POST">
            <input type="hidden" name="action" value="add_license">

            <div class="form-group">
                <label for="licenseName"><?php echo __('label_license_name'); ?></label>
                <input type="text" id="licenseName" name="license_name" placeholder="<?php echo esc(__('placeholder_license_name')); ?>" required>
            </div>

            <div class="form-group">
                <label for="licenseCategory"><?php echo __('label_license_category'); ?></label>
                <input type="text" id="licenseCategory" name="category" placeholder="<?php echo esc(__('placeholder_license_category')); ?>">
            </div>

            <div class="form-group">
                <label for="licenseProvider"><?php echo __('label_license_provider'); ?></label>
                <input type="text" id="licenseProvider" name="provider" placeholder="<?php echo esc(__('placeholder_license_provider')); ?>">
            </div>

            <div class="form-group">
                <label for="licenseReference"><?php echo __('label_license_reference'); ?></label>
                <input type="text" id="licenseReference" name="reference_code" placeholder="<?php echo esc(__('placeholder_license_reference')); ?>">
            </div>

            <div class="form-group">
                <label for="licenseExpDate"><?php echo __('label_renewal_date'); ?></label>
                <input type="date" id="licenseExpDate" name="expiration_date" required>
            </div>

            <div class="form-group">
                <label for="licenseNotes"><?php echo __('label_license_notes'); ?></label>
                <textarea id="licenseNotes" name="notes" rows="3" placeholder="<?php echo esc(__('placeholder_license_notes')); ?>"></textarea>
            </div>

            <div class="form-group">
                <label><?php echo __('label_reminder_days'); ?></label>
                <div class="alert-tags-container">
                    <button type="button" class="alert-tag-btn active" data-val="30" onclick="toggleAlertTag(this)">30d</button>
                    <button type="button" class="alert-tag-btn active" data-val="7" onclick="toggleAlertTag(this)">7d</button>
                    <button type="button" class="alert-tag-btn active" data-val="1" onclick="toggleAlertTag(this)">1d</button>
                </div>

                <input type="hidden" name="alerts[30]" id="license_alert_val_30" value="1">
                <input type="hidden" name="alerts[7]" id="license_alert_val_7" value="1">
                <input type="hidden" name="alerts[1]" id="license_alert_val_1" value="1">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addLicenseDialog').close()"><?php echo __('modal_btn_cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo __('btn_add'); ?></button>
            </div>
        </form>
    </div>
</dialog>
