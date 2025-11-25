/**
 * B2Brouter Customer JavaScript
 *
 * Handles customer-facing PDF download functionality
 *
 * @package B2Brouter\WooCommerce
 * @since 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        /**
         * Handle PDF download button clicks
         * Target both class names (WooCommerce uses action key as class)
         */
        $(document).on('click', '.b2brouter-customer-download-pdf, .download_invoice_pdf', function(e) {
            e.preventDefault();

            var $button = $(this);
            var orderId = $button.data('order-id');
            var orderKey = $button.data('order-key');

            // Prevent multiple clicks
            if ($button.hasClass('loading')) {
                return;
            }

            // Get order ID from My Account table if not set
            if (!orderId) {
                // Try to extract from aria-label first (e.g., "Download Invoice PDF order number 70")
                var ariaLabel = $button.attr('aria-label');
                if (ariaLabel) {
                    var ariaMatch = ariaLabel.match(/order\s+number\s+(\d+)/i);
                    if (ariaMatch && ariaMatch[1]) {
                        orderId = ariaMatch[1];
                        if (b2brouterCustomer.debug) {
                            console.log('Extracted order ID from aria-label:', orderId);
                        }
                    }
                }

                // If still not found, try from table row
                if (!orderId) {
                    var $row = $button.closest('tr');

                    // Try multiple selectors to find order number link
                    var $orderLink = $row.find('.woocommerce-orders-table__cell-order-number a, td.order-number a, .order-number a');
                    var orderUrl = $orderLink.attr('href');

                    if (b2brouterCustomer.debug) {
                        console.log('Order link found:', $orderLink.length);
                        console.log('Order URL:', orderUrl);
                    }

                    if (orderUrl) {
                        // Try different URL patterns
                        var matches = orderUrl.match(/[?&]order(?:-id)?[=\/](\d+)/i) ||
                                      orderUrl.match(/\/order\/(\d+)/i) ||
                                      orderUrl.match(/view-order\/(\d+)/i);

                        if (matches && matches[1]) {
                            orderId = matches[1];
                            if (b2brouterCustomer.debug) {
                                console.log('Extracted order ID from URL:', orderId);
                            }
                        }
                    }

                    // Try to get order key from view order link
                    var $viewLink = $row.find('.woocommerce-button.view, a.view, .button.view');
                    var viewOrderUrl = $viewLink.attr('href');

                    if (viewOrderUrl) {
                        var keyMatch = viewOrderUrl.match(/[?&]key=([^&]+)/);
                        if (keyMatch && keyMatch[1]) {
                            orderKey = keyMatch[1];
                            if (b2brouterCustomer.debug) {
                                console.log('Extracted order key:', orderKey);
                            }
                        }
                    }
                }
            }

            if (!orderId) {
                if (b2brouterCustomer.debug) {
                    console.error('Could not find order ID');
                }
                alert(b2brouterCustomer.strings.error);
                return;
            }

            // Show loading state
            var originalHtml = $button.html();
            $button.addClass('loading')
                   .prop('disabled', true)
                   .html('<span class="dashicons dashicons-update dashicons-spin"></span> ' +
                         b2brouterCustomer.strings.downloading);

            // Create hidden form for POST request
            var form = $('<form>', {
                method: 'POST',
                action: b2brouterCustomer.ajax_url,
                target: '_blank'
            });

            // Add form fields
            form.append($('<input>', {
                name: 'action',
                value: 'b2brouter_customer_download_pdf',
                type: 'hidden'
            }));

            form.append($('<input>', {
                name: 'nonce',
                value: b2brouterCustomer.nonce,
                type: 'hidden'
            }));

            form.append($('<input>', {
                name: 'order_id',
                value: orderId,
                type: 'hidden'
            }));

            // Add order key if available (for guest orders)
            if (orderKey) {
                form.append($('<input>', {
                    name: 'order_key',
                    value: orderKey,
                    type: 'hidden'
                }));
            }

            // Submit form
            $('body').append(form);
            form.submit();

            // Clean up and restore button after delay
            setTimeout(function() {
                form.remove();
                $button.removeClass('loading')
                       .prop('disabled', false)
                       .html(originalHtml);
            }, 2000);
        });

        /**
         * Handle My Account orders table - add data attributes to buttons
         * This runs on page load to pre-populate data attributes
         */
        $('.b2brouter-customer-download-pdf, .download_invoice_pdf').each(function() {
            var $button = $(this);

            // Try to extract from aria-label first
            var ariaLabel = $button.attr('aria-label');
            if (ariaLabel) {
                var ariaMatch = ariaLabel.match(/order\s+number\s+(\d+)/i);
                if (ariaMatch && ariaMatch[1]) {
                    $button.attr('data-order-id', ariaMatch[1]);
                    if (b2brouterCustomer.debug) {
                        console.log('Set order ID from aria-label:', ariaMatch[1]);
                    }
                }
            }

            var $row = $button.closest('tr');

            if ($row.length === 0) {
                // Not in a table, might be on thank you page which already has attributes
                return;
            }

            // If no order ID yet, get from order number link
            if (!$button.attr('data-order-id')) {
                var $orderLink = $row.find('.woocommerce-orders-table__cell-order-number a, td.order-number a, .order-number a');
                var orderUrl = $orderLink.attr('href');

                if (orderUrl) {
                    var matches = orderUrl.match(/[?&]order(?:-id)?[=\/](\d+)/i) ||
                                  orderUrl.match(/\/order\/(\d+)/i) ||
                                  orderUrl.match(/view-order\/(\d+)/i);

                    if (matches && matches[1]) {
                        $button.attr('data-order-id', matches[1]);
                        if (b2brouterCustomer.debug) {
                            console.log('Set order ID from URL:', matches[1]);
                        }
                    }
                }
            }

            // Get order key from view order link
            var $viewLink = $row.find('.woocommerce-button.view, a.view, .button.view');
            var viewOrderUrl = $viewLink.attr('href');

            if (viewOrderUrl) {
                var keyMatch = viewOrderUrl.match(/[?&]key=([^&]+)/);
                if (keyMatch && keyMatch[1]) {
                    $button.attr('data-order-key', keyMatch[1]);
                    if (b2brouterCustomer.debug) {
                        console.log('Set order key on button:', keyMatch[1]);
                    }
                }
            }
        });

        // Debug: Log if scripts loaded
        if (b2brouterCustomer.debug) {
            console.log('B2Brouter customer scripts loaded');
            console.log('Found buttons:', $('.b2brouter-customer-download-pdf, .download_invoice_pdf').length);
        }

    });

})(jQuery);
