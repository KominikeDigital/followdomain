<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

$postedName = trim((string)($_POST['name'] ?? ''));
$postedEmail = trim((string)($_POST['email'] ?? ''));
$postedSubject = trim((string)($_POST['subject'] ?? ''));
$postedMessage = trim((string)($_POST['message'] ?? ''));
?>

<section class="contact-page-container">
    <div class="contact-layout">
        <div class="contact-copy">
            <span class="contact-kicker"><?php echo __('contact_kicker', 'Contact'); ?></span>
            <h1><?php echo __('contact_title', 'Contact'); ?></h1>
            <p><?php echo __('contact_intro', 'Send us questions about domain expiration tracking, alerts, API access, or account support.'); ?></p>
            <a class="contact-direct-mail" href="mailto:hello@tldix.com">hello@tldix.com</a>
        </div>

        <div class="glass-panel contact-form-panel">
            <?php if (!empty($contactSuccess)): ?>
                <div class="alert alert-success"><?php echo esc($contactSuccess); ?></div>
            <?php endif; ?>
            <?php if (!empty($contactError)): ?>
                <div class="alert alert-error"><?php echo esc($contactError); ?></div>
            <?php endif; ?>

            <form action="<?php echo url('contact'); ?>" method="POST" class="contact-form">
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="contact-hp-field" aria-hidden="true">
                <div class="form-group">
                    <label for="contactName"><?php echo __('contact_name_label', 'Name'); ?></label>
                    <input type="text" id="contactName" name="name" value="<?php echo esc($postedName); ?>" required autocomplete="name">
                </div>
                <div class="form-group">
                    <label for="contactEmail"><?php echo __('contact_email_label', 'Email'); ?></label>
                    <input type="email" id="contactEmail" name="email" value="<?php echo esc($postedEmail); ?>" required autocomplete="email">
                </div>
                <div class="form-group">
                    <label for="contactSubject"><?php echo __('contact_subject_label', 'Subject'); ?></label>
                    <input type="text" id="contactSubject" name="subject" value="<?php echo esc($postedSubject); ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="contactMessage"><?php echo __('contact_message_label', 'Message'); ?></label>
                    <textarea id="contactMessage" name="message" rows="7" required><?php echo esc($postedMessage); ?></textarea>
                </div>
                <button type="submit" name="submit_contact" value="1" class="btn btn-primary w-full"><?php echo __('contact_submit', 'Send Message'); ?></button>
            </form>
        </div>
    </div>
</section>
