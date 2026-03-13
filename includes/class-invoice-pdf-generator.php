<?php
/**
 * Invoice PDF Generator — mPDF
 * Mirrors the pattern of CCES_PDF_Generator (class-pdf-generator.php)
 *
 * Usage:
 *   $pdf_url = WLWP_Invoice_PDF_Generator::generate($invoice_id);
 */
if (!defined('ABSPATH')) exit;

class WLWP_Invoice_PDF_Generator {

    // ── Public entry point ──────────────────────────────────────────────────
    public static function generate($invoice_id) {
        global $wpdb;

        $table   = $wpdb->prefix . 'wl_invoices';
        $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $invoice_id));
        if (!$invoice) return false;

        $items_table = $wpdb->prefix . 'wl_invoice_items';
        $items = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $items_table WHERE invoice_id = %d ORDER BY sort_order ASC", $invoice_id),
            ARRAY_A
        );

        // ── Invoice settings (saved in Invoice Settings admin page) ─────────
        $site_name      = get_bloginfo('name');
        $site_logo_url  = get_option('wl_invoice_logo_url', '');
        $site_phone     = get_option('wl_invoice_phone', '');
        $site_email     = get_option('wl_invoice_email', get_option('admin_email'));
        $site_url       = get_option('wl_invoice_website', home_url());
        $bank_name      = get_option('wl_invoice_bank_name', 'NAB');
        $bsb            = get_option('wl_invoice_bsb', '');
        $account_name   = get_option('wl_invoice_account_name', '');
        $account_number = get_option('wl_invoice_account_number', '');

        // ── Payment terms — per-invoice override or fall back to global ─────
        $payment_terms = !empty($invoice->payment_terms)
            ? $invoice->payment_terms
            : get_option('wl_invoice_payment_terms', 'weekly');

        // ── Formatted fields ────────────────────────────────────────────────
        $invoice_number = get_option('wl_invoice_prefix', 'INV-') . str_pad($invoice->id, 4, '0', STR_PAD_LEFT);
        $invoice_date   = date('d/m/Y', strtotime($invoice->invoice_date ?: $invoice->created_at));
        $bill_to_name   = $invoice->client_company ?: $invoice->client_name;
        $bill_to_attn   = $invoice->client_name;

        $html = self::renderTemplate(
            $invoice, $items,
            $site_name, $site_logo_url, $site_phone, $site_email, $site_url,
            $bank_name, $bsb, $account_name, $account_number,
            $payment_terms, $invoice_number, $invoice_date,
            $bill_to_name, $bill_to_attn
        );

        $pdf_url = self::convertToPDF($html, $invoice_id);

        if ($pdf_url) {
            $wpdb->update($table, ['pdf_path' => $pdf_url], ['id' => $invoice_id]);
        }

        return $pdf_url;
    }

    // ── Render HTML template ────────────────────────────────────────────────
    private static function renderTemplate(
        $invoice, $items,
        $site_name, $site_logo_url, $site_phone, $site_email, $site_url,
        $bank_name, $bsb, $account_name, $account_number,
        $payment_terms, $invoice_number, $invoice_date,
        $bill_to_name, $bill_to_attn
    ) {
        ob_start();
        $tpl = plugin_dir_path(__FILE__) . 'templates/invoice-pdf-template.php';
        if (file_exists($tpl)) {
            include $tpl;
        } else {
            error_log('Invoice PDF template not found: ' . $tpl);
        }
        return ob_get_clean();
    }

    // ── Convert HTML → PDF via mPDF ─────────────────────────────────────────
    private static function convertToPDF($html, $invoice_id) {
        $autoload = plugin_dir_path(__FILE__) . '../../vendor/autoload.php'; // adjust path
        if (!file_exists($autoload)) {
            error_log('mPDF not found. Run: composer require mpdf/mpdf');
            return false;
        }
        require_once $autoload;

        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode'          => 'utf-8',
                'format'        => 'A4',
                'orientation'   => 'P',
                'margin_top'    => 0,
                'margin_right'  => 0,
                'margin_bottom' => 15,
                'margin_left'   => 0,
                'tempDir'       => sys_get_temp_dir(),
            ]);

            $mpdf->SetTitle('Invoice #' . $invoice_id);
            $mpdf->SetAuthor(get_bloginfo('name'));
            $mpdf->SetCreator(get_bloginfo('name'));
            $mpdf->WriteHTML($html);

            $upload_dir = wp_upload_dir();
            $pdf_dir    = $upload_dir['basedir'] . '/wl-invoices/';
            if (!file_exists($pdf_dir)) wp_mkdir_p($pdf_dir);

            $filename = 'invoice-' . $invoice_id . '-' . time() . '.pdf';
            $filepath = $pdf_dir . $filename;

            $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);

            return $upload_dir['baseurl'] . '/wl-invoices/' . $filename;

        } catch (\Exception $e) {
            error_log('Invoice mPDF Error: ' . $e->getMessage());
            return false;
        }
    }

    // ── Stream PDF to browser (download) ────────────────────────────────────
    public static function stream($invoice_id) {
        $autoload = plugin_dir_path(__FILE__) . '../../vendor/autoload.php';
        if (!file_exists($autoload)) return false;
        require_once $autoload;

        global $wpdb;
        $table   = $wpdb->prefix . 'wl_invoices';
        $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $invoice_id));
        if (!$invoice) return false;

        // Re-use generate() to build HTML then stream
        $items_table = $wpdb->prefix . 'wl_invoice_items';
        $items = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $items_table WHERE invoice_id = %d ORDER BY sort_order ASC", $invoice_id),
            ARRAY_A
        );

        $payment_terms  = !empty($invoice->payment_terms) ? $invoice->payment_terms : get_option('wl_invoice_payment_terms', 'weekly');
        $invoice_number = get_option('wl_invoice_prefix', 'INV-') . str_pad($invoice->id, 4, '0', STR_PAD_LEFT);
        $invoice_date   = date('d/m/Y', strtotime($invoice->invoice_date ?: $invoice->created_at));

        $html = self::renderTemplate(
            $invoice, $items,
            get_bloginfo('name'),
            get_option('wl_invoice_logo_url', ''),
            get_option('wl_invoice_phone', ''),
            get_option('wl_invoice_email', get_option('admin_email')),
            get_option('wl_invoice_website', home_url()),
            get_option('wl_invoice_bank_name', 'NAB'),
            get_option('wl_invoice_bsb', ''),
            get_option('wl_invoice_account_name', ''),
            get_option('wl_invoice_account_number', ''),
            $payment_terms, $invoice_number, $invoice_date,
            $invoice->client_company ?: $invoice->client_name,
            $invoice->client_name
        );

        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode'          => 'utf-8',
                'format'        => 'A4',
                'orientation'   => 'P',
                'margin_top'    => 0, 'margin_right' => 0,
                'margin_bottom' => 15, 'margin_left' => 0,
                'tempDir'       => sys_get_temp_dir(),
            ]);
            $mpdf->SetTitle('Invoice #' . $invoice_id);
            $mpdf->WriteHTML($html);
            $mpdf->Output('invoice-' . $invoice_number . '.pdf', \Mpdf\Output\Destination::INLINE);
            exit;
        } catch (\Exception $e) {
            error_log('Invoice mPDF stream error: ' . $e->getMessage());
            return false;
        }
    }
}