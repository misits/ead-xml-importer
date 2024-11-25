<?php

namespace EAD_XML_Importer;

class AdminPage {
    private static function get_template_data()
    {
        $selected_post_type = get_option('exi_post_type', 'archive');
        $selected_cron_interval = get_option('exi_cron_interval', 'hourly');
        $last_sync = get_option('exi_last_sync', 'Never');
        $next_sync = wp_next_scheduled('exi_cron_hook') ?: false;

        return [
            'post_types' => get_post_types(['public' => true], 'objects'),
            'selected_post_type' => $selected_post_type,
            'exi_cron_enabled' => get_option('exi_cron_enabled', 0),
            'exi_post_type_meta_url' => get_option('exi_post_type_meta_url', 'ead_url'),
            'selected_cron_interval' => $selected_cron_interval,
            'cron_intervals' => [
                'hourly' => __('Hourly', 'ead-xml-importer'),
                'twicedaily' => __('Twice Daily', 'ead-xml-importer'),
                'daily' => __('Daily', 'ead-xml-importer'),
                'weekly' => __('Weekly', 'ead-xml-importer'),
            ],
            'last_sync' => $last_sync,
            'next_sync' => $next_sync,
        ];
    }

    public static function render()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get plugin root directory
        $plugin_dir = dirname(dirname(__FILE__));

        // Include handlers
        require_once $plugin_dir . '/admin/handlers/data-handler.php';

        // Get template data
        $data = self::get_template_data();

        // Include main template
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/templates/admin-template.php';
    }
}