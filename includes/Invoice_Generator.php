<?php
/**
 * Invoice Generator - Integrates with B2Brouter PHP SDK
 *
 * @package B2Brouter\WooCommerce
 * @since 1.0.0
 */

namespace B2Brouter\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Invoice Generator class
 *
 * Handles invoice generation and API communication with B2Brouter
 *
 * @since 1.0.0
 */
class Invoice_Generator {

    /**
     * Countries that use rectificative invoices (negative amounts) instead of credit notes
     *
     * @since 1.0.0
     * @var array
     */
    const RECTIFICATIVE_COUNTRIES = array('ES'); // Spain uses rectificative invoices

    /**
     * EU member states (for intra-community supply detection)
     *
     * @since 1.0.0
     * @var array
     */
    const EU_COUNTRIES = array(
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
    );

    /**
     * Tax names by country
     *
     * @since 1.0.0
     * @var array
     */
    const TAX_NAMES = array(
        'ES' => 'IVA',
        'FR' => 'TVA',
        'DE' => 'MwSt',
        'IT' => 'IVA',
        'PT' => 'IVA',
        'NL' => 'BTW',
        'BE' => 'TVA',
        'AT' => 'USt',
        'GB' => 'VAT',
        'IE' => 'VAT',
        'US' => 'Sales Tax',
        'CA' => 'GST',
        'AU' => 'GST',
        'NZ' => 'GST',
        'default' => 'VAT'
    );

    /**
     * Settings instance
     *
     * @since 1.0.0
     * @var Settings
     */
    private $settings;

    /**
     * B2Brouter API client
     *
     * @since 1.0.0
     * @var \B2BRouter\Client\B2BRouterClient|null
     */
    private $client = null;

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param Settings $settings Settings instance
     */
    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    /**
     * Get B2Brouter client instance
     *
     * @since 1.0.0
     * @return \B2BRouter\Client\B2BRouterClient The B2Brouter API client
     * @throws \Exception If API key is not configured or SDK is not found
     */
    private function get_client() {
        if (null !== $this->client) {
            return $this->client;
        }

        $api_key = $this->settings->get_api_key();

        if (empty($api_key)) {
            throw new \Exception(__('API key not configured', 'b2brouter-woocommerce'));
        }

        if (!class_exists('B2BRouter\B2BRouterClient')) {
            throw new \Exception(__('B2Brouter PHP SDK not found', 'b2brouter-woocommerce'));
        }

        // Create client with environment setting
        $options = array('api_base' => $this->settings->get_api_base_url());
        $this->client = new \B2BRouter\B2BRouterClient($api_key, $options);

        return $this->client;
    }

    /**
     * Generate invoice from WooCommerce order or refund
     *
     * @since 1.0.0
     * @param int $order_id The WooCommerce order or refund ID
     * @return array{success: bool, invoice_id?: string, invoice_number?: string, message: string} Generation result
     */
    public function generate_invoice($order_id) {
        try {
            // Get order (could be regular order or refund)
            $order = wc_get_order($order_id);

            if (!$order) {
                throw new \Exception(__('Order not found', 'b2brouter-woocommerce'));
            }

            $is_refund = $this->is_refund($order);

            // Check if invoice already generated
            if ($order->get_meta('_b2brouter_invoice_id')) {
                $message = $is_refund
                    ? __('Credit note already generated for this refund', 'b2brouter-woocommerce')
                    : __('Invoice already generated for this order', 'b2brouter-woocommerce');
                throw new \Exception($message);
            }

            // For refunds, validate parent order has invoice
            if ($is_refund) {
                $parent_invoice_info = $this->get_parent_invoice_info($order);
                if (!$parent_invoice_info) {
                    throw new \Exception(__('Cannot generate credit note: parent order has no invoice', 'b2brouter-woocommerce'));
                }
            }

            // Get client
            $client = $this->get_client();

            // Get account ID
            $account_id = $this->settings->get_account_id();

            if (empty($account_id)) {
                throw new \Exception(__('Account ID not configured. Please validate your API key.', 'b2brouter-woocommerce'));
            }

            // Prepare invoice data (handles both regular invoices and refunds)
            $invoice_data = $this->prepare_invoice_data($order);

            // Create and send invoice via B2Brouter API (single call)
            // send_after_import is outside the invoice object to trigger immediate sending
            $invoice = $client->invoices->create($account_id, array(
                'invoice' => $invoice_data,
                'send_after_import' => true
            ));

            // Store invoice ID in order meta
            $order->add_meta_data('_b2brouter_invoice_id', $invoice['id'], true);
            $order->add_meta_data('_b2brouter_invoice_number', $invoice['number'] ?? '', true);
            // Store the series_code we sent to the API (from invoice_data, not from response)
            $order->add_meta_data('_b2brouter_invoice_series_code', $invoice_data['series_code'] ?? '', true);
            $order->add_meta_data('_b2brouter_invoice_date', current_time('mysql'), true);

            // Store initial status from API response
            if (isset($invoice['state'])) {
                $initial_status = strtolower($invoice['state']);
                $order->add_meta_data('_b2brouter_invoice_status', $initial_status, true);
                $order->add_meta_data('_b2brouter_invoice_status_updated', time(), true);
            }

            $order->save();

            // Schedule a single status check 10 seconds in the future
            wp_schedule_single_event(time() + 10, 'b2brouter_sync_single_invoice', array($order_id));

            // Add order note with context-aware message
            // For refunds, add note to parent order instead of refund itself
            $note_target = $is_refund && isset($parent_invoice_info['parent_order'])
                ? $parent_invoice_info['parent_order']
                : $order;

            // Format invoice number with series code (use series_code from request data)
            $formatted_number = self::format_invoice_number(
                $invoice['number'] ?? '',
                $invoice_data['series_code'] ?? ''
            );

            $note_message = $is_refund
                ? sprintf(
                    __('B2Brouter credit note generated successfully. Invoice: %s (ID: %s)', 'b2brouter-woocommerce'),
                    $formatted_number,
                    $invoice['id']
                  )
                : sprintf(
                    __('B2Brouter invoice generated successfully. Invoice: %s (ID: %s)', 'b2brouter-woocommerce'),
                    $formatted_number,
                    $invoice['id']
                  );

            $note_target->add_order_note($note_message);

            // Increment transaction counter
            $this->settings->increment_transaction_count();

            // Auto-save PDF if enabled
            if ($this->settings->get_auto_save_pdf()) {
                try {
                    // Retry PDF download with exponential backoff
                    // PDF generation is asynchronous, so we use polling with retries
                    $pdf_result = API_Retry::execute(function() use ($order_id) {
                        return $this->save_invoice_pdf($order_id, false);
                    }, array(
                        'max_attempts' => 5,
                        'initial_delay' => 1,
                        'retryable_exceptions' => array(
                            'B2BRouter\Exception\ResourceNotFoundException',
                        ),
                    ));

                    if ($pdf_result['success']) {
                        $pdf_note = $is_refund
                            ? __('Credit note PDF automatically downloaded and cached locally', 'b2brouter-woocommerce')
                            : __('Invoice PDF automatically downloaded and cached locally', 'b2brouter-woocommerce');
                        $note_target->add_order_note($pdf_note);
                    }
                } catch (\Exception $e) {
                    // PDF download failed after retries, but invoice was created successfully
                    // Log the error but don't fail the entire operation
                    error_log('B2Brouter auto-save PDF failed after retries: ' . $e->getMessage());
                }
            }

            $success_message = $is_refund
                ? __('Credit note generated successfully', 'b2brouter-woocommerce')
                : __('Invoice generated successfully', 'b2brouter-woocommerce');

            return array(
                'success' => true,
                'invoice_id' => $invoice['id'],
                'invoice_number' => $invoice['number'] ?? '',
                'message' => $success_message,
                'is_refund' => $is_refund
            );

        } catch (\Exception $e) {
            // Log error
            error_log('B2Brouter Invoice Generation Error: ' . $e->getMessage());

            // Add order note with error
            if (isset($order) && $order) {
                // For refunds, add error note to parent order if available
                $error_note_target = $order;
                if (isset($is_refund) && $is_refund && isset($parent_invoice_info['parent_order'])) {
                    $error_note_target = $parent_invoice_info['parent_order'];
                }

                $error_message = isset($is_refund) && $is_refund
                    ? sprintf(
                        __('B2Brouter credit note generation failed: %s', 'b2brouter-woocommerce'),
                        $e->getMessage()
                      )
                    : sprintf(
                        __('B2Brouter invoice generation failed: %s', 'b2brouter-woocommerce'),
                        $e->getMessage()
                      );

                $error_note_target->add_order_note($error_message);
            }

            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Prepare invoice data from WooCommerce order
     *
     * @since 1.0.0
     * @param \WC_Order|\WC_Order_Refund $order The WooCommerce order or refund
     * @return array The invoice data array for B2Brouter API
     */
    private function prepare_invoice_data($order) {
        $is_refund = $this->is_refund($order);
        $parent_order = null;
        $parent_invoice_info = null;

        // For refunds, get parent order and invoice info
        if ($is_refund) {
            $parent_invoice_info = $this->get_parent_invoice_info($order);
            if (!$parent_invoice_info) {
                throw new \Exception(__('Parent order has no invoice. Cannot create refund invoice.', 'b2brouter-woocommerce'));
            }
            $parent_order = $parent_invoice_info['parent_order'];
        }

        // Use parent order for billing details if this is a refund
        $billing_order = $is_refund && $parent_order ? $parent_order : $order;

        // Get billing details
        $billing_name = trim($billing_order->get_billing_first_name() . ' ' . $billing_order->get_billing_last_name());
        if (empty($billing_name)) {
            $billing_name = $billing_order->get_billing_company();
        }

        // Prepare contact (customer) data
        $contact = array(
            'name' => $billing_name,
            'email' => $billing_order->get_billing_email(),
            'country' => $billing_order->get_billing_country(),
            'address' => $billing_order->get_billing_address_1(),
            'city' => $billing_order->get_billing_city(),
            'postalcode' => $billing_order->get_billing_postcode(),
        );

        // Add address line 2 if present
        if ($billing_order->get_billing_address_2()) {
            $contact['address'] .= ', ' . $billing_order->get_billing_address_2();
        }

        // Add TIN/VAT number if available
        $tin = Customer_Fields::get_order_tin($order);
        if (empty($tin) && $is_refund && $parent_order) {
            $tin = Customer_Fields::get_order_tin($parent_order);
        }
        if (!empty($tin)) {
            $contact['tin_value'] = $tin;
            // B2BRouter uses TIN scheme 9999 for generic tax IDs
            // This can be adjusted based on country if needed
            $contact['tin_scheme'] = 9999;
        }

        // Determine if we should use credit notes (positive amounts) or rectificative (negative amounts)
        $country = $billing_order->get_billing_country();
        $is_rectificative = $this->uses_rectificative_invoices($country);
        $amount_multiplier = ($is_refund && !$is_rectificative) ? -1 : 1;

        // Prepare line items
        $invoice_lines = array();

        // For refunds, if no items exist, use parent order items
        $items = $order->get_items();
        $use_parent_items = false;
        if ($is_refund && empty($items) && $parent_order) {
            $items = $parent_order->get_items();
            $use_parent_items = true;
        }

        // Determine which order object to use for item calculations
        $item_order = $use_parent_items ? $parent_order : $order;

        foreach ($items as $item) {
            $quantity = $item->get_quantity();
            $price = (float) $item_order->get_item_subtotal($item, false, false);

            // For refunds using parent items, negate the amounts for rectificative invoices
            if ($is_refund && $use_parent_items && $is_rectificative) {
                $quantity = -$quantity;
                // Price stays positive for per-unit price
            }

            // For credit notes (non-rectificative), convert negative amounts to positive
            if ($amount_multiplier === -1) {
                $quantity = abs($quantity);
                $price = abs($price);
            }

            $line = array(
                'description' => $item->get_name(),
                'quantity' => $quantity,
                'price' => $price,
            );

            // Always add tax information (PEPPOL compliant)
            $tax_rate = $this->get_item_tax_rate($item, $item_order);
            $tax_info = $this->get_peppol_tax_category($item, $item_order, $tax_rate);
            $line['taxes_attributes'] = array(
                array(
                    'name' => $tax_info['name'],
                    'category' => $tax_info['category'],
                    'percent' => $tax_info['percent'],
                )
            );

            $invoice_lines[] = $line;
        }

        // Add shipping as line item if exists
        $shipping_total = $item_order->get_shipping_total();

        // For refunds using parent items, negate shipping for rectificative invoices
        if ($is_refund && $use_parent_items && $is_rectificative) {
            $shipping_total = -$shipping_total;
        }

        if ($shipping_total != 0) {
            $shipping_price = (float) $shipping_total;

            // For credit notes, convert to positive
            if ($amount_multiplier === -1) {
                $shipping_price = abs($shipping_price);
            }

            $shipping_line = array(
                'description' => __('Shipping', 'b2brouter-woocommerce'),
                'quantity' => 1,
                'price' => $shipping_price,
            );

            // Always add tax information for shipping (PEPPOL compliant)
            $shipping_tax_rate = $this->get_shipping_tax_rate($item_order);
            $merchant_country = $this->get_merchant_country();
            $tax_name = $this->get_tax_name($merchant_country);

            // Check for reverse charge
            if ($this->is_reverse_charge($item_order)) {
                $shipping_line['taxes_attributes'] = array(
                    array(
                        'name' => $tax_name,
                        'category' => 'AE',
                        'percent' => 0.0,
                    )
                );
            } elseif ($shipping_tax_rate == 0) {
                // Exempt shipping (most common for zero-rate shipping)
                $shipping_line['taxes_attributes'] = array(
                    array(
                        'name' => $tax_name,
                        'category' => 'E',
                        'percent' => 0.0,
                    )
                );
            } else {
                // Standard rate shipping
                $shipping_line['taxes_attributes'] = array(
                    array(
                        'name' => $tax_name,
                        'category' => 'S',
                        'percent' => abs($shipping_tax_rate),
                    )
                );
            }

            $invoice_lines[] = $shipping_line;
        }

        // Determine invoice type (IssuedInvoice or IssuedSimplifiedInvoice)
        $invoice_type = $this->get_invoice_type($order);

        // Get series code and invoice number based on settings
        $series_code = $this->get_series_code_for_invoice($is_refund);
        $invoice_number = $this->generate_invoice_number($order, $series_code, $is_refund);

        // Prepare base invoice data
        $invoice_data = array(
            'type' => $invoice_type,
            'date' => current_time('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime(current_time('Y-m-d') . ' +30 days')),
            'currency' => $order->get_currency(),
            'language' => substr(get_locale(), 0, 2),  // Get language from WordPress locale (e.g., 'es' from 'es_ES')
            'contact' => $contact,
            'contact_email_override' => $contact['email'], // Override email to enable sending for IssuedSimplifiedInvoice
            'invoice_lines_attributes' => $invoice_lines,
            'extra_info' => sprintf(
                __('WooCommerce Order #%s', 'b2brouter-woocommerce'),
                $is_refund ? $order->get_id() : $order->get_order_number()
            ),
        );

        // Add series code if configured
        if (!empty($series_code)) {
            $invoice_data['series_code'] = $series_code;
        }

        // Add invoice number if not using automatic numbering
        if ($invoice_number !== null) {
            $invoice_data['number'] = $invoice_number;
        }

        // Add refund-specific fields
        if ($is_refund && $parent_invoice_info) {
            // Add amended invoice reference
            $invoice_data['amended_number'] = $parent_invoice_info['invoice_number'];

            // Parse invoice date (format: Y-m-d)
            $invoice_date = $parent_invoice_info['invoice_date'];
            if (!empty($invoice_date)) {
                // Convert MySQL datetime to Y-m-d format
                $date_obj = new \DateTime($invoice_date);
                $invoice_data['amended_date'] = $date_obj->format('Y-m-d');
            }

            // Add refund reason if available
            if (method_exists($order, 'get_reason') && !empty($order->get_reason())) {
                $invoice_data['amended_reason'] = $order->get_reason();
            }

            // Add is_credit_note flag for non-rectificative countries
            // This is an undocumented parameter used by B2Brouter API
            if (!$is_rectificative) {
                $invoice_data['is_credit_note'] = true;
            }
        }

        return $invoice_data;
    }

    /**
     * Get tax rate for order item
     *
     * @since 1.0.0
     * @param \WC_Order_Item_Product $item The order item
     * @param \WC_Order $order The order
     * @return float The tax rate percentage
     */
    private function get_item_tax_rate($item, $order) {
        $taxes = $item->get_taxes();

        if (empty($taxes['total'])) {
            return 0;
        }

        $tax_total = array_sum($taxes['total']);
        $item_total = $item->get_total();

        if ($item_total > 0) {
            return round(($tax_total / $item_total) * 100, 2);
        }

        return 0;
    }

    /**
     * Get tax rate for shipping
     *
     * @since 1.0.0
     * @param \WC_Order $order The order
     * @return float The shipping tax rate percentage
     */
    private function get_shipping_tax_rate($order) {
        $shipping_total = $order->get_shipping_total();
        $shipping_tax = $order->get_shipping_tax();

        if ($shipping_total > 0 && $shipping_tax > 0) {
            return round(($shipping_tax / $shipping_total) * 100, 2);
        }

        return 0;
    }

    /**
     * Check if order is a refund
     *
     * @since 1.0.0
     * @param \WC_Order|\WC_Order_Refund $order The order object
     * @return bool True if order is a refund
     */
    private function is_refund($order) {
        return $order->get_type() === 'shop_order_refund';
    }

    /**
     * Determine invoice type based on TIN presence
     *
     * @since 1.0.0
     * @param \WC_Order|\WC_Order_Refund $order The order object
     * @return string Invoice type (IssuedInvoice or IssuedSimplifiedInvoice)
     */
    private function get_invoice_type($order) {
        $tin = Customer_Fields::get_order_tin($order);

        // For refunds, get TIN from parent order if not on refund itself
        if ($this->is_refund($order) && empty($tin)) {
            $parent_order = wc_get_order($order->get_parent_id());
            if ($parent_order) {
                $tin = Customer_Fields::get_order_tin($parent_order);
            }
        }

        // Determine type based on TIN presence
        return !empty($tin) ? 'IssuedInvoice' : 'IssuedSimplifiedInvoice';
    }

    /**
     * Check if country uses rectificative invoices (negative amounts)
     *
     * @since 1.0.0
     * @param string $country_code Two-letter country code
     * @return bool True if country uses rectificative invoices
     */
    private function uses_rectificative_invoices($country_code) {
        return in_array(strtoupper($country_code), self::RECTIFICATIVE_COUNTRIES, true);
    }

    /**
     * Get parent invoice information for refund
     *
     * @since 1.0.0
     * @param \WC_Order_Refund $refund_order The refund order
     * @return array|null Array with invoice info or null if not found
     */
    private function get_parent_invoice_info($refund_order) {
        $parent_id = $refund_order->get_parent_id();
        if (!$parent_id) {
            return null;
        }

        $parent_order = wc_get_order($parent_id);
        if (!$parent_order) {
            return null;
        }

        $invoice_id = $parent_order->get_meta('_b2brouter_invoice_id');
        $invoice_number = $parent_order->get_meta('_b2brouter_invoice_number');
        $invoice_date = $parent_order->get_meta('_b2brouter_invoice_date');

        if (empty($invoice_id)) {
            return null;
        }

        return array(
            'invoice_id' => $invoice_id,
            'invoice_number' => $invoice_number,
            'invoice_date' => $invoice_date,
            'parent_order' => $parent_order,
        );
    }

    /**
     * Check if order has invoice
     *
     * @since 1.0.0
     * @param int $order_id The order ID
     * @return bool True if order has invoice, false otherwise
     */
    public function has_invoice($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        return !empty($order->get_meta('_b2brouter_invoice_id'));
    }

    /**
     * Get invoice ID for order
     *
     * @since 1.0.0
     * @param int $order_id The order ID
     * @return string|null The invoice ID or null if not found
     */
    public function get_invoice_id($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }
        return $order->get_meta('_b2brouter_invoice_id');
    }

    /**
     * Download invoice as PDF from B2Brouter API
     *
     * @since 1.0.0
     * @param string $invoice_id The B2Brouter invoice ID
     * @return array{success: bool, pdf_data?: string, filename?: string, message: string}
     */
    public function download_invoice_pdf($invoice_id) {
        try {
            if (empty($invoice_id)) {
                throw new \Exception(__('Invoice ID is required', 'b2brouter-woocommerce'));
            }

            // Get B2Brouter client
            $client = $this->get_client();

            // Download PDF using SDK v0.9.1
            $pdf_data = $client->invoices->downloadPdf($invoice_id);

            // Validate PDF data
            if (empty($pdf_data)) {
                throw new \Exception(__('PDF data is empty', 'b2brouter-woocommerce'));
            }

            // Generate filename
            $filename = sanitize_file_name("invoice-{$invoice_id}.pdf");

            return array(
                'success' => true,
                'pdf_data' => $pdf_data,
                'filename' => $filename,
                'message' => __('PDF downloaded successfully', 'b2brouter-woocommerce')
            );

        } catch (\B2BRouter\Exception\ResourceNotFoundException $e) {
            error_log('B2Brouter PDF Download - Invoice not found: ' . $invoice_id);
            return array(
                'success' => false,
                'message' => __('Invoice not found', 'b2brouter-woocommerce')
            );

        } catch (\B2BRouter\Exception\AuthenticationException $e) {
            error_log('B2Brouter PDF Download - Authentication failed: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('API authentication failed. Please check your API key.', 'b2brouter-woocommerce')
            );

        } catch (\B2BRouter\Exception\PermissionException $e) {
            error_log('B2Brouter PDF Download - Permission denied: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('You do not have permission to download this invoice.', 'b2brouter-woocommerce')
            );

        } catch (\B2BRouter\Exception\ApiErrorException $e) {
            error_log('B2Brouter PDF Download - API error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => sprintf(
                    __('API error: %s', 'b2brouter-woocommerce'),
                    $e->getMessage()
                )
            );

        } catch (\Exception $e) {
            error_log('B2Brouter PDF Download - Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Save invoice PDF to local storage
     *
     * @since 1.0.0
     * @param int $order_id The WooCommerce order ID
     * @param bool $force_download Force new download even if file exists
     * @return array{success: bool, file_path?: string, file_url?: string, message: string}
     */
    public function save_invoice_pdf($order_id, $force_download = false) {
        try {
            // Get order
            $order = wc_get_order($order_id);

            if (!$order) {
                throw new \Exception(__('Order not found', 'b2brouter-woocommerce'));
            }

            // Get invoice ID
            $invoice_id = $order->get_meta('_b2brouter_invoice_id');

            if (empty($invoice_id)) {
                throw new \Exception(__('No invoice found for this order', 'b2brouter-woocommerce'));
            }

            // Check if PDF already exists and we're not forcing download
            $existing_path = $order->get_meta('_b2brouter_invoice_pdf_path');
            if (!$force_download && !empty($existing_path) && file_exists($existing_path)) {
                $upload_dir = wp_upload_dir();
                $filename = basename($existing_path);
                $file_url = $upload_dir['baseurl'] . '/b2brouter-invoices/' . $filename;

                return array(
                    'success' => true,
                    'file_path' => $existing_path,
                    'file_url' => $file_url,
                    'message' => __('Using existing PDF file', 'b2brouter-woocommerce'),
                    'cached' => true
                );
            }

            // Download PDF from API
            $result = $this->download_invoice_pdf($invoice_id);

            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            // Create storage directory
            $storage_path = $this->settings->get_pdf_storage_path();

            if (!file_exists($storage_path)) {
                if (!wp_mkdir_p($storage_path)) {
                    throw new \Exception(__('Failed to create PDF storage directory', 'b2brouter-woocommerce'));
                }

                // Add security files
                $this->secure_pdf_directory($storage_path);
            }

            // Generate unique filename
            $filename = sanitize_file_name("invoice-order-{$order_id}-{$invoice_id}.pdf");
            $file_path = $storage_path . '/' . $filename;

            // Save PDF file
            $bytes_written = file_put_contents($file_path, $result['pdf_data']);

            if ($bytes_written === false) {
                throw new \Exception(__('Failed to save PDF file', 'b2brouter-woocommerce'));
            }

            // Store metadata in order
            $order->update_meta_data('_b2brouter_invoice_pdf_path', $file_path);
            $order->update_meta_data('_b2brouter_invoice_pdf_filename', $filename);
            $order->update_meta_data('_b2brouter_invoice_pdf_size', $bytes_written);
            $order->update_meta_data('_b2brouter_invoice_pdf_date', current_time('mysql'));
            $order->save();

            // Generate URL (note: direct access blocked by .htaccess)
            $upload_dir = wp_upload_dir();
            $file_url = $upload_dir['baseurl'] . '/b2brouter-invoices/' . $filename;

            return array(
                'success' => true,
                'file_path' => $file_path,
                'file_url' => $file_url,
                'file_size' => $bytes_written,
                'message' => __('PDF saved successfully', 'b2brouter-woocommerce')
            );

        } catch (\Exception $e) {
            error_log('B2Brouter Save PDF Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Secure PDF storage directory
     *
     * @since 1.0.0
     * @param string $directory_path Directory to secure
     * @return void
     */
    private function secure_pdf_directory($directory_path) {
        // Add .htaccess to prevent direct access
        $htaccess_path = $directory_path . '/.htaccess';
        $htaccess_content = "# B2Brouter Invoice PDFs - Access Denied\n";
        $htaccess_content .= "Options -Indexes\n";
        $htaccess_content .= "<Files *.pdf>\n";
        $htaccess_content .= "    Require all denied\n";
        $htaccess_content .= "</Files>\n";

        file_put_contents($htaccess_path, $htaccess_content);

        // Add index.php to prevent directory listing
        $index_path = $directory_path . '/index.php';
        file_put_contents($index_path, "<?php\n// Silence is golden.");
    }

    /**
     * Stream invoice PDF directly to browser
     *
     * @since 1.0.0
     * @param int $order_id The WooCommerce order ID
     * @param bool $download Force download vs inline display
     * @return void Outputs PDF and exits
     */
    public function stream_invoice_pdf($order_id, $download = false) {
        try {
            // Get order
            $order = wc_get_order($order_id);

            if (!$order) {
                wp_die(
                    esc_html__('Order not found', 'b2brouter-woocommerce'),
                    esc_html__('Error', 'b2brouter-woocommerce'),
                    array('response' => 404)
                );
            }

            // Check permissions
            if (!$this->can_access_invoice($order)) {
                wp_die(
                    esc_html__('You do not have permission to access this invoice.', 'b2brouter-woocommerce'),
                    esc_html__('Permission Denied', 'b2brouter-woocommerce'),
                    array('response' => 403)
                );
            }

            // Get invoice ID
            $invoice_id = $order->get_meta('_b2brouter_invoice_id');

            // If this is a refund with no invoice, but parent has invoice, generate it on-demand
            if (empty($invoice_id) && $order->get_type() === 'shop_order_refund') {
                $parent_id = $order->get_parent_id();
                if ($parent_id && $this->has_invoice($parent_id)) {
                    // Generate credit note on-demand for accounting compliance
                    $result = $this->generate_invoice($order_id);
                    if ($result['success']) {
                        // Refresh to get the new invoice ID
                        $order = wc_get_order($order_id);
                        $invoice_id = $order->get_meta('_b2brouter_invoice_id');
                    }
                }
            }

            if (empty($invoice_id)) {
                wp_die(
                    esc_html__('No invoice found for this order', 'b2brouter-woocommerce'),
                    esc_html__('Error', 'b2brouter-woocommerce'),
                    array('response' => 404)
                );
            }

            // Check if PDF exists locally
            $pdf_path = $order->get_meta('_b2brouter_invoice_pdf_path');

            if (!empty($pdf_path) && file_exists($pdf_path)) {
                // Use cached PDF
                $pdf_data = file_get_contents($pdf_path);
                $filename = basename($pdf_path);
            } else {
                // Download and save PDF (this will cache it)
                $save_result = $this->save_invoice_pdf($order_id, false);

                if (!$save_result['success']) {
                    wp_die(
                        esc_html($save_result['message']),
                        esc_html__('Error', 'b2brouter-woocommerce'),
                        array('response' => 500)
                    );
                }

                // Read the saved PDF file
                $pdf_data = file_get_contents($save_result['file_path']);
                $filename = basename($save_result['file_path']);
            }

            // Clear any previous output
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Set headers
            header('Content-Type: application/pdf');
            header('Content-Length: ' . strlen($pdf_data));

            if ($download) {
                header('Content-Disposition: attachment; filename="' . $filename . '"');
            } else {
                header('Content-Disposition: inline; filename="' . $filename . '"');
            }

            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            header('Expires: 0');

            // Output PDF
            echo $pdf_data;
            exit;

        } catch (\Exception $e) {
            error_log('B2Brouter Stream PDF Error: ' . $e->getMessage());
            wp_die(
                esc_html($e->getMessage()),
                esc_html__('Error', 'b2brouter-woocommerce'),
                array('response' => 500)
            );
        }
    }

    /**
     * Check if current user can access invoice for order
     *
     * @since 1.0.0
     * @param \WC_Order $order The order
     * @return bool True if user has access
     */
    private function can_access_invoice($order) {
        // Admins can always access
        if (current_user_can('manage_woocommerce')) {
            return true;
        }

        // If this is a refund, get the parent order
        $check_order = $order;
        if ($order->get_type() === 'shop_order_refund') {
            $parent_id = $order->get_parent_id();
            if (!$parent_id) {
                return false;
            }
            $check_order = wc_get_order($parent_id);
            if (!$check_order) {
                return false;
            }
        }

        // Check if current user is the order customer
        $user_id = get_current_user_id();
        if ($user_id > 0 && (int) $check_order->get_customer_id() === $user_id) {
            return true;
        }

        // Check for guest access with order key
        if (isset($_GET['key']) && $check_order->get_order_key() === $_GET['key']) {
            return true;
        }

        return false;
    }

    /**
     * Delete stored PDF for an order
     *
     * @since 1.0.0
     * @param int $order_id The order ID
     * @return bool True on success
     */
    public function delete_invoice_pdf($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return false;
        }

        $pdf_path = $order->get_meta('_b2brouter_invoice_pdf_path');

        if (!empty($pdf_path) && file_exists($pdf_path)) {
            if (unlink($pdf_path)) {
                // Clear metadata
                $order->delete_meta_data('_b2brouter_invoice_pdf_path');
                $order->delete_meta_data('_b2brouter_invoice_pdf_filename');
                $order->delete_meta_data('_b2brouter_invoice_pdf_size');
                $order->delete_meta_data('_b2brouter_invoice_pdf_date');
                $order->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Attach PDF to WooCommerce emails
     *
     * @since 1.0.0
     * @param array $attachments Existing attachments
     * @param string $email_id Email ID
     * @param \WC_Order $order Order object
     * @return array Modified attachments
     */
    public function attach_pdf_to_email($attachments, $email_id, $order) {
        // Check if this is an object we can work with
        if (!$order || !is_a($order, 'WC_Order')) {
            return $attachments;
        }

        // Refresh order data to get latest metadata (in case invoice was just generated)
        $order_id = $order->get_id();
        $order = wc_get_order($order_id);

        if (!$order) {
            return $attachments;
        }

        // Check if order has an invoice
        $invoice_id = $order->get_meta('_b2brouter_invoice_id');
        if (empty($invoice_id)) {
            return $attachments;
        }

        // Check settings for which emails to attach to
        $attach = false;

        if ($email_id === 'customer_completed_order' && $this->settings->get_attach_to_order_completed()) {
            $attach = true;
        }

        if ($email_id === 'customer_invoice' && $this->settings->get_attach_to_customer_invoice()) {
            $attach = true;
        }

        if ($email_id === 'customer_refunded_order' && $this->settings->get_attach_to_refunded_order()) {
            $attach = true;
        }

        if (!$attach) {
            return $attachments;
        }

        // For refunded order emails, attach the refund's credit note/rectificative PDF instead of parent invoice
        if ($email_id === 'customer_refunded_order') {
            $refunds = $order->get_refunds();

            // Attach PDFs for all refunds that have invoices
            foreach ($refunds as $refund) {
                // Refresh refund to get latest metadata (in case invoice was just generated)
                $refund = wc_get_order($refund->get_id());

                $refund_invoice_id = $refund->get_meta('_b2brouter_invoice_id');

                // If refund has no invoice yet, generate it on-demand
                // Credit notes auto-generate when parent invoice exists, for accounting compliance
                if (empty($refund_invoice_id)) {
                    $result = $this->generate_invoice($refund->get_id());
                    if ($result['success']) {
                        // Refresh refund to get the new invoice metadata
                        $refund = wc_get_order($refund->get_id());
                        $refund_invoice_id = $refund->get_meta('_b2brouter_invoice_id');
                    }
                }

                if (!empty($refund_invoice_id)) {
                    $refund_pdf_path = $refund->get_meta('_b2brouter_invoice_pdf_path');

                    // If no cached PDF, try to download it
                    if (empty($refund_pdf_path) || !file_exists($refund_pdf_path)) {
                        $save_result = $this->save_invoice_pdf($refund->get_id(), false);
                        if ($save_result['success']) {
                            $refund_pdf_path = $save_result['file_path'];
                        }
                    }

                    // Add refund PDF to attachments
                    if (!empty($refund_pdf_path) && file_exists($refund_pdf_path)) {
                        $attachments[] = $refund_pdf_path;
                    }
                }
            }

            return $attachments;
        }

        // For other emails, attach the order's invoice PDF
        $pdf_path = $order->get_meta('_b2brouter_invoice_pdf_path');

        // If no cached PDF, try to download it temporarily
        if (empty($pdf_path) || !file_exists($pdf_path)) {
            $save_result = $this->save_invoice_pdf($order->get_id(), false);

            if ($save_result['success']) {
                $pdf_path = $save_result['file_path'];
            } else {
                error_log('B2Brouter Email Attachment: Failed to get PDF for order ' . $order->get_id());
                return $attachments;
            }
        }

        // Add PDF to attachments if it exists
        if (!empty($pdf_path) && file_exists($pdf_path)) {
            $attachments[] = $pdf_path;
        }

        return $attachments;
    }

    /**
     * Clean up old PDF files
     *
     * @since 1.0.0
     * @param int $days Delete PDFs older than this many days
     * @return array{deleted: int, errors: int} Results
     */
    public function cleanup_old_pdfs($days = 90) {
        $storage_path = $this->settings->get_pdf_storage_path();
        $deleted = 0;
        $errors = 0;

        if (!file_exists($storage_path)) {
            return array('deleted' => 0, 'errors' => 0);
        }

        $pdf_files = glob($storage_path . '/*.pdf');

        if (empty($pdf_files)) {
            return array('deleted' => 0, 'errors' => 0);
        }

        $cutoff_time = time() - ($days * DAY_IN_SECONDS);

        foreach ($pdf_files as $file) {
            $file_time = filemtime($file);

            if ($file_time && $file_time < $cutoff_time) {
                if (unlink($file)) {
                    $deleted++;

                    // Try to clean up order metadata
                    $this->cleanup_order_metadata_for_file($file);
                } else {
                    $errors++;
                    error_log('B2Brouter Cleanup: Failed to delete ' . $file);
                }
            }
        }

        return array('deleted' => $deleted, 'errors' => $errors);
    }

    /**
     * Clean up order metadata for deleted PDF file
     *
     * @since 1.0.0
     * @param string $file_path The deleted file path
     * @return void
     */
    private function cleanup_order_metadata_for_file($file_path) {
        global $wpdb;

        // Find orders with this PDF path in metadata
        $meta_key = '_b2brouter_invoice_pdf_path';
        $order_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = %s AND meta_value = %s",
            $meta_key,
            $file_path
        ));

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->delete_meta_data('_b2brouter_invoice_pdf_path');
                $order->delete_meta_data('_b2brouter_invoice_pdf_filename');
                $order->delete_meta_data('_b2brouter_invoice_pdf_size');
                $order->delete_meta_data('_b2brouter_invoice_pdf_date');
                $order->save();
            }
        }
    }

    /**
     * Format invoice number with series code prefix
     *
     * @since 1.0.0
     * @param string $invoice_number The invoice number
     * @param string $series_code The series code
     * @return string The formatted invoice number
     */
    public static function format_invoice_number($invoice_number, $series_code = '') {
        if (empty($series_code) || empty($invoice_number)) {
            return $invoice_number;
        }
        return $series_code . '-' . $invoice_number;
    }

    /**
     * Get formatted invoice number from order meta
     *
     * @since 1.0.0
     * @param \WC_Order|\WC_Order_Refund $order The order or refund object
     * @return string The formatted invoice number
     */
    public static function get_formatted_invoice_number($order) {
        $invoice_number = $order->get_meta('_b2brouter_invoice_number');
        $series_code = $order->get_meta('_b2brouter_invoice_series_code');
        return self::format_invoice_number($invoice_number, $series_code);
    }

    /**
     * Get series code for invoice based on type
     *
     * @since 1.0.0
     * @param bool $is_credit_note Whether this is a credit note
     * @return string The series code to use
     */
    private function get_series_code_for_invoice($is_credit_note) {
        if ($is_credit_note) {
            $series_code = $this->settings->get_credit_note_series_code();
            // Fall back to invoice series code if credit note series is not set
            if (empty($series_code)) {
                $series_code = $this->settings->get_invoice_series_code();
            }
        } else {
            $series_code = $this->settings->get_invoice_series_code();
        }

        return $series_code;
    }

    /**
     * Generate invoice number based on configured pattern
     *
     * @since 1.0.0
     * @param \WC_Order|\WC_Order_Refund $order The order or refund object
     * @param string $series_code The series code being used
     * @param bool $is_credit_note Whether this is a credit note
     * @return string|null The invoice number, or null for automatic numbering
     */
    private function generate_invoice_number($order, $series_code, $is_credit_note) {
        $pattern = $this->settings->get_invoice_numbering_pattern();

        switch ($pattern) {
            case 'automatic':
                // Don't send number field, let B2Brouter assign it
                return null;

            case 'woocommerce':
                // Use WooCommerce order number
                // For refunds, get the parent order number
                if ($this->is_refund($order)) {
                    $parent_order = wc_get_order($order->get_parent_id());
                    return $parent_order ? $parent_order->get_order_number() : $order->get_id();
                }
                return $order->get_order_number();

            case 'sequential':
                // Get next sequential number for this series
                return (string) $this->settings->get_next_sequential_number($series_code);

            case 'custom':
                // Apply custom pattern
                $custom_pattern = $this->settings->get_custom_numbering_pattern();
                return $this->apply_custom_pattern($custom_pattern, $order);

            default:
                // Default to WooCommerce order number
                // For refunds, get the parent order number
                if ($this->is_refund($order)) {
                    $parent_order = wc_get_order($order->get_parent_id());
                    return $parent_order ? $parent_order->get_order_number() : $order->get_id();
                }
                return $order->get_order_number();
        }
    }

    /**
     * Apply custom pattern to generate invoice number
     *
     * @since 1.0.0
     * @param string $pattern The pattern with placeholders
     * @param \WC_Order|\WC_Order_Refund $order The order or refund object
     * @return string The generated invoice number
     */
    private function apply_custom_pattern($pattern, $order) {
        $replacements = array(
            '{order_id}' => $order->get_id(),
            '{order_number}' => $order->get_order_number(),
            '{year}' => date('Y'),
            '{month}' => date('m'),
            '{day}' => date('d'),
        );

        return str_replace(array_keys($replacements), array_values($replacements), $pattern);
    }

    /**
     * Get merchant (supplier) country from WooCommerce settings
     *
     * @since 1.0.0
     * @return string Two-letter country code
     */
    private function get_merchant_country() {
        $default_country = get_option('woocommerce_default_country', '');

        // Format is "COUNTRY:STATE" or just "COUNTRY"
        if (strpos($default_country, ':') !== false) {
            list($country, $state) = explode(':', $default_country);
            return strtoupper($country);
        }

        return strtoupper($default_country);
    }

    /**
     * Get tax name based on country
     *
     * @since 1.0.0
     * @param string $country_code Two-letter country code
     * @return string Tax name (VAT, IVA, GST, etc.)
     */
    private function get_tax_name($country_code) {
        $country_code = strtoupper($country_code);

        if (isset(self::TAX_NAMES[$country_code])) {
            return self::TAX_NAMES[$country_code];
        }

        return self::TAX_NAMES['default'];
    }

    /**
     * Check if country is in the EU
     *
     * @since 1.0.0
     * @param string $country_code Two-letter country code
     * @return bool True if country is in EU
     */
    private function is_eu_country($country_code) {
        return in_array(strtoupper($country_code), self::EU_COUNTRIES, true);
    }

    /**
     * Detect if order qualifies for reverse charge (intra-EU B2B)
     *
     * @since 1.0.0
     * @param \WC_Order|\WC_Order_Refund $order The order
     * @return bool True if reverse charge applies
     */
    private function is_reverse_charge($order) {
        // Get customer TIN
        $tin = Customer_Fields::get_order_tin($order);

        // For refunds, check parent order TIN if not on refund
        if ($this->is_refund($order) && empty($tin)) {
            $parent_order = wc_get_order($order->get_parent_id());
            if ($parent_order) {
                $tin = Customer_Fields::get_order_tin($parent_order);
            }
        }

        // No TIN = not B2B = no reverse charge
        if (empty($tin)) {
            return false;
        }

        $merchant_country = $this->get_merchant_country();
        $customer_country = $order->get_billing_country();

        // Both must be in EU
        if (!$this->is_eu_country($merchant_country) || !$this->is_eu_country($customer_country)) {
            return false;
        }

        // Must be different countries
        if ($merchant_country === $customer_country) {
            return false;
        }

        // All conditions met: intra-EU B2B with different countries
        return true;
    }

    /**
     * Get PEPPOL tax category and details for an item
     *
     * @since 1.0.0
     * @param \WC_Order_Item_Product $item The order item
     * @param \WC_Order $order The order
     * @param float $tax_rate The calculated tax rate percentage
     * @return array{category: string, name: string, percent: float} Tax information
     */
    private function get_peppol_tax_category($item, $order, $tax_rate) {
        $merchant_country = $this->get_merchant_country();
        $tax_name = $this->get_tax_name($merchant_country);

        // Check for reverse charge (AE)
        if ($this->is_reverse_charge($order)) {
            return array(
                'category' => 'AE',
                'name' => $tax_name,
                'percent' => 0.0,
            );
        }

        // Get product to check tax status
        $product = $item->get_product();

        if ($product) {
            $tax_status = $product->get_tax_status();

            // Product marked as non-taxable (Not Subject to tax)
            if ($tax_status === 'none') {
                return array(
                    'category' => 'NS',
                    'name' => $tax_name,
                    'percent' => 0.0,
                );
            }

            // Product is taxable, check if it's zero-rated
            $tax_class = $product->get_tax_class();

            if ($tax_rate == 0 && $tax_class === 'zero-rate') {
                return array(
                    'category' => 'Z',
                    'name' => $tax_name,
                    'percent' => 0.0,
                );
            }
        }

        // Tax rate is 0 but product is taxable (likely exempt)
        if ($tax_rate == 0) {
            return array(
                'category' => 'E',
                'name' => $tax_name,
                'percent' => 0.0,
            );
        }

        // Standard rate
        return array(
            'category' => 'S',
            'name' => $tax_name,
            'percent' => abs($tax_rate),
        );
    }
}
