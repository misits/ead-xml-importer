<?php

if (!defined('ABSPATH')) exit;

use EAD_XML_Importer\DataLoader;


// Verify we have the required data
if (!isset($post_types) || !isset($exi_post_type_meta_url)) {
    return;
}

?>

<div id="exi-fetch" class="exi-section">
    <div class="exi-process-section">
        <h2 class="exi-toggle">
            <span class="exi-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-cloud-down">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M12 18.004h-5.343c-2.572 -.004 -4.657 -2.011 -4.657 -4.487c0 -2.475 2.085 -4.482 4.657 -4.482c.393 -1.762 1.794 -3.2 3.675 -3.773c1.88 -.572 3.956 -.193 5.444 1c1.488 1.19 2.162 3.007 1.77 4.769h.99c1.38 0 2.573 .813 3.13 1.99" />
                    <path d="M19 16v6" />
                    <path d="M22 19l-3 3l-3 -3" />
                </svg>
                <?= __('Process XML Data', 'ead-xml-importer'); ?> <span class="desc"> - <?= __('Import the EAD Data', 'ead-xml-importer') ?>.</span>

                </span>
                <span class="exi-arrow">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-down">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M6 9l6 6l6 -6" />
                    </svg>
                </span>
        </h2>
        <div class="exi-content">
            <div id="exi-progress-container" style="display: none;">
                <div class="exi-progress-bar">
                    <div class="exi-progress-fill"></div>
                </div>
                <div class="exi-progress-status"></div>
                <div class="exi-progress-log"></div>
            </div>

            <form method="post" action="" id="exi-process-form">
                <?php wp_nonce_field('exi_process_xml', '_wpnonce_exi_process_xml'); ?>
                <p class="description">
                    <?php __('Click the button below to process XML data for all posts.', 'ead-xml-importer'); ?>
                </p>
                <p>
                    <button type="button"
                        id="exi-process-xml"
                        class="button button-primary">
                        <?php esc_html_e('Process XML Data', 'ead-xml-importer'); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function($) {
        const button = $('#exi-process-xml');
        const progressContainer = $('#exi-progress-container');
        const progressBar = $('.exi-progress-fill');
        const progressStatus = $('.exi-progress-status');
        const progressLog = $('.exi-progress-log');

        button.on('click', function() {
            button.prop('disabled', true);
            progressContainer.show();
            progressLog.empty();
            processNextBatch(0);
        });

        function processNextBatch(current) {

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'POST',
                data: {
                    action: 'process_xml_data',
                    nonce: '<?php echo wp_create_nonce('exi_process_xml'); ?>',
                    current: current
                },
                beforeSend: function(xhr) {
                    // console.log('Sending request with data:', this.data); // Debug log
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;

                        if (data.total === 0) {
                            button.prop('disabled', false);
                            progressBar.css('width', 100 + '%');
                            progressLog.append(
                                $('<div class="exi-log-error"></div>')
                                .text(data.message)
                            );
                            return;
                        }

                        // console.log('Received response:', data); // Debug log

                        progressBar.css('width', data.progress + '%');
                        progressStatus.text(data.progress + '% completed');

                        if (data.error) {
                            progressLog.append(
                                $('<div class="exi-log-error"></div>')
                                .text('Error: ' + data.error)
                            );
                        } else {
                            progressLog.append(
                                $('<div class="exi-log-success"></div>')
                                .text('Processed: ' + data.processedTitle)
                            );
                        }

                        progressLog.scrollTop(progressLog[0].scrollHeight);

                        if (!data.done) {
                            setTimeout(() => {
                                processNextBatch(data.current);
                            }, 500);
                        } else {
                            button.prop('disabled', false);
                            progressStatus.text(data.message);
                        }
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX Error:', {
                        status: jqXHR.status,
                        statusText: jqXHR.statusText,
                        responseText: jqXHR.responseText,
                        textStatus: textStatus,
                        errorThrown: errorThrown
                    });

                    progressLog.append(
                        $('<div class="exi-log-error"></div>')
                        .text(`<?php esc_html_e('Network error occurred. Please try again. Status: ', 'ead-xml-importer'); ?>${textStatus} (${jqXHR.status})`)
                    );
                    button.prop('disabled', false);
                }
            });
        }
    });
</script>