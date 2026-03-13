<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PT_PDF {

    /**
     * Generate a PDF invoice/receipt.
     *
     * @param  string $type     'invoice' or 'receipt'
     * @param  object $invoice  Invoice row
     * @param  object $term     Term row
     * @param  object $enquiry  Enquiry row (with caravan_name)
     * @return array|false      ['url'=>..., 'path'=>...] or false
     */
    public static function generate( $type, $invoice, $term, $enquiry ) {

        // ── Find mPDF autoloader ──────────────────────────────────────────────
        $autoload_paths = [
            PT_PATH . 'vendor/autoload.php',
            defined( 'CCES_PLUGIN_DIR' ) ? CCES_PLUGIN_DIR . 'vendor/autoload.php' : '',
            ABSPATH . 'vendor/autoload.php',
        ];
        $autoload = '';
        foreach ( $autoload_paths as $p ) {
            if ( $p && file_exists( $p ) ) { $autoload = $p; break; }
        }
        if ( ! $autoload ) {
            error_log( 'PT_PDF: mPDF autoload not found.' );
            return false;
        }
        require_once $autoload;

        // ── Company / branding settings ───────────────────────────────────────
        $co_name     = get_option( 'pt_company_name',        get_bloginfo('name') );
        $co_website  = get_option( 'pt_company_website',     home_url() );
        $co_phone    = get_option( 'pt_company_phone',       '' );
        $co_email    = get_option( 'pt_company_email',       get_option('admin_email') );
        $co_tagline  = get_option( 'pt_company_tagline',     '' );
        $co_logo_url = get_option( 'pt_company_logo_url',    '' );
        $bk_acc_name = get_option( 'pt_bank_account_name',   '' );
        $bk_name     = get_option( 'pt_bank_name',           '' );
        $bk_bsb      = get_option( 'pt_bank_bsb',            '' );
        $bk_acc_no   = get_option( 'pt_bank_account_number', '' );
        $inv_notes   = get_option( 'pt_invoice_notes',       'Thank you for your business.' );

        $payment_lbl     = $invoice->payment_type === 'monthly' ? 'Monthly' : 'Weekly';
        $inv_date        = date( 'd/m/Y', strtotime( $invoice->created_at ) );
        $is_receipt      = ( $type === 'receipt' );
        $title_text      = $is_receipt ? 'PAYMENT RECEIPT' : 'INVOICE';
        $co_website_clean = str_replace( ['https://','http://'], '', $co_website );

        // ── GST Calculations (GST-inclusive) ──────────────────────────────────
        $grand_total = floatval( $invoice->total_amount );
        $gst_amount  = round( $grand_total / 11, 2 );
        $sub_total   = $grand_total - $gst_amount;

        // ── All terms ─────────────────────────────────────────────────────────
        $all_terms = PT_Invoice_Manager::get_terms( $invoice->id );

        // ── Build terms table rows (each line shows ex-tax amount) ────────────
        $terms_rows = '';
        foreach ( $all_terms as $i => $t ) {
            $due = $t->due_date ? date( 'd M Y', strtotime( $t->due_date ) ) : '';
            $bg  = ( $i % 2 === 0 ) ? '#ffffff' : '#f9fafb';
            $paid_badge = $t->status === 'paid'
                ? ' <img src="' . esc_url( PT_URL . 'assets/image/paid_latest.png' ) . '" 
                    alt="Paid" 
                    style="width:60px;margin-left:6px;vertical-align:middle;">'
                : '';

            // Per-term GST-inclusive breakdown
            $term_inc   = floatval( $t->amount );
            $term_gst   = round( $term_inc / 11, 2 );
            $term_extax = $term_inc - $term_gst;

            $terms_rows .= '<tr style="background:' . $bg . ';">'
                . '<td class="center" style="padding:10px 12px;font-size:12px;color:#888;border-bottom:1px solid #f0f0f0;">' . intval( $t->term_number ) . '</td>'
                . '<td style="padding:10px 12px;font-size:12px;border-bottom:1px solid #f0f0f0;">'
                    . '<strong>' . esc_html( $t->term_name ) . '</strong>' . $paid_badge
                    . ( $due ? '<br><span class="due-date">Due: ' . esc_html( $due ) . '</span>' : '' )
                . '</td>'
                . '<td class="right" style="padding:10px 12px;font-size:12px;font-weight:700;border-bottom:1px solid #f0f0f0;">$' . number_format( $term_extax, 2 ) . ' <span style="font-size:10px;color:#888;font-weight:400;">(ex. tax)</span></td>'
                . '</tr>';
        }

        // ── Build logo HTML ───────────────────────────────────────────────────
        $logo_html = $co_logo_url
            ? '<img src="' . esc_url( $co_logo_url ) . '" alt="' . esc_attr( $co_name ) . '" style="max-height:55px;max-width:200px;">'
            : '<div class="site-name">' . esc_html( $co_name ) . '</div>';

        $tagline_html = $co_tagline
            ? '<div class="tagline">' . esc_html( $co_tagline ) . '</div>'
            : '';

        // ── Build bank rows HTML ──────────────────────────────────────────────
        $bank_rows = '';
        foreach ( [
            'Account Name'   => $bk_acc_name,
            'Bank Name'      => $bk_name,
            'BSB Number'     => $bk_bsb,
            'ACC Number'     => $bk_acc_no,
        ] as $lbl => $val ) {
            if ( ! $val ) continue;
            $bank_rows .= '<strong>' . esc_html( $lbl ) . ':</strong> ' . esc_html( $val ) . '<br>';
        }

        // ── Build header contact lines ────────────────────────────────────────
        $company_phone_text   = $co_phone   ? esc_html( $co_phone ) . '<br>'   : '';
        $company_email_text   = $co_email   ? esc_html( $co_email ) . '<br>'   : '';
        $company_website_text = $co_website ? esc_html( $co_website_clean )     : '';

        // ── Build customer info lines ─────────────────────────────────────────
        $customer_email_text = $enquiry->customer_email ? '<span class="small">' . esc_html( $enquiry->customer_email ) . '</span><br>' : '';
        $customer_phone_text = $enquiry->customer_phone ? '<span class="small">' . esc_html( $enquiry->customer_phone ) . '</span><br>' : '';
        $caravan_text        = $enquiry->caravan_name   ? '<br><strong>Caravan:</strong> ' . esc_html( $enquiry->caravan_name ) : '';

        // ── Build "From" column lines ─────────────────────────────────────────
        $from_website_text = $co_website ? '<span class="small">' . esc_html( $co_website_clean ) . '</span><br>' : '';
        $from_phone_text   = $co_phone   ? '<span class="small">' . esc_html( $co_phone ) . '</span><br>' : '';
        $from_email_text   = $co_email   ? '<span class="small">' . esc_html( $co_email ) . '</span><br>' : '';
        $from_tagline_text = $co_tagline ? '<br><span style="font-size:9px;text-transform:uppercase;letter-spacing:1px;color:#888;">' . esc_html( $co_tagline ) . '</span>' : '';

        // ── Footer contact ────────────────────────────────────────────────────
        $footer_contact = '';
        if ( $co_website ) $footer_contact .= esc_html( $co_website_clean ) . '&nbsp;&nbsp;';
        if ( $co_phone )   $footer_contact .= esc_html( $co_phone );

        // ── Note text ─────────────────────────────────────────────────────────
        $note_text = $is_receipt
            ? 'Please keep this receipt for your records. You will receive a payment receipt by email each time a payment is processed.'
            : 'You will receive a payment receipt by email each time a payment is processed. Please use the bank details above to make your payments.';

        // ── Bill To label ─────────────────────────────────────────────────────
        $bill_to_label = $is_receipt ? 'Receipt For' : 'Invoice To';

        // ── Load template ─────────────────────────────────────────────────────
        $html = self::get_template( 'invoice-pdf', [
            'LOGO_HTML'            => $logo_html,
            'TAGLINE_HTML'         => $tagline_html,
            'COMPANY_NAME'         => esc_html( $co_name ),
            'COMPANY_PHONE_TEXT'   => $company_phone_text,
            'COMPANY_EMAIL_TEXT'   => $company_email_text,
            'COMPANY_WEBSITE_TEXT' => $company_website_text,
            'TITLE_TEXT'           => $title_text,
        //    'INVOICE_NUMBER'       => esc_html( $invoice->invoice_number ),
             'INVOICE_NUMBER'       => $invoice->invoice_number . '-' . sprintf('%02d', $term->term_number),
            'INVOICE_DATE'         => esc_html( $inv_date ),
            'PAYMENT_TYPE'         => esc_html( $payment_lbl ),
            'BILL_TO_LABEL'        => $bill_to_label,
            'CUSTOMER_NAME'        => esc_html( $enquiry->customer_name ),
            'CUSTOMER_EMAIL_TEXT'  => $customer_email_text,
            'CUSTOMER_PHONE_TEXT'  => $customer_phone_text,
            'CARAVAN_TEXT'         => $caravan_text,
            'BANK_ROWS'           => $bank_rows,
            'FROM_WEBSITE_TEXT'    => $from_website_text,
            'FROM_PHONE_TEXT'      => $from_phone_text,
            'FROM_EMAIL_TEXT'      => $from_email_text,
            'FROM_TAGLINE_TEXT'    => $from_tagline_text,
            'TERMS_ROWS'          => $terms_rows,
            'SUB_TOTAL'           => '$' . number_format( $sub_total, 2 ),
            'GST_AMOUNT'          => '$' . number_format( $gst_amount, 2 ),
            'GRAND_TOTAL'         => '$' . number_format( $grand_total, 2 ),
            'NOTE_TEXT'            => $note_text,
            'INVOICE_NOTES'        => esc_html( $inv_notes ),
            'FOOTER_CONTACT'       => $footer_contact,
            'YEAR'                 => date( 'Y' ),
        ] );

        if ( empty( $html ) ) {
            error_log( 'PT_PDF: invoice-pdf template not found.' );
            return false;
        }

        // ── Generate PDF via mPDF ─────────────────────────────────────────────
        try {
            $mpdf = new \Mpdf\Mpdf( [
                'mode'          => 'utf-8',
                'format'        => 'A4',
                'orientation'   => 'P',
                'margin_top'    => 0,
                'margin_right'  => 0,
                'margin_bottom' => 0,
                'margin_left'   => 0,
                'tempDir'       => sys_get_temp_dir(),
            ] );

            $mpdf->SetTitle( $title_text . ' ' . $invoice->invoice_number );
            $mpdf->SetAuthor( $co_name );
            $mpdf->WriteHTML( $html );

            $upload_dir = wp_upload_dir();
            $dir        = $upload_dir['basedir'] . '/pt-invoices/';
            if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );

            $prefix   = $is_receipt ? 'receipt' : 'invoice';
            $filename = sanitize_file_name( $prefix . '-' . $invoice->invoice_number . '-' . $term->term_name ) . '-' . time() . '.pdf';
            $filepath = $dir . $filename;

            $mpdf->Output( $filepath, \Mpdf\Output\Destination::FILE );

            return [
                'url'  => $upload_dir['baseurl'] . '/pt-invoices/' . $filename,
                'path' => $filepath,
            ];

        } catch ( \Exception $e ) {
            error_log( 'PT_PDF mPDF Error: ' . $e->getMessage() );
            return false;
        }
    }

    // =========================================================================
    // INTERNAL — Load HTML template and replace {{VARIABLES}}
    // =========================================================================

    private static function get_template( $name, array $vars = [] ) {
        $path = PT_PATH . 'includes/templates/' . $name . '.html';
        if ( ! file_exists( $path ) ) {
            error_log( 'PT_PDF: template not found – ' . $path );
            return '';
        }
        $html = file_get_contents( $path );
        foreach ( $vars as $key => $val ) {
            $html = str_replace( '{{' . $key . '}}', $val, $html );
        }
        return $html;
    }
}