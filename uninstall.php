<?php

// Prevent direct access.
defined( 'ABSPATH' ) or exit;

// if uninstall.php is not called by WordPress, die
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

// Delete options.