        </div> <!-- Close .container -->
    </main> <!-- Close .app-main-content -->

    <div class="container">
        <?php global $config; if (($config['ad_status'] ?? 'off') === 'on' && !empty($config['ad_footer'])): ?>
            <div class="ad-container ad-footer-slot">
                <?php echo $config['ad_footer']; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="app-footer">
        <div class="container footer-container">
            
            <!-- Brand Info Column -->
            <div class="footer-info">
                <a href="<?php echo url(''); ?>" class="app-logo">
                    <span class="logo-icon" style="color: var(--color-primary); display: inline-flex;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V15H13V17ZM13 13H11V7H13V13Z" fill="currentColor"/>
                        </svg>
                    </span>
                    <span class="logo-text">domain<span>await</span></span>
                </a>
                <p class="footer-desc"><?php echo __('footer_logo_sub'); ?></p>
                
                <!-- Pulse Green Status Badge -->
                <div class="system-status-badge">
                    <span class="status-dot pulsing"></span>
                    <span><?php echo __('status_online'); ?></span>
                </div>
            </div>
            
            <!-- Quick Links Columns -->
            <div class="footer-links">
                <div class="link-group">
                    <h4><?php echo __('footer_explore'); ?></h4>
                    <ul>
                        <li><a href="<?php echo url(''); ?>"><?php echo __('nav_track'); ?></a></li>
                        <li><a href="<?php echo url('trending'); ?>"><?php echo __('nav_trending'); ?></a></li>
                        <?php if ($isUser): ?>
                            <li><a href="<?php echo url('expiring'); ?>"><?php echo __('nav_expiring'); ?></a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo url('privacy-policy'); ?>"><?php echo __('legal_privacy_title'); ?></a></li>
                        <li><a href="<?php echo url('terms-of-service'); ?>"><?php echo __('legal_terms_title'); ?></a></li>
                    </ul>
                </div>
                <div class="link-group">
                    <h4><a href="<?php echo url('blog'); ?>" style="color: inherit; text-decoration: none;">Blog</a></h4>
                    <ul>
                        <?php 
                        $latestPosts = getLatestBlogPosts($lang, 3);
                        foreach ($latestPosts as $lPost):
                        ?>
                            <li><a href="<?php echo url('blog/' . $lPost['slug']); ?>" style="display: block; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc($lPost['title']); ?>"><?php echo esc($lPost['title']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="link-group">
                    <h4><?php echo __('footer_partners'); ?></h4>
                    <ul>
                        <li><a href="<?php echo esc($config['affiliate_namecheap'] ?? '#'); ?>" target="_blank" rel="nofollow">Namecheap</a></li>
                        <li><a href="<?php echo esc($config['affiliate_hostinger'] ?? '#'); ?>" target="_blank" rel="nofollow">Hostinger</a></li>
                        <li><a href="<?php echo esc($config['affiliate_namesilo'] ?? '#'); ?>" target="_blank" rel="nofollow">NameSilo</a></li>
                        <li><a href="<?php echo esc($config['affiliate_porkbun'] ?? '#'); ?>" target="_blank" rel="nofollow">Porkbun</a></li>
                        <li><a href="<?php echo esc($config['affiliate_spaceship'] ?? '#'); ?>" target="_blank" rel="nofollow">Spaceship</a></li>
                        <li><a href="<?php echo esc($config['affiliate_dynadot'] ?? '#'); ?>" target="_blank" rel="nofollow">Dynadot</a></li>
                    </ul>
                </div>
                <div class="link-group">
                    <h4><?php echo __('footer_social', 'Social Media'); ?></h4>
                    <div class="social-icons-grid" style="display: flex; gap: 0.75rem; margin-top: 0.75rem; flex-wrap: wrap;">
                        <a href="#" class="social-icon-link" aria-label="X / Twitter" style="color: var(--color-text-secondary); transition: color 0.2s; display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--color-border);">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"/></svg>
                        </a>
                        <a href="#" class="social-icon-link" aria-label="GitHub" style="color: var(--color-text-secondary); transition: color 0.2s; display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--color-border);">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg>
                        </a>
                        <a href="#" class="social-icon-link" aria-label="LinkedIn" style="color: var(--color-text-secondary); transition: color 0.2s; display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--color-border);">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
                        </a>
                        <a href="#" class="social-icon-link" aria-label="Instagram" style="color: var(--color-text-secondary); transition: color 0.2s; display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--color-border);">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bottom Rights & Meta Badge Slot -->
        <div class="footer-bottom">
            <div class="container footer-bottom-container">
                <p>&copy; <?php echo date('Y'); ?> domainawait. <?php echo __('footer_all_rights'); ?></p>
                <div class="footer-meta">
                    <span class="meta-badge">Hourly Check Sync</span>
                    <span class="meta-badge">RDAP Over HTTPS</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Main JS file -->
    <script src="<?php echo url('assets/js/main.js'); ?>"></script>
</body>
</html>
