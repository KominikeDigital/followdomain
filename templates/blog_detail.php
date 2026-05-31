<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}
global $blogPost;
?>
<div class="blog-detail-container">
    <a href="<?php echo url('blog'); ?>" class="blog-detail-back">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        <?php echo __('blog_back_to_list', 'Back to Blog'); ?>
    </a>
    
    <div class="blog-detail-hero-image-wrapper">
        <img src="<?php echo url($blogPost['image']); ?>" alt="<?php echo esc($blogPost['title']); ?>" class="blog-detail-hero-image">
    </div>
    
    <article class="blog-detail-article">
        <header class="blog-detail-header">
            <h1 class="blog-detail-title"><?php echo esc($blogPost['title']); ?></h1>
            <div class="blog-detail-meta">
                <a href="<?php echo url('blog/category/' . slugifyCategory($blogPost['category'])); ?>" class="blog-category-link"><span class="blog-category"><?php echo esc($blogPost['category']); ?></span></a>
                <span class="blog-date"><?php echo formatDate($blogPost['date'], 'Y-m-d'); ?></span>
            </div>
        </header>
        
        <div class="blog-detail-body">
            <?php echo $blogPost['content']; ?>
        </div>
    </article>
</div>
