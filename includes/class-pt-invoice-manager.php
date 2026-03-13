<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PT_Invoice_Manager {

    // ── Generate next invoice number  INV-00001 ───────────────────────────────
    private static function next_invoice_number() {
        global $wpdb;
        $last = $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->prefix}pt_invoices" );
        return 'INV-' . str_pad( intval( $last ) + 1, 5, '0', STR_PAD_LEFT );
    }

    // =========================================================================
    // CREATE INVOICE
    // Term 1 → 'unpaid'  (active immediately)
    // Term 2+ → 'locked' (unlocked one-by-one as previous term is paid)
    // =========================================================================
    public static function create_invoice( $enquiry_id, $payment_type, $total_amount, $terms ) {
        global $wpdb;

        // Resolve user_id
        $enquiry = $wpdb->get_row( $wpdb->prepare(
            "SELECT user_id, customer_email FROM {$wpdb->prefix}custom_build_enquiries WHERE id = %d",
            $enquiry_id
        ) );
        if ( ! $enquiry ) return false;

        $user_id = intval( $enquiry->user_id );
        if ( ! $user_id && $enquiry->customer_email ) {
            $u = get_user_by( 'email', $enquiry->customer_email );
            $user_id = $u ? $u->ID : 0;
        }

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'pt_invoices',
            [
                'enquiry_id'     => $enquiry_id,
                'user_id'        => $user_id,
                'invoice_number' => self::next_invoice_number(),
                'payment_type'   => sanitize_key( $payment_type ),
                'total_amount'   => floatval( $total_amount ),
                'terms_count'    => count( $terms ),
                'status'         => 'active',
                'created_at'     => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%f', '%d', '%s', '%s' ]
        );
        if ( ! $inserted ) return false;

        $invoice_id = $wpdb->insert_id;

        // Insert terms — ONLY term 1 is 'unpaid', rest are 'locked'
        foreach ( $terms as $i => $t ) {
            $is_first = ( $i === 0 );
            $wpdb->insert(
                $wpdb->prefix . 'pt_invoice_terms',
                [
                    'invoice_id'  => $invoice_id,
                    'term_number' => $i + 1,
                    'term_name'   => sanitize_text_field( $t['name'] ?? ( 'Term ' . ( $i + 1 ) ) ),
                    'amount'      => floatval( $t['amount'] ?? 0 ),
                    'due_date'    => ! empty( $t['due_date'] ) ? sanitize_text_field( $t['due_date'] ) : null,
                    'status'      => $is_first ? 'unpaid' : 'locked',  // ← KEY CHANGE
                    'created_at'  => current_time( 'mysql' ),
                ],
                [ '%d', '%d', '%s', '%f', '%s', '%s', '%s' ]
            );
        }

        return $invoice_id;
    }

    public static function send_term_invoice( $term_id ) {
        global $wpdb;

        $term = self::get_term( $term_id );
        if ( ! $term ) {
            return [ 'success' => false, 'message' => 'Term not found.' ];
        }

        if ( ! in_array( $term->status, [ 'unpaid', 'invoice_sent' ], true ) ) {
            return [ 'success' => false, 'message' => 'This term is not ready to send.' ];
        }

        $invoice = self::get_invoice( $term->invoice_id );
        if ( ! $invoice ) {
            return [ 'success' => false, 'message' => 'Invoice not found.' ];
        }

        $enquiry = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.*, p.post_title AS caravan_name
             FROM {$wpdb->prefix}custom_build_enquiries e
             LEFT JOIN {$wpdb->posts} p ON p.ID = e.custom_build_id
             WHERE e.id = %d",
            $invoice->enquiry_id
        ) );
        if ( ! $enquiry ) {
            return [ 'success' => false, 'message' => 'Enquiry not found.' ];
        }

        // ── Generate invoice PDF ──────────────────────────────────────────────
        $pdf      = PT_PDF::generate( 'invoice', $invoice, $term, $enquiry );
        $pdf_path = $pdf ? $pdf['path'] : '';
        $pdf_url  = $pdf ? $pdf['url']  : '';

        // ── Send email (with PDF attachment) ──────────────────────────────────
        $sent = PT_Email_Handler::send_single_term_invoice( $invoice, $term, $enquiry, $pdf_path );
        if ( ! $sent ) {
            return [ 'success' => false, 'message' => 'Email failed to send. Check mail settings.' ];
        }

        // ── Update term status → invoice_sent ─────────────────────────────────
        $wpdb->update(
            $wpdb->prefix . 'pt_invoice_terms',
            [
                'status'          => 'invoice_sent',
                'invoice_sent_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $term_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        return [
            'success'  => true,
            'message'  => 'Invoice sent to ' . $enquiry->customer_email,
            'pdf_url'  => $pdf_url,
            'sent_at'  => date( 'd M Y, g:i A', current_time( 'timestamp' ) ),
        ];
    }

    public static function mark_term_paid( $term_id ) {
        global $wpdb;

        $term = self::get_term( $term_id );
        if ( ! $term ) {
            return [ 'success' => false, 'message' => 'Term not found.' ];
        }
        if ( $term->status === 'paid' ) {
            return [ 'success' => false, 'message' => 'Term already paid.' ];
        }
        if ( $term->status === 'locked' ) {
            return [ 'success' => false, 'message' => 'Term is locked. Pay previous term first.' ];
        }

        $paid_at = current_time( 'mysql' );

        // ── Mark current term paid ────────────────────────────────────────────
        $wpdb->update(
            $wpdb->prefix . 'pt_invoice_terms',
            [ 'status' => 'paid', 'paid_at' => $paid_at ],
            [ 'id'     => $term_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        $invoice   = self::get_invoice( $term->invoice_id );
        $all_terms = self::get_terms( $term->invoice_id );

        // ── Unlock NEXT term ──────────────────────────────────────────────────
        foreach ( $all_terms as $t ) {
            if ( intval( $t->term_number ) === intval( $term->term_number ) + 1
                 && $t->status === 'locked' ) {
                $wpdb->update(
                    $wpdb->prefix . 'pt_invoice_terms',
                    [ 'status' => 'unpaid' ],
                    [ 'id'     => $t->id ],
                    [ '%s' ],
                    [ '%d' ]
                );
                break;
            }
        }

        // ── Check if ALL terms now paid ───────────────────────────────────────
        $all_paid   = true;
        $paid_total = 0;
        foreach ( $all_terms as $t ) {
            if ( $t->id == $term_id || $t->status === 'paid' ) {
                $paid_total += floatval( $t->amount );
            } else {
                $all_paid = false;
            }
        }
        $remaining = max( 0, floatval( $invoice->total_amount ) - $paid_total );

        if ( $all_paid ) {
            $wpdb->update(
                $wpdb->prefix . 'pt_invoices',
                [ 'status' => 'paid' ],
                [ 'id'     => $invoice->id ],
                [ '%s' ],
                [ '%d' ]
            );
        }

        // ── Get enquiry ───────────────────────────────────────────────────────
        $enquiry = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.*, p.post_title AS caravan_name
             FROM {$wpdb->prefix}custom_build_enquiries e
             LEFT JOIN {$wpdb->posts} p ON p.ID = e.custom_build_id
             WHERE e.id = %d",
            $invoice->enquiry_id
        ) );

        // ── Reload term with paid_at set ──────────────────────────────────────
        $term = self::get_term( $term_id );

        // ── Generate receipt PDF ──────────────────────────────────────────────
        $pdf      = PT_PDF::generate( 'receipt', $invoice, $term, $enquiry );
        $pdf_path = $pdf ? $pdf['path'] : '';
        $pdf_url  = $pdf ? $pdf['url']  : '';

        // ── Send receipt email (with PDF attachment) ──────────────────────────
        if ( $enquiry ) {
            PT_Email_Handler::send_receipt(
                [
                    'customer_email'    => $enquiry->customer_email,
                    'customer_name'     => $enquiry->customer_name,
                    'invoice_number'    => $invoice->invoice_number,
                    'term_name'         => $term->term_name,
                    'amount_paid'       => number_format( floatval( $term->amount ), 2 ),
                    'date_paid'         => date( 'd M Y, g:i A', strtotime( $paid_at ) ),
                    'remaining_balance' => number_format( $remaining, 2 ),
                    'caravan_name'      => $enquiry->caravan_name ?? 'Your Caravan',
                    'total_amount'      => number_format( floatval( $invoice->total_amount ), 2 ),
                ],
                $pdf_path
            );
        }

        return [
            'success'      => true,
            'paid_at'      => date( 'd M Y, g:i A', strtotime( $paid_at ) ),
            'remaining'    => number_format( $remaining, 2 ),
            'invoice_paid' => $all_paid,
            'pdf_url'      => $pdf_url,
        ];
    }

    // =========================================================================
    // GETTERS
    // =========================================================================

    public static function get_invoice( $invoice_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pt_invoices WHERE id = %d", $invoice_id
        ) );
    }

    public static function get_by_enquiry( $enquiry_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pt_invoices WHERE enquiry_id = %d ORDER BY id DESC LIMIT 1",
            $enquiry_id
        ) );
    }

    public static function get_terms( $invoice_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pt_invoice_terms WHERE invoice_id = %d ORDER BY term_number ASC",
            $invoice_id
        ) );
    }

    public static function get_term( $term_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pt_invoice_terms WHERE id = %d", $term_id
        ) );
    }

    public static function get_all_with_enquiries() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT
                e.id            AS enquiry_id,
                e.customer_name,
                e.customer_email,
                e.customer_phone,
                e.final_price,
                e.total_price,
                e.created_at    AS enquiry_date,
                e.status        AS enquiry_status,
                p.post_title    AS caravan_name,
                i.id            AS invoice_id,
                i.invoice_number,
                i.payment_type,
                i.terms_count,
                i.total_amount,
                i.status        AS invoice_status,
                i.created_at    AS invoice_date
            FROM {$wpdb->prefix}custom_build_enquiries e
            LEFT JOIN {$wpdb->posts} p ON p.ID = e.custom_build_id
            LEFT JOIN {$wpdb->prefix}pt_invoices i ON i.enquiry_id = e.id
            WHERE e.status = 'approved'
            ORDER BY e.created_at DESC"
        );
    }
}