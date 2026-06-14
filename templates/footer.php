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
                <a href="<?php echo url(''); ?>" class="app-logo logo-mark logo-mark-footer" aria-label="TLDix">
                    <img src="<?php echo url('assets/images/logo.png'); ?>" alt="TLDix Logo" class="logo-img logo-img-light">
                    <img src="<?php echo url('assets/images/dark-logo.png'); ?>" alt="" class="logo-img logo-img-dark" aria-hidden="true">
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
                        <li><a href="<?php echo url('social-search'); ?>"><?php echo __('nav_social_search'); ?></a></li>
                        <?php if ($isUser): ?>
                            <li><a href="<?php echo url('expiring'); ?>"><?php echo __('nav_expiring'); ?></a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo url('contact'); ?>"><?php echo __('nav_contact', 'Contact'); ?></a></li>
                        <li><a href="<?php echo url('affiliate'); ?>"><?php echo __('nav_affiliate', 'Affiliate'); ?></a></li>
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
                <?php
                $socialLinks = getConfiguredSocialLinks($config);
                ?>
                <?php if (!empty($socialLinks)): ?>
                    <div class="link-group">
                        <h4><?php echo __('footer_social', 'Social Media'); ?></h4>
                        <ul>
                            <?php foreach ($socialLinks as $socialLink): ?>
                                <?php $socialName = formatSocialDisplayName($socialLink['name'] ?? ''); ?>
                                <?php if ($socialName === '') continue; ?>
                                <li><a href="<?php echo esc($socialLink['url']); ?>" target="_blank" rel="noopener nofollow me"><?php echo esc($socialName); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Bottom Rights & Meta Badge Slot -->
        <div class="footer-bottom">
            <div class="container footer-bottom-container">
                <p>&copy; 2026 TLDix. All rights reserved. Kominike &quot;Creative&quot; Digital Project</p>
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
