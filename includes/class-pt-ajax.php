<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PT_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_pt_save_terms',   [ $this, 'save_terms' ] );
        add_action( 'wp_ajax_pt_send_invoice', [ $this, 'send_invoice' ] );  // ← Flow 1
        add_action( 'wp_ajax_pt_mark_paid',    [ $this, 'mark_paid' ] );     // ← Flow 2
    }

    /* ════════════════════════════════════════════════════════
     * SAVE TERMS + APPROVE ENQUIRY
     * ════════════════════════════════════════════════════════ */
    public function save_terms() {
        check_ajax_referer( 'pt_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised' );

        $enquiry_id   = intval( $_POST['enquiry_id'] ?? 0 );
        $display_name = sanitize_text_field( $_POST['display_name'] ?? '' );
        $terms_raw    = $_POST['terms'] ?? [];

        if ( ! $enquiry_id ) wp_send_json_error( 'Invalid enquiry ID.' );

        $terms = [];
        foreach ( $terms_raw as $t ) {
            $name   = sanitize_text_field( $t['name'] ?? '' );
            $amount = floatval( $t['amount'] ?? 0 );
            if ( $name === '' || $amount <= 0 ) {
                wp_send_json_error( 'Each term must have a name and a positive amount.' );
            }
            $terms[] = [ 'name' => $name, 'amount' => $amount ];
        }
        if ( empty( $terms ) || count( $terms ) > 5 ) {
            wp_send_json_error( 'Please add between 1 and 5 payment terms.' );
        }

        // Fallback display name
        if ( $display_name === '' ) {
            global $wpdb;
            $enq = $wpdb->get_row( $wpdb->prepare(
                'SELECT * FROM ' . $wpdb->prefix . 'custom_build_enquiries WHERE id = %d', $enquiry_id
            ) );
            if ( $enq ) {
                $u = get_user_by( 'email', $enq->customer_email );
                $display_name = $u ? $u->display_name : ( $enq->customer_name ?? '' );
            }
        }

        PT_Database::save_terms( $enquiry_id, $display_name, $terms );

        // Approve the enquiry
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'custom_build_enquiries',
            [ 'status' => 'approved' ],
            [ 'id'     => $enquiry_id ],
            [ '%s' ], [ '%d' ]
        );

        wp_send_json_success( [ 'message' => 'Terms saved and enquiry approved.' ] );
    }

    /* ════════════════════════════════════════════════════════
     * FLOW 1 — SEND INVOICE (no payment yet, just send the bill)
     * ════════════════════════════════════════════════════════ */
    public function send_invoice() {
        check_ajax_referer( 'pt_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised' );

        $term_id = intval( $_POST['term_id'] ?? 0 );
        if ( ! $term_id ) wp_send_json_error( 'Invalid term ID.' );

        $term = PT_Database::get_term( $term_id );
        if ( ! $term ) wp_send_json_error( 'Term not found.' );

        if ( ! in_array( $term->status, [ 'pending', 'paid' ] ) ) {
            wp_send_json_error( 'Invoice can only be sent for active or paid terms.' );
        }

        // Generate PDF (invoice style — payment not yet received)
        $pdf_url = PT_PDF::generate( $term, 'invoice' );

        // Save PDF url if not already set
        if ( $pdf_url && ! $term->pdf_url ) {
            PT_Database::save_pdf_url( $term_id, $pdf_url );
            $term = PT_Database::get_term( $term_id ); // reload
        }

        // Send invoice email
        $sent = PT_Email::send_invoice( $term, $pdf_url );

        if ( ! $sent ) {
            wp_send_json_error( 'Failed to send invoice email. Check mail settings.' );
        }

        // Track invoice sent
        PT_Database::mark_invoice_sent( $term_id );

        wp_send_json_success( [
            'message' => 'Invoice emailed to customer.',
            'pdf_url' => $pdf_url,
        ] );
    }

    /* ════════════════════════════════════════════════════════
     * FLOW 2 — PAYMENT RECEIVED → generate receipt → send email
     * ════════════════════════════════════════════════════════ */
    public function mark_paid() {
        check_ajax_referer( 'pt_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorised' );

        $term_id = intval( $_POST['term_id'] ?? 0 );
        if ( ! $term_id ) wp_send_json_error( 'Invalid term ID.' );

        $term = PT_Database::get_term( $term_id );
        if ( ! $term )                    wp_send_json_error( 'Term not found.' );
        if ( $term->status !== 'pending' ) wp_send_json_error( 'This term is not currently active.' );

        // Generate receipt PDF
        $pdf_url = PT_PDF::generate( $term, 'receipt' );

        // Mark paid in DB + unlock next term
        PT_Database::mark_paid( $term_id, $pdf_url ?: '' );

        // Reload with updated paid_date
        $term = PT_Database::get_term( $term_id );

        // Send receipt email
        $sent = PT_Email::send_receipt( $term );
        if ( $sent ) PT_Database::mark_email_sent( $term_id );

        wp_send_json_success( [
            'message' => 'Payment recorded. Receipt emailed to customer.',
            'pdf_url' => $pdf_url,
            'term_id' => $term_id,
        ] );
    }
}