<?php

namespace EAD_XML_Importer;

if (!defined('ABSPATH')) exit;

use EAD_XML_Importer\DataLoader;

function add_exi_notice($message, $type = 'success', $id = '') {
    $id = !empty($id) ? $id : 'exi_' . uniqid();
    $class = 'notice notice-' . $type . ' settings-error is-dismissible';
    add_settings_error(
        'exi_messages',
        $id,
        $message,
        $class
    );
}

if (isset($_POST['exi_save_settings']) && wp_verify_nonce($_POST['_wpnonce_exi_save_settings'], 'exi_save_settings')) {
    $selected_post_type = sanitize_text_field($_POST['exi_post_type']);
    $selected_meta = sanitize_text_field($_POST['exi_post_type_meta_url']);
    $selected_cron = isset($_POST['exi_cron_enabled']) ? 1 : 0;
    $selected_cron_interval = sanitize_text_field($_POST['exi_cron_interval']);

    update_option('exi_post_type', $selected_post_type);
    update_option('exi_post_type_meta_url', $selected_meta);
    update_option('exi_cron_enabled', $selected_cron);
    update_option('exi_cron_interval', $selected_cron_interval);

    add_exi_notice(__('Settings saved.', 'ead-xml-importer'));
}

if (isset($_POST['exi_process_xml']) && wp_verify_nonce($_POST['_wpnonce_exi_process_xml'], 'exi_process_xml')) {
    $post_type = get_option('exi_post_type');
    $meta_key = get_option('exi_post_type_meta_url');
    
    if (empty($post_type) || empty($meta_key)) {
        add_exi_notice(__('Please configure post type and meta field settings first.', 'ead-xml-importer'), 'error');
    } else {
        try {
            // Get all posts of the selected type
            $args = array(
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => $meta_key,
                        'compare' => 'EXISTS',
                    ),
                ),
            );
            
            $posts = get_posts($args);
            $processed = 0;
            $errors = [];

            foreach ($posts as $post) {
                $xml_url = get_post_meta($post->ID, $meta_key, true);
                
                if (empty($xml_url)) continue;

                try {
                    $loader = new DataLoader($xml_url);
                    $loader->convert();
                    $loader->populateCustomPostType($post_type, $post->ID);
                    $processed++;
                } catch (Exception $e) {
                    $errors[] = sprintf(
                        __('Error processing post ID %d: %s', 'ead-xml-importer'),
                        $post->ID,
                        $e->getMessage()
                    );
                }
            }

            if ($processed > 0) {
                add_exi_notice(
                    sprintf(
                        __('Successfully processed %d posts.', 'ead-xml-importer'),
                        $processed
                    )
                );

                update_option('exi_last_sync', date('Y-m-d H:i:s'));
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    add_exi_notice($error, 'error');
                }
            }

            if ($processed === 0 && empty($errors)) {
                add_exi_notice(
                    __('No posts found with XML URLs to process.', 'ead-xml-importer'),
                    'warning'
                );
            }

        } catch (Exception $e) {
            add_exi_notice(
                sprintf(
                    __('Error: %s', 'ead-xml-importer'),
                    $e->getMessage()
                ),
                'error'
            );
        }
    }
}