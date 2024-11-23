<?php
/**
 * Plugin Name: EAD XML Importer
 * Description: Import and manage EAD XML data in WordPress.
 * Version: 1.0.0
 * Requires at least: 5.2
 * Requires PHP: 8.0
 * Author: Martin IS IT Services
 * Author URI: https://misits.ch
 * Text Domain: ead-xml-importer
 * Domain Path: /languages
*/

namespace EAD_XML_Importer;

if (!defined('ABSPATH')) exit;

require 'utils/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/misits/ead-xml-importer',
	__FILE__,
	'ead-xml-importer'
);


/**
 * Main plugin class
 */
final class EAD_XML_Importer {
    /**
     * Plugin instance
     * @var EAD_XML_Importer
     */
    private static $instance = null;

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {
        $this->define_constants();
        $this->register_autoloader();
        $this->init_hooks();
    }

    /**
     * Define plugin constants
     */
    private function define_constants() {
        define('EAD_VERSION', '1.0.0');
        define('EAD_PLUGIN_FILE', __FILE__);
        define('EAD_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('EAD_PLUGIN_URL', plugin_dir_url(__FILE__));
    }

    /**
     * Register autoloader
     */
    private function register_autoloader() {
        spl_autoload_register(function ($class) {
            // Project-specific namespace prefix
            $prefix = 'EAD_XML_Importer\\';
            $base_dir = plugin_dir_path(__FILE__) . 'includes/';
        
            // Check if the class uses the namespace prefix
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }
        
            // Get the relative class name
            $relative_class = substr($class, $len);
        
            // Convert class name to file name:
            // Example: AdminPage becomes class-admin-page.php
            $file_name = 'class-' . strtolower(str_replace('_', '-', 
                preg_replace('/([a-z])([A-Z])/', '$1-$2', $relative_class)
            )) . '.php';
        
            $file = $base_dir . $file_name;
        
            // If the file exists, require it
            if (file_exists($file)) {
                require $file;
            }
        });
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize plugin
        add_action('plugins_loaded', [$this, 'init_plugin']);

        // Admin hooks
        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        }
    }

    /**
     * Initialize plugin
     */
    public function init_plugin() {
        // Load text domain if needed
        load_plugin_textdomain('ead-xml-importer', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Initialize the plugin
        Loader::init();
    }

     /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on plugin pages
        if (!$this->is_plugin_page($hook)) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'adl-admin',
            ADL_PLUGIN_URL . 'assets/css/admin.css',
            [],
            ADL_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'adl-admin',
            ADL_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            ADL_VERSION,
            true
        );

        // Localize script
        wp_localize_script('adl-admin', 'adl', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adl_preview_data'),
        ]);
    }

     /**
     * Check if current page is plugin page
     */
    private function is_plugin_page($hook) {
        $plugin_pages = [
            'toplevel_page_ead-xml-importer',
        ];
        
        return in_array($hook, $plugin_pages);
    }
}

// Initialize the plugin
function adl_init() {
    return EAD_XML_Importer::get_instance();
}

// Start the plugin
adl_init();