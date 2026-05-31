<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}
?>
<!-- Modal dialog for adding domains (Single or Bulk) -->
<dialog id="addDomainDialog" class="glass-modal">
    <div class="modal-container">
        
        <!-- Header -->
        <div class="modal-header">
            <h3><?php echo __('modal_add_title'); ?></h3>
            <button class="modal-close-btn" onclick="closeAddDomainModal()" aria-label="<?php echo esc(__('btn_close')); ?>">×</button>
        </div>
        
        <!-- Subtitle -->
        <p class="modal-subtitle"><?php echo __('modal_add_subtitle'); ?></p>
        
        <!-- Modal Tabs -->
        <div class="modal-tab-selector">
            <button type="button" id="tabBtnSingle" class="modal-tab-btn active" onclick="switchModalTab('single')"><?php echo __('modal_tab_single'); ?></button>
            <button type="button" id="tabBtnBulk" class="modal-tab-btn" onclick="switchModalTab('bulk')"><?php echo __('modal_tab_bulk'); ?></button>
        </div>
        
        <!-- Forms -->
        <form action="<?php echo url('panel/domains'); ?>" method="POST" id="addDomainForm">
            <input type="hidden" name="action" value="add_domains">
            <input type="hidden" name="mode" id="modalInputMode" value="single">
            
            <!-- SINGLE DOMAIN FORM BLOCK -->
            <div id="modalSingleBlock" class="modal-form-block">
                <div class="form-group">
                    <label for="modalDomainName"><?php echo __('modal_domain_label'); ?></label>
                    <input type="text" id="modalDomainName" name="domain_name" placeholder="<?php echo esc(__('modal_domain_placeholder')); ?>" autocomplete="off">
                    <span class="input-helper"><?php echo __('modal_domain_helper'); ?></span>
                </div>
            </div>
            
            <!-- BULK DOMAIN FORM BLOCK -->
            <div id="modalBulkBlock" class="modal-form-block" style="display:none;">
                <div class="form-group">
                    <div class="label-row-flex">
                        <label for="modalDomainsBulk"><?php echo __('modal_bulk_label'); ?></label>
                        <span class="csv-upload-lbl"><?php echo __('csv_upload_lbl'); ?></span>
                    </div>
                    <textarea id="modalDomainsBulk" name="domains_bulk" rows="6" placeholder="<?php echo esc(__('modal_bulk_placeholder')); ?>"></textarea>
                    <span class="input-helper"><?php echo __('modal_bulk_helper'); ?></span>
                </div>
            </div>
            
            <!-- ALERTS NOTIFIER SELECTOR (Tags selection) -->
            <div class="form-group">
                <label><?php echo __('modal_alerts_label'); ?></label>
                <div class="alert-tags-container">
                    <button type="button" class="alert-tag-btn active" data-val="60" onclick="toggleAlertTag(this)">60d</button>
                    <button type="button" class="alert-tag-btn active" data-val="30" onclick="toggleAlertTag(this)">30d</button>
                    <button type="button" class="alert-tag-btn" data-val="14" onclick="toggleAlertTag(this)">14d</button>
                    <button type="button" class="alert-tag-btn active" data-val="7" onclick="toggleAlertTag(this)">7d</button>
                    <button type="button" class="alert-tag-btn" data-val="3" onclick="toggleAlertTag(this)">3d</button>
                    <button type="button" class="alert-tag-btn active" data-val="1" onclick="toggleAlertTag(this)">1d</button>
                </div>
                <span class="input-helper"><?php echo __('modal_alerts_helper'); ?></span>
                
                <!-- Hidden inputs to submit values -->
                <input type="hidden" name="alerts[60]" id="alert_val_60" value="1">
                <input type="hidden" name="alerts[30]" id="alert_val_30" value="1">
                <input type="hidden" name="alerts[14]" id="alert_val_14" value="0">
                <input type="hidden" name="alerts[7]" id="alert_val_7" value="1">
                <input type="hidden" name="alerts[3]" id="alert_val_3" value="0">
                <input type="hidden" name="alerts[1]" id="alert_val_1" value="1">
            </div>
            
            <!-- Actions -->
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAddDomainModal()"><?php echo __('modal_btn_cancel'); ?></button>
                <button type="submit" id="modalSubmitBtn" class="btn btn-primary"
                        data-text-single="<?php echo esc(__('modal_btn_save')); ?>"
                        data-text-bulk="<?php echo esc(__('modal_btn_bulk_save')); ?>"><?php echo __('modal_btn_save'); ?></button>
            </div>
        </form>
        
    </div>
</dialog>
