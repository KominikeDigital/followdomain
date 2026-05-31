<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

global $pdo;

$userId = $_SESSION['user_id'];

// Handle Actions (Add, Delete)
$hostingMessage = null;
$hostingError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_hosting') {
        $provider = trim($_POST['provider']);
        $domainName = cleanDomainName($_POST['domain_name']);
        $expDate = $_POST['expiration_date'];
        
        $alertSettings = isset($_POST['alerts']) ? $_POST['alerts'] : [];
        
        $res = addUserHosting($pdo, $userId, $provider, $domainName, $expDate, $alertSettings);
        if ($res['success']) {
            $hostingMessage = $res['message'];
        } else {
            $hostingError = $res['message'];
        }
    }
}

if (isset($_GET['delete_hosting'])) {
    $delId = intval($_GET['delete_hosting']);
    
    // Ensure this hosting belongs to the current user
    $stmtCheck = $pdo->prepare("SELECT domain_name, hosting_provider FROM user_hostings WHERE id = ? AND user_id = ?");
    $stmtCheck->execute([$delId, $userId]);
    $hosting = $stmtCheck->fetch();
    
    if ($hosting) {
        $stmtDel = $pdo->prepare("DELETE FROM user_hostings WHERE id = ?");
        $stmtDel->execute([$delId]);
        
        logActivity($pdo, $userId, "Hosting takip silindi: " . $hosting['domain_name'] . " (" . $hosting['hosting_provider'] . ")");
        $hostingMessage = __('msg_hosting_deleted');
    }
}

// Fetch user hostings
$stmtH = $pdo->prepare("SELECT * FROM user_hostings WHERE user_id = ? ORDER BY expiration_date ASC");
$stmtH->execute([$userId]);
$myHostings = $stmtH->fetchAll();
?>

<div class="hosting-page">
    <div class="dashboard-greet-header">
        <div>
            <h1 class="greet-title"><?php echo __('hosting_title'); ?></h1>
            <p class="greet-subtitle"><?php echo __('hosting_subtitle'); ?></p>
        </div>
        <div>
            <button class="btn btn-primary" onclick="document.getElementById('addHostingDialog').showModal()"><?php echo __('add_hosting_btn'); ?></button>
        </div>
    </div>

    <?php if (isset($hostingMessage)): ?>
        <div class="alert alert-success"><?php echo esc($hostingMessage); ?></div>
    <?php endif; ?>
    
    <?php if (isset($hostingError)): ?>
        <div class="alert alert-error"><?php echo esc($hostingError); ?></div>
    <?php endif; ?>

    <!-- Display Table -->
    <?php if (empty($myHostings)): ?>
        <div class="glass-panel text-center" style="padding: 4rem 2rem;">
            <h3><?php echo __('no_hostings'); ?></h3>
            <p class="text-muted" style="margin-top: 0.5rem;"><?php echo __('no_hostings_sub'); ?></p>
            <button class="btn btn-primary" style="margin-top: 1.5rem;" onclick="document.getElementById('addHostingDialog').showModal()"><?php echo __('add_first_hosting'); ?></button>
        </div>
    <?php else: ?>
        <div class="glass-panel table-responsive-container">
            <table class="trending-table">
                <thead>
                    <tr>
                        <th><?php echo __('col_provider'); ?></th>
                        <th><?php echo __('col_domain'); ?></th>
                        <th><?php echo __('col_time_left'); ?></th>
                        <th class="hide-mobile"><?php echo __('col_renewal_date'); ?></th>
                        <th class="text-right"><?php echo __('col_actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myHostings as $host): 
                        $cd = getCountdownDetails($host['expiration_date']);
                    ?>
                        <tr class="table-row-hover">
                            <td class="domain-cell">
                                <span class="domain-icon-bullet" style="background-color: #10b981;"></span>
                                <strong><?php echo esc($host['hosting_provider']); ?></strong>
                            </td>
                            <td>
                                <a href="<?php echo url('domain/' . urlencode($host['domain_name'])); ?>" target="_blank" style="color: #a5b4fc; text-decoration: underline;">
                                    <?php echo esc($host['domain_name']); ?>
                                </a>
                            </td>
                            <td>
                                <span class="countdown-badge <?php echo $cd['expired'] ? 'expired' : ''; ?>">
                                    <?php echo esc($cd['text']); ?>
                                </span>
                            </td>
                            <td class="hide-mobile table-date-txt">
                                <?php echo formatDate($host['expiration_date'], 'd M Y'); ?>
                            </td>
                            <td class="text-right">
                                <a href="<?php echo url('panel/hosting?delete_hosting=' . (int)$host['id']); ?>" onclick="return confirm('<?php echo esc(__('action_delete_confirm')); ?>')" class="btn btn-secondary btn-sm" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.2);"><?php echo __('action_delete'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Add Hosting Dialog Modal -->
<dialog id="addHostingDialog" class="glass-modal">
    <div class="modal-container">
        <div class="modal-header">
            <h3><?php echo __('modal_hosting_title'); ?></h3>
            <button class="modal-close-btn" onclick="document.getElementById('addHostingDialog').close()">×</button>
        </div>
        <p class="modal-subtitle"><?php echo __('modal_hosting_sub'); ?></p>
        
        <form action="<?php echo url('panel/hosting'); ?>" method="POST">
            <input type="hidden" name="action" value="add_hosting">
            
            <div class="form-group">
                <label for="hostProvider"><?php echo __('label_provider'); ?></label>
                <input type="text" id="hostProvider" name="provider" placeholder="<?php echo esc(__('placeholder_provider')); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="hostDomain"><?php echo __('label_connected_domain'); ?></label>
                <input type="text" id="hostDomain" name="domain_name" placeholder="<?php echo esc(__('modal_domain_placeholder')); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="hostExpDate"><?php echo __('label_renewal_date'); ?></label>
                <input type="date" id="hostExpDate" name="expiration_date" required>
            </div>
            
            <div class="form-group">
                <label><?php echo __('label_reminder_days'); ?></label>
                <div class="alert-tags-container">
                    <button type="button" class="alert-tag-btn active" data-val="30" onclick="toggleAlertTag(this)">30d</button>
                    <button type="button" class="alert-tag-btn active" data-val="7" onclick="toggleAlertTag(this)">7d</button>
                    <button type="button" class="alert-tag-btn active" data-val="1" onclick="toggleAlertTag(this)">1d</button>
                </div>
                
                <input type="hidden" name="alerts[30]" id="alert_val_30" value="1">
                <input type="hidden" name="alerts[7]" id="alert_val_7" value="1">
                <input type="hidden" name="alerts[1]" id="alert_val_1" value="1">
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addHostingDialog').close()"><?php echo __('modal_btn_cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo __('btn_add'); ?></button>
            </div>
        </form>
    </div>
</dialog>
