<?php
/**
 * Invoice Email Sender
 * Renders invoice-email.html with dynamic placeholders and sends via wp_mail()
 */
if (!defined('ABSPATH')) exit;

class WLWP_Invoice_Email {

    // Term labels — keep in sync with invoice-pdf-template.php
    private static $term_labels = [
        'weekly'           => 'Weekly',
        'fortnightly'      => 'Fortnightly',
        'monthly'          => 'Monthly',
        'upon_completion'  => 'Upon Completion',
        'net_7'            => 'Net 7 Days',
        'net_14'           => 'Net 14 Days',
        'net_30'           => 'Net 30 Days',
    ];

    /**
     * Send invoice email to client.
     *
     * @param int    $invoice_id
     * @param string $to         Override recipient (default: invoice client email)
     * @return bool
     */
    public static function send($invoice_id, $to = '') {
        global $wpdb;

        $table   = $wpdb->prefix . 'wl_invoices';
        $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $invoice_id));
        if (!$invoice) return false;

        $items_table = $wpdb->prefix . 'wl_invoice_items';
        $items = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $items_table WHERE invoice_id = %d ORDER BY sort_order ASC", $invoice_id),
            ARRAY_A
        );

        // ── Generate (or re-use) PDF ────────────────────────────────────────
        $pdf_url = !empty($invoice->pdf_path)
            ? $invoice->pdf_path
            : WLWP_Invoice_PDF_Generator::generate($invoice_id);

        // ── Build placeholders ──────────────────────────────────────────────
        $payment_terms = !empty($invoice->payment_terms)
            ? $invoice->payment_terms
            : get_option('wl_invoice_payment_terms', 'weekly');

        if ($payment_terms === 'custom') {
            $term_label = get_option('wl_invoice_payment_terms_custom', 'As Agreed');
        } else {
            $term_label = self::$term_labels[$payment_terms]
                        ?? ucwords(str_replace('_', ' ', $payment_terms));
        }

        $invoice_number = get_option('wl_invoice_prefix', 'INV-') . str_pad($invoice->id, 4, '0', STR_PAD_LEFT);
        $invoice_date   = date('d/m/Y', strtotime($invoice->invoice_date ?: $invoice->created_at));

        // Totals
        /* $sub_total = 0;
        foreach ($items as $item) $sub_total += floatval($item['fee']);
        $tax       = floatval($invoice->tax_amount ?? 0);
        $total     = $sub_total + $tax; */
        $total = 0;
        foreach ($items as $item) $total += floatval($item['fee']);
        $tax       = round( $total / 11, 2 );
        $sub_total = $total - $tax;

        // Line items HTML rows
        $rows_html = '';
        foreach ($items as $i => $item) {
            $item_pt = !empty($item['payment_term']) ? $item['payment_term'] : $payment_terms;
            $item_term_label = ($item_pt === 'custom')
                ? get_option('wl_invoice_payment_terms_custom', 'As Agreed')
                : (self::$term_labels[$item_pt] ?? ucwords(str_replace('_', ' ', $item_pt)));

            $bg = ($i % 2 === 0) ? '#ffffff' : '#f8f9fd';
            $rows_html .= sprintf(
                '<tr style="background:%s;">
                    <td style="padding:9px 12px;border-bottom:1px solid #e4eaf4;font-size:12px;">%d</td>
                    <td style="padding:9px 12px;border-bottom:1px solid #e4eaf4;font-size:12px;">%s</td>
                    <td style="padding:9px 12px;border-bottom:1px solid #e4eaf4;font-size:12px;">%s</td>
                    <td style="padding:9px 12px;border-bottom:1px solid #e4eaf4;font-size:12px;text-align:right;">$%s</td>
                </tr>',
                $bg,
                $i + 1,
                esc_html($item['description']),
                esc_html($item_term_label),
                number_format(floatval($item['fee']), 2)
            );
        }

        $tax_row_html = ($tax > 0)
            ? '<tr><td colspan="3" style="padding:6px 12px;text-align:right;font-size:12px;">GST (10%):</td>'
              . '<td style="padding:6px 12px;text-align:right;font-size:12px;">$' . number_format($tax, 2) . '</td></tr>'
            : '';

        $logo_url = get_option('wl_invoice_logo_url', '');
        $logo_html = $logo_url
            ? '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr(get_bloginfo('name')) . '" style="max-height:50px;display:block;">'
            : '<span style="font-size:22px;font-weight:bold;color:#ffffff;letter-spacing:1px;">' . esc_html(get_bloginfo('name')) . '</span>';

        $placeholders = [
            '{{INVOICE_NUMBER}}'      => esc_html($invoice_number),
            '{{INVOICE_DATE}}'        => esc_html($invoice_date),
            '{{CLIENT_NAME}}'         => esc_html($invoice->client_name),
            '{{BILL_TO_NAME}}'        => esc_html($invoice->client_company ?: $invoice->client_name),
            '{{BILL_TO_ATTN}}'        => esc_html($invoice->client_name),
            '{{CLIENT_ADDRESS}}'      => nl2br(esc_html($invoice->client_address ?? '')),
            '{{ACCOUNT_NAME}}'        => esc_html(get_option('wl_invoice_account_name', '')),
            '{{BANK_NAME}}'           => esc_html(get_option('wl_invoice_bank_name', 'NAB')),
            '{{BSB}}'                 => esc_html(get_option('wl_invoice_bsb', '')),
            '{{ACCOUNT_NUMBER}}'      => esc_html(get_option('wl_invoice_account_number', '')),
            '{{PAYMENT_TERMS_LABEL}}' => esc_html($term_label),
            '{{INVOICE_ITEMS_ROWS}}'  => $rows_html,
            '{{TAX_ROW}}'             => $tax_row_html,
            '{{TAX_AMOUNT}}'          => '$' . number_format($tax, 2),
            '{{SUB_TOTAL}}'           => '$' . number_format($sub_total, 2),
            '{{TOTAL}}'               => '$' . number_format($total, 2),
            '{{INVOICE_PDF_URL}}'     => esc_url($pdf_url ?: '#'),
            '{{INVOICE_NOTES}}'       => nl2br(esc_html($invoice->notes ?? '')),
            '{{SITE_NAME}}'           => esc_html(get_bloginfo('name')),
            '{{SITE_URL}}'            => esc_html(home_url()),
            '{{SITE_PHONE}}'          => esc_html(get_option('wl_invoice_phone', '')),
            '{{SITE_EMAIL}}'          => esc_html(get_option('wl_invoice_email', get_option('admin_email'))),
            '{{SITE_LOGO_URL}}'       => esc_url($logo_url),
            // simple {{#if X}} … {{/if}} blocks handled below
        ];

        // ── Load & render template ──────────────────────────────────────────
        //RECEIPT content 
        $tpl_path = plugin_dir_path(__FILE__) . 'templates/emails/invoice-email.html';
        if (!file_exists($tpl_path)) {
            error_log('Invoice email template not found: ' . $tpl_path);
            return false;
        }
        $body = file_get_contents($tpl_path);
        $body = self::process_conditionals($body, $placeholders);
        $body = str_replace(array_keys($placeholders), array_values($placeholders), $body);

        // ── wp_mail ─────────────────────────────────────────────────────────
        $recipient  = $to ?: $invoice->client_email;
        $from_name  = get_bloginfo('name');
        $from_email = get_option('wl_invoice_email', get_option('admin_email'));
        $subject    = 'Invoice ' . $invoice_number . ' from ' . $from_name;

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];

        // Attach PDF if saved locally
        $attachments = [];
        if (!empty($invoice->pdf_path)) {
            $upload_dir = wp_upload_dir();
            $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $invoice->pdf_path);
            if (file_exists($local_path)) $attachments[] = $local_path;
        }

        $sent = wp_mail($recipient, $subject, $body, $headers, $attachments);

        if ($sent) {
            $wpdb->update(
                $wpdb->prefix . 'wl_invoices',
                ['sent_at' => current_time('mysql'), 'status' => 'sent'],
                ['id' => $invoice_id]
            );
        }

        return $sent;
    }

    /**
     * Process simple {{#if VAR}} … {{/if}} and {{#if VAR}} … {{else}} … {{/if}} blocks.
     */
    private static function process_conditionals($html, $vars) {
        // {{#if VAR}} block {{else}} fallback {{/if}}
        $html = preg_replace_callback(
            '/\{\{#if ([A-Z0-9_]+)\}\}(.*?)\{\{else\}\}(.*?)\{\{\/if\}\}/s',
            function($m) use ($vars) {
                $key = '{{' . $m[1] . '}}';
                $val = $vars[$key] ?? '';
                return (!empty($val) && $val !== '{{' . $m[1] . '}}') ? $m[2] : $m[3];
            },
            $html
        );
        // {{#if VAR}} block {{/if}}
        $html = preg_replace_callback(
            '/\{\{#if ([A-Z0-9_]+)\}\}(.*?)\{\{\/if\}\}/s',
            function($m) use ($vars) {
                $key = '{{' . $m[1] . '}}';
                $val = $vars[$key] ?? '';
                return (!empty($val) && $val !== '{{' . $m[1] . '}}') ? $m[2] : '';
            },
            $html
        );
        return $html;
    }
}
