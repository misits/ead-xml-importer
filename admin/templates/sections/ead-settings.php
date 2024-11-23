<?php

if (!defined('ABSPATH')) exit;

// Verify we have the required data
if (!isset($post_types) || !isset($exi_post_type_meta_url)) {
    return;
}
?>

<div id="exi-settings" class="exi-section">
    <div class="exi-section-head">
        <div class="flex gap-10">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-symlink">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M4 21v-4a3 3 0 0 1 3 -3h5" />
                <path d="M9 17l3 -3l-3 -3" />
                <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                <path d="M5 11v-6a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2h-9.5" />
            </svg>
            <h1><?= __('EAD XML Importer', 'ead-xml-importer') ?></h1>
        </div>
        <p><?= __('Use this tool to load data from a EAD Archive into WordPress.', 'ead-xml-importer') ?></p>
        <p><?= __('Last Sync:', 'ead-xml-importer') ?> <strong><?= $last_sync ?></strong></p>
        <?php if ($next_sync) : ?>
            <p><?= __('Next Sync:', 'ead-xml-importer') ?> <strong><?= date('Y-m-d H:i:s', $next_sync) ?></strong></p>
        <?php endif; ?>
    </div>
    <h2 class="exi-toggle">
        <span class="exi-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-adjustments-alt">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M4 8h4v4h-4z" />
                <path d="M6 4l0 4" />
                <path d="M6 12l0 8" />
                <path d="M10 14h4v4h-4z" />
                <path d="M12 4l0 10" />
                <path d="M12 18l0 2" />
                <path d="M16 5h4v4h-4z" />
                <path d="M18 4l0 1" />
                <path d="M18 9l0 11" />
            </svg>
            <?= __('Settings', 'ead-xml-importer') ?> <span class="desc"> - <?= __('Configure the EAD importer', 'ead-xml-importer') ?>.</span>
        </span>
        <span class="exi-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-down">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M6 9l6 6l6 -6" />
            </svg>
        </span>
    </h2>
    <div class="exi-content">
        <form method="post" class="flex flex-col gap-10">
            <!-- nonce field -->
            <?php wp_nonce_field('exi_save_settings', '_wpnonce_exi_save_settings'); ?>
            <div class="form-group">
                <label for="exi_post_type"><?= __('Custom Post Type', 'ead-xml-importer') ?>:</label>
                <select name="exi_post_type" id="exi_post_type">
                    <?php foreach ($post_types as $post_type) : ?>
                        <option value="<?php echo esc_attr($post_type->name); ?>" 
                            <?php selected($selected_post_type, $post_type->name); ?>>
                            <?php echo esc_html($post_type->label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="exi_post_type_meta_url"><?= __('Meta EAD target', 'ead-xml-importer') ?>:</label>
                <input name="exi_post_type_meta_url" type="text" id="exi_post_type_meta_url" value="<?php echo esc_attr($exi_post_type_meta_url); ?>" class="regular-text">
            </div>

            <div class="form-group">
                <label for="exi_cron_interval"><?= __('Cron Interval', 'ead-xml-importer') ?>:</label>
                <select name="exi_cron_interval" id="exi_cron_interval">
                    <?php foreach ($cron_intervals as $interval => $label) : ?>
                        <option value="<?php echo esc_attr($interval); ?>" <?php selected($selected_cron_interval, $interval); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="exi_cron_enabled"><?= __('Enable Cron', 'ead-xml-importer') ?>:</label>
                <label class="switch">
                    <input type="checkbox" name="exi_cron_enabled" id="exi_cron_enabled" value="1" <?php checked($exi_cron_enabled, 1); ?>>
                    <span class="slider round"></span>
                </label>
            </div>


            <div class="flex items-center gap-10 buttons">
                <button type="submit" name="exi_save_settings" class="button button-primary"><?= __('Save Settings', 'ead-xml-importer') ?></button>
            </div>
        </form>
    </div>
</div>