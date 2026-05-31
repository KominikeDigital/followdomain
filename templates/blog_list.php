<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}
global $blogPosts;
?>
<div class="blog-list-page">
    <?php if (isset($displayCategory)): ?>
        <h1 class="blog-page-title"><?php echo esc($displayCategory); ?></h1>
        <p class="blog-page-desc"><?php echo sprintf(__('blog_category_subtitle', 'Browse all articles in the %s category.'), esc($displayCategory)); ?></p>
    <?php else: ?>
        <h1 class="blog-page-title"><?php echo __('blog_title', 'Insights & Guides'); ?></h1>
        <p class="blog-page-desc"><?php echo __('blog_subtitle', 'Stay updated with the latest trends in domain tracking, hosting architecture, and web security.'); ?></p>
    <?php endif; ?>
    
    <div class="blog-grid">
        <?php foreach ($blogPosts as $post): ?>
            <div class="blog-card glass-panel">
                <div class="blog-card-image-wrapper">
                    <img src="<?php echo url($post['image']); ?>" alt="<?php echo esc($post['title']); ?>" class="blog-card-image" loading="lazy">
                </div>
                <div class="blog-card-content">
                    <div class="blog-meta">
                        <a href="<?php echo url('blog/category/' . slugifyCategory($post['category'])); ?>" class="blog-category-link"><span class="blog-category"><?php echo esc($post['category']); ?></span></a>
                        <span class="blog-date"><?php echo formatDate($post['date'], 'Y-m-d'); ?></span>
                    </div>
                    <h2 class="blog-card-title"><?php echo esc($post['title']); ?></h2>
                    <p class="blog-card-text"><?php echo esc($post['description']); ?></p>
                    <a href="<?php echo url('blog/' . $post['slug']); ?>" class="blog-read-more">
                        <?php echo __('blog_read_more', 'Read Article'); ?> 
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
