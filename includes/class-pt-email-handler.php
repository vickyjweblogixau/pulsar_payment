<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PT_Email_Handler {

    // =========================================================================
    // SEND INVOICE EMAIL  (full invoice — all terms)
    // =========================================================================

    public static function send_invoice( $invoice_id ) {
        global $wpdb;

        $invoice = PT_Invoice_Manager::get_invoice( $invoice_id );
        if ( ! $invoice ) return false;

        $terms   = PT_Invoice_Manager::get_terms( $invoice_id );
        $enquiry = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.*, p.post_title AS caravan_name
             FROM {$wpdb->prefix}custom_build_enquiries e
             LEFT JOIN {$wpdb->posts} p ON p.ID = e.custom_build_id
             WHERE e.id = %d",
            $invoice->enquiry_id
        ) );
        if ( ! $enquiry ) return false;

        // ── Company settings ──────────────────────────────────────────────────
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
        $inv_notes   = get_option( 'pt_invoice_notes',       'Thank you for your business' );

        $payment_lbl = $invoice->payment_type === 'monthly' ? 'Monthly' : 'Weekly';
        $inv_date    = date( 'd/m/Y', strtotime( $invoice->created_at ) );
        $dashboard   = home_url( '/client-login/' );

        // ── GST Calculations ──────────────────────────────────────────────────
        /* $sub_total   = floatval( $invoice->total_amount );
        $gst_amount  = round( $sub_total * 0.10, 2 );
        $grand_total = $sub_total + $gst_amount; */
        $grand_total = floatval( $invoice->total_amount );
        $gst_amount  = round( $grand_total / 11, 2 );
        $sub_total   = $grand_total - $gst_amount;

        // ── Build terms rows HTML ─────────────────────────────────────────────
        $terms_rows = '';
        foreach ( $terms as $t ) {
            $due = $t->due_date ? date( 'd M Y', strtotime( $t->due_date ) ) : '—';
            $bg  = ( intval( $t->term_number ) % 2 === 0 ) ? '#f9fafb' : '#ffffff';
            /* $paid_badge = $t->status === 'paid'
                ? ' <span style="display:inline-block;background:#d4edda;color:#155724;border-radius:10px;padding:1px 8px;font-size:11px;font-weight:600;">Paid</span>'
                : ''; */
                $paid_badge = $t->status === 'paid'
                    ? '<img src="' . esc_url( PT_URL . 'assets/image/paid_latest.png' ) . '" 
                        alt="Paid" 
                        style="width:70px; margin-left:6px; vertical-align:middle;">'
                    : '';  
            $terms_rows .= '
            <tr style="background:' . $bg . ';">
                <td style="padding:11px 14px;font-size:13px;text-align:center;color:#888;border-bottom:1px solid #f0f0f0;">'
                    . intval( $t->term_number ) . '</td>
                <td style="padding:11px 14px;font-size:13px;border-bottom:1px solid #f0f0f0;">
                    <strong>' . esc_html( $t->term_name ) . '</strong>' . $paid_badge . '
                    <br><span style="font-size:11px;color:#888;">Due: ' . esc_html( $due ) . '</span></td>
                <td style="padding:11px 14px;font-size:13px;text-align:center;color:#555;border-bottom:1px solid #f0f0f0;">'
                    . esc_html( $payment_lbl ) . '</td>
                <td style="padding:11px 14px;font-size:13px;text-align:right;font-weight:700;border-bottom:1px solid #f0f0f0;">
                    $' . number_format( floatval( $t->amount ), 2 ) . '</td>
            </tr>';
        }

        // ── Build conditional HTML blocks ─────────────────────────────────────
        $logo_html    = self::build_logo_html( $co_logo_url, $co_name );
        $bank_rows    = self::build_bank_rows( $bk_acc_name, $bk_name, $bk_bsb, $bk_acc_no );
        $tagline_html = $co_tagline ? '<div style="font-size:9px;letter-spacing:2px;color:#aaa;text-transform:uppercase;margin-top:5px;">' . esc_html( $co_tagline ) . '</div>' : '';

        $customer_email_html = $enquiry->customer_email ? '<p style="margin:0 0 3px;font-size:12px;color:#555;">' . esc_html( $enquiry->customer_email ) . '</p>' : '';
        $customer_phone_html = $enquiry->customer_phone ? '<p style="margin:0 0 3px;font-size:12px;color:#555;">' . esc_html( $enquiry->customer_phone ) . '</p>' : '';
        $caravan_html        = $enquiry->caravan_name   ? '<p style="margin:8px 0 0;font-size:12px;"><strong>Caravan:</strong> ' . esc_html( $enquiry->caravan_name ) . '</p>' : '';

        $co_website_clean    = str_replace( ['https://','http://'], '', $co_website );
        $company_website_html = $co_website ? '<p style="margin:0 0 3px;font-size:12px;color:#555;">' . esc_html( $co_website_clean ) . '</p>' : '';
        $company_phone_html   = $co_phone   ? '<p style="margin:0 0 3px;font-size:12px;color:#555;">' . esc_html( $co_phone ) . '</p>' : '';
        $company_email_html   = $co_email   ? '<p style="margin:0 0 3px;font-size:12px;color:#555;">' . esc_html( $co_email ) . '</p>' : '';
        $company_tagline_html = $co_tagline ? '<p style="margin:8px 0 0;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#888;">' . esc_html( $co_tagline ) . '</p>' : '';

        $footer_contact = '';
        if ( $co_website ) $footer_contact .= esc_html( $co_website_clean ) . '&nbsp;&nbsp;';
        if ( $co_phone )   $footer_contact .= esc_html( $co_phone );

        // ── Load template ─────────────────────────────────────────────────────
        $html = self::get_template( 'invoice-full', [
            'LOGO_HTML'            => $logo_html,
            'TAGLINE_HTML'         => $tagline_html,
            'COMPANY_NAME'         => esc_html( $co_name ),
        //    'INVOICE_NUMBER'       => esc_html( $invoice->invoice_number ),
              'INVOICE_NUMBER'       =>  $invoice->invoice_number . '-' . sprintf('%02d', $term->term_number),
            'INVOICE_DATE'         => esc_html( $inv_date ),
            'PAYMENT_TYPE'         => esc_html( $payment_lbl ),
            'GRAND_TOTAL'          => '$' . number_format( $grand_total, 2 ),
            'SUB_TOTAL'            => '$' . number_format( $sub_total, 2 ),
            'GST_AMOUNT'           => '$' . number_format( $gst_amount, 2 ),
            'CUSTOMER_NAME'        => esc_html( $enquiry->customer_name ),
            'CUSTOMER_EMAIL_HTML'  => $customer_email_html,
            'CUSTOMER_PHONE_HTML'  => $customer_phone_html,
            'CARAVAN_HTML'         => $caravan_html,
            'BANK_ROWS'            => $bank_rows,
            'COMPANY_WEBSITE_HTML' => $company_website_html,
            'COMPANY_PHONE_HTML'   => $company_phone_html,
            'COMPANY_EMAIL_HTML'   => $company_email_html,
            'COMPANY_TAGLINE_HTML' => $company_tagline_html,
            'TERMS_ROWS'           => $terms_rows,
            'INVOICE_NOTES'        => esc_html( $inv_notes ),
            'FOOTER_CONTACT'       => $footer_contact,
            'DASHBOARD_URL'        => esc_url( $dashboard ),
            'YEAR'                 => date( 'Y' ),
        ] );

        if ( empty( $html ) ) {
            error_log( 'PT Email: invoice-full template missing.' );
            return false;
        }

        $subject = 'Your Invoice ' . $invoice->invoice_number . ' – ' . ( $enquiry->caravan_name ?? 'Caravan Build' );
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        return self::send( $enquiry->customer_email, $subject, $html, $headers );
    }

    // =========================================================================
    // SEND SINGLE TERM INVOICE  (one specific term only)
    // =========================================================================

    public static function send_single_term_invoice( $invoice, $term, $enquiry, $pdf_path = '' ) {
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
        $inv_notes   = get_option( 'pt_invoice_notes',       'Thank you for your business' );

        $payment_lbl = $invoice->payment_type === 'monthly' ? 'Monthly' : 'Weekly';
        $inv_date    = date( 'd/m/Y', strtotime( $invoice->created_at ) );
        $due_str     = $term->due_date ? date( 'd M Y', strtotime( $term->due_date ) ) : '—';
        $dashboard   = home_url( '/client-login/' );

        // ── GST Calculations ──────────────────────────────────────────────────
        /* $term_amount = floatval( $term->amount );
        $term_gst    = round( $term_amount * 0.10, 2 );
        $term_total  = $term_amount + $term_gst; */
        $term_total  = floatval( $term->amount );
        $term_gst    = round( $term_total / 11, 2 );
        $term_amount = $term_total - $term_gst;


        // ── Build conditional HTML blocks ─────────────────────────────────────
        $logo_html    = self::build_logo_html( $co_logo_url, $co_name );
        $bank_rows    = self::build_bank_rows( $bk_acc_name, $bk_name, $bk_bsb, $bk_acc_no );
        $tagline_html = $co_tagline ? '<div style="font-size:9px;letter-spacing:2px;color:#aaa;text-transform:uppercase;margin-top:5px;">' . esc_html( $co_tagline ) . '</div>' : '';

        $customer_email_html = $enquiry->customer_email ? '<p style="margin:0 0 3px;font-size:12px;color:#555;">' . esc_html( $enquiry->customer_email ) . '</p>' : '';
        $customer_phone_html = $enquiry->customer_phone ? '<p style="margin:0 0 3px;font-size:12px;color:#555;">' . esc_html( $enquiry->customer_phone ) . '</p>' : '';
        $caravan_html        = $enquiry->caravan_name   ? '<p style="margin:8px 0 0;font-size:12px;"><strong>Caravan:</strong> ' . esc_html( $enquiry->caravan_name ) . '</p>' : '';

        $co_website_clean = str_replace( ['https://','http://'], '', $co_website );
        $footer_contact   = '';
        if ( $co_website ) $footer_contact .= esc_html( $co_website_clean ) . '&nbsp;&nbsp;';
        if ( $co_phone )   $footer_contact .= esc_html( $co_phone );
        
        // ── Load template ─────────────────────────────────────────────────────
        $html = self::get_template( 'invoice-single-term', [
            'LOGO_HTML'           => $logo_html,
            'TAGLINE_HTML'        => $tagline_html,
            'COMPANY_NAME'        => esc_html( $co_name ),
        //    'INVOICE_NUMBER'      => esc_html( $invoice->invoice_number ),
            'INVOICE_NUMBER' => esc_html( $invoice->invoice_number . '-' . sprintf('%02d', $term->term_number) ),
            'INVOICE_DATE'        => esc_html( $inv_date ),
            'PAYMENT_TYPE'        => esc_html( $payment_lbl ),
            'TERM_NAME'           => esc_html( $term->term_name ),
            'TERM_NUMBER'         => intval( $term->term_number ),
            'TERM_AMOUNT'         => '$' . number_format( $term_amount, 2 ),
            'TERM_GST'            => '$' . number_format( $term_gst, 2 ),
            'TERM_TOTAL_INC_GST'  => '$' . number_format( $term_total, 2 ),
            'TERM_DUE_DATE'       => esc_html( $due_str ),
            'CUSTOMER_NAME'       => esc_html( $enquiry->customer_name ),
            'CUSTOMER_EMAIL_HTML' => $customer_email_html,
            'CUSTOMER_PHONE_HTML' => $customer_phone_html,
            'CARAVAN_HTML'        => $caravan_html,
            'BANK_ROWS'           => $bank_rows,
            'INVOICE_NOTES'       => esc_html( $inv_notes ),
            'FOOTER_CONTACT'      => $footer_contact,
            'DASHBOARD_URL'       => esc_url( $dashboard ),
            'YEAR'                => date( 'Y' ),
            'DASHBOARD_URL'       => esc_url( $dashboard ),
            'YEAR'                => date( 'Y' ),
        ] );

        if ( empty( $html ) ) {
            error_log( 'PT Email: invoice-single-term template missing.' );
            return false;
        }

        $subject     = 'Invoice ' . $invoice->invoice_number . ' – ' . $term->term_name . ' – ' . ( $enquiry->caravan_name ?? 'Caravan Build' );
        $headers     = [ 'Content-Type: text/html; charset=UTF-8' ];
        $attachments = ( $pdf_path && file_exists( $pdf_path ) ) ? [ $pdf_path ] : [];

        return self::send( $enquiry->customer_email, $subject, $html, $headers, $attachments );
    }

    // =========================================================================
    // SEND PAYMENT RECEIPT  (per term, after admin marks paid)
    // =========================================================================

    /**
     * @param array $data {
     *   customer_email, customer_name, invoice_number,
     *   term_name, amount_paid, date_paid,
     *   remaining_balance, caravan_name, total_amount
     * }
     */
    public static function send_receipt( array $data, $pdf_path = '' ) {
        $site_name  = get_option( 'pt_company_name', get_bloginfo( 'name' ) );
        $site_email = get_option( 'pt_company_email', get_option( 'admin_email' ) );
        $co_logo    = get_option( 'pt_company_logo_url', '' );
        $dashboard  = home_url( '/client-login/' );

        $logo_html = $co_logo
            ? '<img src="' . esc_url( $co_logo ) . '" alt="' . esc_attr( $site_name ) . '" style="max-height:50px;max-width:180px;display:block;" />'
            : '<span style="font-size:20px;font-weight:900;color:#fff;">' . esc_html( $site_name ) . '</span>';

        // ── GST Calculations ──────────────────────────────────────────────────
        /* $amount_paid_raw = floatval( str_replace( [',','$'], '', $data['amount_paid'] ) );
        $gst_on_paid     = round( $amount_paid_raw * 0.10, 2 );
        $paid_inc_gst    = $amount_paid_raw + $gst_on_paid;

        $total_raw       = floatval( str_replace( [',','$'], '', $data['total_amount'] ) );
        $total_inc_gst   = $total_raw + round( $total_raw * 0.10, 2 );

        $remaining_raw   = floatval( str_replace( [',','$'], '', $data['remaining_balance'] ) );
        $remaining_gst   = round( $remaining_raw * 0.10, 2 );
        $remaining_inc   = $remaining_raw + $remaining_gst; */
        $paid_inc_gst    = floatval( str_replace( [',','$'], '', $data['amount_paid'] ) );
        $gst_on_paid     = round( $paid_inc_gst / 11, 2 );
        $amount_paid_raw = $paid_inc_gst - $gst_on_paid;

        $total_raw       = floatval( str_replace( [',','$'], '', $data['total_amount'] ) );
        $total_inc_gst   = $total_raw;

        $remaining_raw   = floatval( str_replace( [',','$'], '', $data['remaining_balance'] ) );
        $remaining_inc   = $remaining_raw;


        $remaining_color = $remaining_inc <= 0 ? '#16a34a' : '#856404';
        $remaining_bg    = $remaining_inc <= 0 ? '#d4edda'  : '#fff3cd';

        $html = self::get_template( 'invoice-receipt', [
            'SITE_NAME'                 => esc_html( $site_name ),
            'SITE_NAME_UPPER'           => esc_html( strtoupper( $site_name ) ),
            'LOGO_HTML'                 => $logo_html,
            'CUSTOMER_NAME'             => esc_html( $data['customer_name'] ),
            'CARAVAN_NAME'              => esc_html( $data['caravan_name'] ),
            'INVOICE_NUMBER'            => esc_html( $data['invoice_number'] ),
            'TERM_NAME'                 => esc_html( $data['term_name'] ),
            'AMOUNT_PAID'               => '$' . $data['amount_paid'],
            'GST_AMOUNT'                => '$' . number_format( $gst_on_paid, 2 ),
            'AMOUNT_PAID_INC_GST'       => '$' . number_format( $paid_inc_gst, 2 ),
            'DATE_PAID'                 => esc_html( $data['date_paid'] ),
            'REMAINING_BALANCE_INC_GST' => '$' . number_format( $remaining_inc, 2 ),
            'REMAINING_BALANCE_BG'      => $remaining_bg,
            'REMAINING_BALANCE_COLOR'   => $remaining_color,
            'TOTAL_AMOUNT_INC_GST'      => '$' . number_format( $total_inc_gst, 2 ),
            'DASHBOARD_URL'             => esc_url( $dashboard ),
            'SITE_EMAIL'                => esc_html( $site_email ),
            'YEAR'                      => date( 'Y' ),
        ] );

        if ( empty( $html ) ) {
            error_log( 'PT Email: invoice-receipt template missing.' );
            return false;
        }

        $subject     = 'Payment Receipt – ' . $data['invoice_number'] . ' – ' . $data['term_name'];
        $headers     = [ 'Content-Type: text/html; charset=UTF-8' ];
        $attachments = ( $pdf_path && file_exists( $pdf_path ) ) ? [ $pdf_path ] : [];

        return self::send( $data['customer_email'], $subject, $html, $headers, $attachments );
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Build bank detail table rows.
     */
    private static function build_bank_rows( $acc_name, $bank_name, $bsb, $acc_no ) {
        $rows = '';
        foreach ( [
            'Account Name'   => $acc_name,
            'Bank Name'      => $bank_name,
            'BSB Number'     => $bsb,
            'Account Number' => $acc_no,
        ] as $lbl => $val ) {
            if ( empty( $val ) ) continue;
            $rows .= '
            <tr>
                <td style="padding:6px 0;font-size:12px;color:#666;width:130px;">' . esc_html( $lbl ) . '</td>
                <td style="padding:6px 0;font-size:12px;font-weight:700;color:#000;">' . esc_html( $val ) . '</td>
            </tr>';
        }
        return $rows;
    }

    /**
     * Build logo HTML or fallback company name text.
     */
    private static function build_logo_html( $logo_url, $co_name ) {
        return $logo_url
            ? '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $co_name ) . '" style="max-height:55px;max-width:200px;display:block;" />'
            : '<span style="font-size:22px;font-weight:900;color:#fff;letter-spacing:1px;">' . esc_html( $co_name ) . '</span>';
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    private static function get_template( $name, array $vars = [] ) {
        $path = PT_PATH . 'includes/templates/' . $name . '.html';
        if ( ! file_exists( $path ) ) {
            error_log( 'PT Email: template not found – ' . $path );
            return '';
        }
        $html = file_get_contents( $path );
        foreach ( $vars as $key => $val ) {
            $html = str_replace( '{{' . $key . '}}', $val, $html );
        }
        return $html;
    }

    private static function send( $to, $subject, $html, $headers, $attachments = [] ) {
        $site_name  = get_option( 'pt_company_name', get_bloginfo( 'name' ) );
        $site_email = get_option( 'pt_company_email', get_option( 'admin_email' ) );

        $set_email = fn() => $site_email;
        $set_name  = fn() => $site_name;

        add_filter( 'wp_mail_from',      $set_email );
        add_filter( 'wp_mail_from_name', $set_name );

        $result = wp_mail( $to, $subject, $html, $headers, $attachments );

        remove_filter( 'wp_mail_from',      $set_email );
        remove_filter( 'wp_mail_from_name', $set_name );

        return $result;
    }
}