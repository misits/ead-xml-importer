<?php

if (!defined('ABSPATH')) exit;

// Ensure we have the data
if (!isset($data) || !is_array($data)) {
    return;
}

// Extract data to make variables available to templates
extract($data);
?>

<div class="wrap" id="ead-xml-importer">
    <?php
    // Show admin notices
    settings_errors('exi_messages');
    ?>

    <div id="exi-sections">
        <?php
        // Include each section with proper scope
        require dirname(__FILE__) . '/sections/ead-settings.php';
        require dirname(__FILE__) . '/sections/ead-fetch.php';

        ?>
    </div>
</div>