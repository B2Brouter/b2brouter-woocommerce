/**
 * B2Brouter for WooCommerce - Admin JavaScript
 */

(function($) {
    'use strict';

    /**
     * Handle bulk PDF downloads on list page
     */
    function handleBulkDownload() {
        // Check if we're on the invoices list page with bulk_download parameter
        var urlParams = new URLSearchParams(window.location.search);
        var bulkCount = urlParams.get('bulk_download');

        if (!bulkCount || !b2brouterAdmin.bulk_download_ids) {
            return;
        }

        var orderIds = b2brouterAdmin.bulk_download_ids;
        var delay = 500; // 500ms delay between downloads

        // Download each PDF sequentially
        orderIds.forEach(function(orderId, index) {
            setTimeout(function() {
                // Create a hidden iframe for each download to avoid popup blockers
                var iframe = $('<iframe>', {
                    style: 'display:none;',
                    name: 'b2brouter-download-frame-' + orderId
                });
                $('body').append(iframe);

                var form = $('<form>', {
                    method: 'POST',
                    action: b2brouterAdmin.ajax_url,
                    target: 'b2brouter-download-frame-' + orderId
                });

                form.append($('<input>', {
                    name: 'action',
                    value: 'b2brouter_download_pdf',
                    type: 'hidden'
                }));

                form.append($('<input>', {
                    name: 'nonce',
                    value: b2brouterAdmin.nonce,
                    type: 'hidden'
                }));

                form.append($('<input>', {
                    name: 'order_id',
                    value: orderId,
                    type: 'hidden'
                }));

                form.append($('<input>', {
                    name: 'download',
                    value: 'download',
                    type: 'hidden'
                }));

                $('body').append(form);
                form.submit();

                // Clean up after download
                setTimeout(function() {
                    form.remove();
                    iframe.remove();
                }, 5000);
            }, index * delay);
        });

        // Clean up URL
        window.history.replaceState({}, document.title, window.location.pathname + '?page=b2brouter-invoices');
    }

    /**
     * Validate API Key
     */
    function validateApiKey() {
        var $button = $('#b2brouter_validate_key');
        var $input = $('#b2brouter_api_key');
        var $result = $('#b2brouter_validation_result');
        var apiKey = $input.val().trim();

        if (!apiKey) {
            $result
                .removeClass('success error')
                .addClass('error')
                .html('<span class="dashicons dashicons-warning"></span> ' + b2brouterAdmin.strings.error + ': ' + b2brouterAdmin.strings.api_key_required);
            return;
        }

        // Disable button and show loading
        $button.prop('disabled', true).text(b2brouterAdmin.strings.validating);
        $result.removeClass('success error').html('');

        // Hide account selector from previous validation
        $('#b2brouter_account_selector').hide();

        // AJAX request
        $.ajax({
            url: b2brouterAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'b2brouter_validate_api_key',
                nonce: b2brouterAdmin.nonce,
                api_key: apiKey
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.multiple_accounts && response.data.accounts) {
                        // Multiple accounts: show selector
                        $result
                            .addClass('success')
                            .html('<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message);

                        var $select = $('#b2brouter_account_select');
                        $select.empty();
                        $.each(response.data.accounts, function(i, account) {
                            $select.append(
                                $('<option></option>')
                                    .val(account.id)
                                    .text(account.label)
                                    .data('name', account.name)
                            );
                        });
                        $('#b2brouter_account_selector').show();
                        $('#b2brouter_account_select_result').removeClass('success error').html('');
                    } else {
                        // Single account: auto-selected
                        $result
                            .addClass('success')
                            .html('<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message);
                    }
                } else {
                    $result
                        .addClass('error')
                        .html('<span class="dashicons dashicons-warning"></span> ' + response.data.message);
                }
            },
            error: function() {
                $result
                    .addClass('error')
                    .html('<span class="dashicons dashicons-warning"></span> ' + b2brouterAdmin.strings.error);
            },
            complete: function() {
                $button.prop('disabled', false).text(b2brouterAdmin.strings.validate_key);
            }
        });
    }

    /**
     * Select Account
     */
    function selectAccount() {
        var $select = $('#b2brouter_account_select');
        var $button = $('#b2brouter_select_account');
        var $result = $('#b2brouter_account_select_result');
        var accountId = $select.val();
        var accountName = $select.find(':selected').data('name');

        if (!accountId) {
            return;
        }

        $button.prop('disabled', true);
        $result.removeClass('success error').html('');

        $.ajax({
            url: b2brouterAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'b2brouter_select_account',
                nonce: b2brouterAdmin.nonce,
                account_id: accountId,
                account_name: accountName
            },
            success: function(response) {
                if (response.success) {
                    $result
                        .addClass('success')
                        .html('<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message);
                    // Update displayed current account
                    var $current = $('#b2brouter_current_account');
                    if ($current.length) {
                        $current.text(b2brouterAdmin.strings.current_account.replace('%1$s', accountName).replace('%2$s', accountId));
                    }
                } else {
                    $result
                        .addClass('error')
                        .html('<span class="dashicons dashicons-warning"></span> ' + response.data.message);
                }
            },
            error: function() {
                $result
                    .addClass('error')
                    .html('<span class="dashicons dashicons-warning"></span> ' + b2brouterAdmin.strings.error);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }

    /**
     * Generate Invoice
     */
    function generateInvoice(orderId, $button) {
        if (!orderId || !$button) {
            return;
        }

        // Disable button and show loading
        var originalText = $button.text();
        $button.prop('disabled', true).text(b2brouterAdmin.strings.generating).addClass('b2brouter-loading');

        // AJAX request
        $.ajax({
            url: b2brouterAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'b2brouter_generate_invoice',
                nonce: b2brouterAdmin.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showNotice('success', response.data.message);

                    // Reload page after 1 second
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    // Show error message
                    showNotice('error', response.data.message);

                    // Re-enable button
                    $button.prop('disabled', false).text(originalText).removeClass('b2brouter-loading');
                }
            },
            error: function() {
                showNotice('error', b2brouterAdmin.strings.error);
                $button.prop('disabled', false).text(originalText).removeClass('b2brouter-loading');
            }
        });
    }

    /**
     * Show admin notice
     */
    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');

        // Add to page
        if ($('.wrap > h1, .wrap > h2').length) {
            $notice.insertAfter('.wrap > h1, .wrap > h2');
        } else {
            $('.wrap').prepend($notice);
        }

        // Make dismissible
        $(document).trigger('wp-updates-notice-added');

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Document Ready
     */
    $(document).ready(function() {
        // Handle bulk downloads
        handleBulkDownload();

        // Validate API key button
        $('#b2brouter_validate_key').on('click', function(e) {
            e.preventDefault();
            validateApiKey();
        });

        // Generate invoice button (in meta box)
        $(document).on('click', '.b2brouter-generate-invoice', function(e) {
            e.preventDefault();
            var orderId = $(this).data('order-id');
            generateInvoice(orderId, $(this));
        });

        // Select account button
        $('#b2brouter_select_account').on('click', function(e) {
            e.preventDefault();
            selectAccount();
        });

        // Validate on Enter key in API key input
        $('#b2brouter_api_key').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                validateApiKey();
            }
        });

        // Download/View PDF button
        $(document).on('click', '.b2brouter-download-pdf', function(e) {
            e.preventDefault();

            var $button = $(this);
            var orderId = $button.data('order-id');
            var downloadMode = $button.data('download') === 'download' ? 'download' : 'view';

            // Disable button and show loading
            var originalText = $button.html();
            $button.prop('disabled', true)
                   .html('<span class="dashicons dashicons-update dashicons-spin"></span> ' +
                         (downloadMode === 'download' ? b2brouterAdmin.strings.downloading : b2brouterAdmin.strings.loading));

            // Create form and submit to new window/tab
            var form = $('<form>', {
                method: 'POST',
                action: b2brouterAdmin.ajax_url,
                target: downloadMode === 'download' ? '_self' : '_blank'
            });

            form.append($('<input>', {
                name: 'action',
                value: 'b2brouter_download_pdf',
                type: 'hidden'
            }));

            form.append($('<input>', {
                name: 'nonce',
                value: b2brouterAdmin.nonce,
                type: 'hidden'
            }));

            form.append($('<input>', {
                name: 'order_id',
                value: orderId,
                type: 'hidden'
            }));

            form.append($('<input>', {
                name: 'download',
                value: downloadMode,
                type: 'hidden'
            }));

            // Add to body and submit
            $('body').append(form);
            form.submit();

            // Clean up and restore button after delay
            setTimeout(function() {
                form.remove();
                $button.prop('disabled', false).html(originalText);
            }, 2000);
        });

        // List table View PDF button
        $(document).on('click', '.b2brouter-list-view-pdf', function(e) {
            e.preventDefault();

            var $button = $(this);
            var orderId = $button.data('order-id');

            // Disable button and show loading
            var originalHtml = $button.html();
            $button.prop('disabled', true)
                   .html('<span class="dashicons dashicons-update dashicons-spin" style="line-height: 1.4;"></span> ' + b2brouterAdmin.strings.loading);

            // Create form and submit to new tab
            var form = $('<form>', {
                method: 'POST',
                action: b2brouterAdmin.ajax_url,
                target: '_blank'
            });

            form.append($('<input>', {
                name: 'action',
                value: 'b2brouter_download_pdf',
                type: 'hidden'
            }));

            form.append($('<input>', {
                name: 'nonce',
                value: b2brouterAdmin.nonce,
                type: 'hidden'
            }));

            form.append($('<input>', {
                name: 'order_id',
                value: orderId,
                type: 'hidden'
            }));

            form.append($('<input>', {
                name: 'download',
                value: 'view',
                type: 'hidden'
            }));

            // Add to body and submit
            $('body').append(form);
            form.submit();

            // Clean up and restore button after delay
            setTimeout(function() {
                form.remove();
                $button.prop('disabled', false).html(originalHtml);
            }, 2000);
        });

        // List table Download PDF button
        $(document).on('click', '.b2brouter-list-download-pdf', function(e) {
            e.preventDefault();

            var $button = $(this);
            var orderId = $button.data('order-id');

            // Disable button and show loading
            var originalHtml = $button.html();
            $button.prop('disabled', true)
                   .html('<span class="dashicons dashicons-update dashicons-spin" style="line-height: 1.4;"></span> ' + b2brouterAdmin.strings.downloading);

            // Create form and submit
            var form = $('<form>', {
                method: 'POST',
                action: b2brouterAdmin.ajax_url,
                target: '_self'
            });

            form.append($('<input>', {
                name: 'action',
                value: 'b2brouter_download_pdf',
                type: 'hidden'
            }));

            form.append($('<input>', {
                name: 'nonce',
                value: b2brouterAdmin.nonce,
                type: 'hidden'
            }));

            form.append($('<input>', {
                name: 'order_id',
                value: orderId,
                type: 'hidden'
            }));

            form.append($('<input>', {
                name: 'download',
                value: 'download',
                type: 'hidden'
            }));

            // Add to body and submit
            $('body').append(form);
            form.submit();

            // Clean up and restore button after delay
            setTimeout(function() {
                form.remove();
                $button.prop('disabled', false).html(originalHtml);
            }, 2000);
        });

    });

})(jQuery);
