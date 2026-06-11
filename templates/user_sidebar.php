<?php
// templates/user_sidebar.php
global $route;
?>
<aside class="user-sidebar">
    <div class="user-sidebar-logo">
        User Panel
    </div>

    <button class="user-sidebar-toggle" onclick="document.querySelector('.user-sidebar-nav').classList.toggle('open')">
        Menü
    </button>

    <nav class="user-sidebar-nav">
        <div class="sidebar-section-label">Takip</div>
        <a href="<?php echo url('panel'); ?>" class="<?php echo $route==='panel'?'active':''; ?>">
            Dashboard
        </a>
        <a href="<?php echo url('panel/domains'); ?>" class="<?php echo $route==='panel_domains'?'active':''; ?>">
            <?php echo __('nav_domains'); ?>
        </a>
        <a href="<?php echo url('panel/hosting'); ?>" class="<?php echo $route==='panel_hosting'?'active':''; ?>">
            <?php echo __('nav_hosting'); ?>
        </a>
        <a href="<?php echo url('panel/licenses'); ?>" class="<?php echo $route==='panel_licenses'?'active':''; ?>">
            <?php echo __('nav_licenses'); ?>
        </a>

        <div class="sidebar-section-label">Keşfet</div>
        <a href="<?php echo url('expiring'); ?>" class="<?php echo $route==='expiring'?'active':''; ?>">
            <?php echo __('nav_expiring'); ?>
        </a>
        <a href="<?php echo url('domains-for-sale'); ?>" class="<?php echo $route==='domains_for_sale'?'active':''; ?>">
            <?php echo __('nav_domains_for_sale'); ?>
        </a>

        <div class="sidebar-section-label">Gelişmiş</div>
        <a href="<?php echo url('panel/integrations'); ?>" class="<?php echo $route==='panel_integrations'?'active':''; ?>">
            <?php echo __('nav_integrations'); ?>
        </a>
        <a href="<?php echo url('panel/integrations#pricing'); ?>" style="color: var(--color-primary); font-weight: 600;">
            Premium
        </a>
    </nav>

    <div class="sidebar-bottom">
        <a href="<?php echo url(''); ?>" class="btn btn-secondary btn-sm w-full" style="margin-bottom: 0.5rem; text-align: center; display: block;">Siteye Git</a>
        <a href="<?php echo url('logout'); ?>" class="btn btn-secondary btn-sm w-full" style="text-align: center; display: block; color: var(--color-error); border-color: rgba(239,68,68,0.3); font-size: 0.8rem;">Çıkış Yap</a>
    </div>
</aside>
