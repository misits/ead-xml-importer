<?php

namespace EAD_XML_Importer;

use EAD_XML_Importer\AdminPage;
use \Exception;
use \WP_Query;

use function add_exi_notice;

class Loader
{
    /**
     * Initialize the plugin
     */
    public static function init()
    {
        // Register AJAX actions
        self::register_ajax_actions();

        // Load required files
        self::load_files();

        // Register hooks
        self::register_hooks();

        // Run cron if enabled
        if (get_option('exi_cron_enabled', 0)) {
            self::register_cron();
        }

        return true;
    }

    /**
     * Load required plugin files
     */
    private static function load_files()
    {
        // Get plugin root directory
        $plugin_dir = dirname(plugin_dir_path(__FILE__));

        // Load main classes
        require_once $plugin_dir . '/includes/class-data-loader.php';
        require_once $plugin_dir . '/includes/class-logger.php';
        require_once $plugin_dir . '/includes/class-admin-page.php';

        // Load admin handlers if in admin
        if (is_admin()) {
            // require add_settings_error
            require_once ABSPATH . 'wp-admin/includes/template.php';
        }
    }

    /**
     * Register WordPress hooks
     */
    private static function register_hooks()
    {
        // Admin menu
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);

        // Admin assets
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    }

    /**
     * Register the admin menu
     */
    public static function register_admin_menu()
    {
        add_menu_page(
            'EAD XML Importer',                 // Page title
            'EAD Importer',                     // Menu title
            'manage_options',                   // Capability
            'ead-xml-importer',                     // Menu slug
            [__CLASS__, 'render_admin_page'],   // Callback
            'dashicons-database-import',        // Icon
        );
    }

    /**
     * Render the admin page
     */
    public static function render_admin_page()
    {
        AdminPage::render();
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets($hook)
    {
        // Only load on our plugin page
        if ('toplevel_page_ead-xml-importer' !== $hook) {
            return;
        }

        $plugin_dir_url = plugin_dir_url(dirname(__FILE__));

        wp_enqueue_style(
            'exi-admin-styles',
            $plugin_dir_url . 'assets/css/admin.css',
            [],
            filemtime(dirname(__FILE__, 2) . '/assets/css/admin.css')
        );

        wp_enqueue_script(
            'exi-admin-script',
            $plugin_dir_url . 'assets/js/admin.js',
            ['jquery'],
            filemtime(dirname(__FILE__, 2) . '/assets/js/admin.js'),
            true
        );

        // Add localized script data
        wp_localize_script('exi-admin-script', 'exiData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('exi_nonce')
        ]);
    }

    /**
     * Register actions for AJAX requests
     */
    public static function register_ajax_actions()
    {
        add_action('wp_ajax_process_xml_data', function() {
            check_ajax_referer('exi_process_xml', 'nonce');
        
            $post_type = get_option('exi_post_type');
            $meta_key = get_option('exi_post_type_meta_url');
            
            if (empty($post_type) || empty($meta_key)) {
                wp_send_json_error([
                    'message' => __("Post type or meta key not configured.", 'ead-xml-importer')
                ]);
                return;
            }
        
            // Get all posts with XML URLs
            $query = new WP_Query([
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => $meta_key,
                        'compare' => 'EXISTS'
                    ]
                ]
            ]);
        
            $total = $query->post_count;
            $current = isset($_POST['current']) ? intval($_POST['current']) : 0;

            if ($total == 0) {
                wp_send_json_success([
                    'done' => true,
                    'total' => $total,
                    'message' => sprintf(__('No posts found with XML URL meta key %s', 'ead-xml-importer'), $meta_key)
                ]);
                return;
            }
        
            if ($current >= $total) {
                wp_send_json_success([
                    'done' => true,
                    'message' => sprintf(__('Successfully processed %d posts.', 'ead-xml-importer'), $total)
                ]);
                return;
            }
        
            $post = $query->posts[$current];
            $xml_url = get_post_meta($post->ID, $meta_key, true);
            $error = null;
        
            if (empty($xml_url)) {
                $error = sprintf(__('No XML URL found for post %s', 'ead-xml-importer'), $post->post_title);
                error_log("EAD XML Importer: " . $error);
            } else {
                try {
                    $loader = new DataLoader($xml_url);
                    $result = $loader->convert();
        
                    if ($result === false) {
                        $error = sprintf(__('Failed to process XML for post %s', 'ead-xml-importer'), $post->post_title);
                        error_log("EAD XML Importer: " . $error);
                    } else {
                        $loader->populateCustomPostType($post_type, $post->ID);
                    }
                } catch (Exception $e) {
                    $error = sprintf(__('Error processing post %s: %s', 'ead-xml-importer'), 
                        $post->post_title, 
                        $e->getMessage()
                    );
                    error_log("EAD XML Importer: " . $error);
                }
            }

            update_option('exi_last_sync', date('Y-m-d H:i:s'));
        
            wp_send_json_success([
                'done' => false,
                'current' => $current + 1,
                'total' => $total,
                'progress' => round(($current + 1) / $total * 100),
                'processedTitle' => $post->post_title,
                'error' => $error // Will be null if no error occurred
            ]);
        });
    }

    /**
     * Register cron job
     */
    public static function register_cron()
    {
        if (!wp_next_scheduled('exi_cron_hook')) {
            wp_schedule_event(time(), get_option('exi_cron_interval', 'hourly'), 'exi_cron_hook');
        }

        add_action('exi_cron_hook', function() {
            $post_type = get_option('exi_post_type');
            $meta_key = get_option('exi_post_type_meta_url');

            if (empty($post_type) || empty($meta_key)) {
                return;
            }

            $args = [
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => $meta_key,
                        'compare' => 'EXISTS'
                    ]
                ]
            ];

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

            update_option('exi_last_sync', date('Y-m-d H:i:s'));
        });
    }
}
