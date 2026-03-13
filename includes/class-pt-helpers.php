<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PT_Helpers {

    // ── Payment type label ────────────────────────────────────────────────────
    public static function payment_type_label( $type ) {
        return $type === 'monthly' ? 'Per Month' : 'Per Week';
    }

    // ── Invoice status badge HTML ─────────────────────────────────────────────
    public static function invoice_status_badge( $status ) {
        $map = [
            'active'    => [ 'label' => 'Active',    'bg' => '#cce5ff', 'color' => '#004085' ],
            'paid'      => [ 'label' => 'Paid',      'bg' => '#d4edda', 'color' => '#155724' ],
            'cancelled' => [ 'label' => 'Cancelled', 'bg' => '#f8d7da', 'color' => '#721c24' ],
        ];
        $s = $map[ $status ] ?? [ 'label' => ucfirst( $status ), 'bg' => '#e2e3e5', 'color' => '#383d41' ];
        return sprintf(
            '<span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;background:%s;color:%s;">%s</span>',
            esc_attr( $s['bg'] ), esc_attr( $s['color'] ), esc_html( $s['label'] )
        );
    }

    // ── Term status badge HTML ────────────────────────────────────────────────
    public static function term_status_badge( $status ) {
        if ( $status === 'paid' ) {
            return '<span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;background:#d4edda;color:#155724;">✅ Paid</span>';
        }
        return '<span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;background:#fff3cd;color:#856404;">Unpaid</span>';
    }

    // ── Format currency ───────────────────────────────────────────────────────
    public static function money( $amount ) {
        return '$' . number_format( floatval( $amount ), 2 );
    }

    // ── Admin page URL helper ─────────────────────────────────────────────────
    public static function page_url( $args = [] ) {
        return add_query_arg( array_merge( [ 'page' => 'pt-invoices' ], $args ), admin_url( 'admin.php' ) );
    }

    // ── Nonce field for forms ─────────────────────────────────────────────────
    public static function nonce_field( $action = 'pt_admin_nonce' ) {
        return wp_nonce_field( $action, '_pt_nonce', true, false );
    }

    // ── Verify nonce ──────────────────────────────────────────────────────────
    public static function verify_nonce( $action = 'pt_admin_nonce' ) {
        return isset( $_POST['_pt_nonce'] ) && wp_verify_nonce( $_POST['_pt_nonce'], $action );
    }
}