<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($route) ? ($route === 'register' ? 'register' : ($route === 'forgot_password' ? 'forgot' : 'login')) : 'login');
?>

<div class="login-signup-container">
    <div class="glass-panel auth-card">
        
        <!-- Auth Tabs Toggle -->
        <div class="auth-tabs">
            <a href="<?php echo url('login'); ?>" class="auth-tab <?php echo ($action === 'login') ? 'active' : ''; ?>"><?php echo __('login_tab'); ?></a>
            <a href="<?php echo url('register'); ?>" class="auth-tab <?php echo ($action === 'register') ? 'active' : ''; ?>"><?php echo __('register_tab'); ?></a>
        </div>

        <?php if (isset($authError)): ?>
            <div class="alert alert-error"><?php echo esc($authError); ?></div>
        <?php endif; ?>
        
        <?php if (isset($authSuccess)): ?>
            <div class="alert alert-success"><?php echo esc($authSuccess); ?></div>
        <?php endif; ?>

        <?php if ($action === 'login'): ?>
            
            <!-- LOGIN FORM -->
            <form action="<?php echo url('login'); ?>" method="POST" class="auth-form">
                <div class="form-group">
                    <label for="loginUser"><?php echo __('label_username_or_email'); ?></label>
                    <input type="text" id="loginUser" name="username_or_email" required placeholder="<?php echo esc(__('placeholder_username_or_email')); ?>">
                </div>
                
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <label for="loginPass" style="margin-bottom: 0;"><?php echo __('label_password'); ?></label>
                        <a href="<?php echo url('forgot-password'); ?>" style="font-size: 0.8rem; color: var(--color-primary); text-decoration: none;"><?php echo __('forgot_password_link', 'Forgot Password?'); ?></a>
                    </div>
                    <input type="password" id="loginPass" name="password" required placeholder="<?php echo esc(__('placeholder_password')); ?>">
                </div>
                
                <button type="submit" name="submit_login" class="btn btn-primary w-full"><?php echo __('btn_login'); ?></button>
            </form>
            
            <p class="auth-helper-txt"><?php echo sprintf(__('helper_no_account'), url('register')); ?></p>

        <?php elseif ($action === 'register'): ?>
            
            <!-- REGISTER FORM -->
            <form action="<?php echo url('register'); ?>" method="POST" class="auth-form">
                <div class="form-group">
                    <label for="regUser"><?php echo __('label_username'); ?></label>
                    <input type="text" id="regUser" name="username" required placeholder="<?php echo esc(__('placeholder_username')); ?>" minlength="3">
                </div>
                
                <div class="form-group">
                    <label for="regEmail"><?php echo __('label_email'); ?></label>
                    <input type="email" id="regEmail" name="email" required placeholder="<?php echo esc(__('placeholder_email')); ?>">
                </div>
                
                <div class="form-group">
                    <label for="regPass"><?php echo __('label_password'); ?></label>
                    <input type="password" id="regPass" name="password" required placeholder="<?php echo esc(__('placeholder_register_password')); ?>" minlength="8" autocomplete="new-password">
                    <div class="password-strength"
                         id="passwordStrength"
                         data-weak="<?php echo esc(__('password_strength_weak')); ?>"
                         data-fair="<?php echo esc(__('password_strength_fair')); ?>"
                         data-good="<?php echo esc(__('password_strength_good')); ?>"
                         data-strong="<?php echo esc(__('password_strength_strong')); ?>"
                         data-invalid="<?php echo esc(__('password_strength_invalid')); ?>">
                        <div class="password-strength-head">
                            <span><?php echo __('password_strength_label'); ?></span>
                            <strong id="passwordStrengthText"><?php echo __('password_strength_weak'); ?></strong>
                        </div>
                        <div class="password-strength-bar" aria-hidden="true">
                            <span id="passwordStrengthBar"></span>
                        </div>
                        <ul class="password-strength-rules" aria-live="polite">
                            <li data-rule="length"><?php echo __('password_rule_length'); ?></li>
                            <li data-rule="case"><?php echo __('password_rule_case'); ?></li>
                            <li data-rule="number"><?php echo __('password_rule_number'); ?></li>
                            <li data-rule="symbol"><?php echo __('password_rule_symbol'); ?></li>
                        </ul>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="regPlan"><?php echo __('signup_select_plan_label'); ?></label>
                    <select id="regPlan" name="plan">
                        <option value="free"><?php echo __('signup_free_plan'); ?></option>
                        <option value="bronze"><?php echo __('signup_bronze_plan'); ?></option>
                        <option value="silver"><?php echo __('signup_silver_plan'); ?></option>
                        <option value="gold"><?php echo __('signup_gold_plan'); ?></option>
                    </select>
                    <span class="input-helper" style="font-size: 0.75rem; color: var(--color-text-secondary); display: block; margin-top: 0.4rem;"><?php echo __('signup_select_plan_desc'); ?></span>
                </div>
                
                <button type="submit" name="submit_register" class="btn btn-primary w-full"><?php echo __('btn_register'); ?></button>
            </form>
            
            <p class="auth-helper-txt"><?php echo sprintf(__('helper_has_account'), url('login')); ?></p>

        <?php elseif ($action === 'forgot'): ?>
            
            <!-- FORGOT PASSWORD FORM -->
            <form action="<?php echo url('forgot-password'); ?>" method="POST" class="auth-form">
                <p class="text-muted" style="font-size: 0.9rem; margin-bottom: 1.5rem; text-align: center; line-height: 1.5;">
                    Hesabınıza kayıtlı e-posta adresinizi girin. Size geçici bir şifre göndereceğiz.
                </p>
                <div class="form-group">
                    <label for="forgotEmail"><?php echo __('label_email'); ?></label>
                    <input type="email" id="forgotEmail" name="email" required placeholder="<?php echo esc(__('placeholder_email')); ?>">
                </div>
                
                <button type="submit" name="submit_forgot" class="btn btn-primary w-full">Geçici Şifre Gönder</button>
            </form>
            
            <p class="auth-helper-txt"><a href="<?php echo url('login'); ?>" style="color: var(--color-primary); text-decoration: none;">Giriş Sayfasına Dön</a></p>

        <?php endif; ?>

    </div>
</div>
