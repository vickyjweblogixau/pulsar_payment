<?php
/**
 * Plugin Name: Payment Terms
 * Description: Invoice and payment terms management for approved caravan enquiries.
 * Version: 1.0.0
 * Author: Your Company
 * Text Domain: payment-terms
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Constants ──────────────────────────────────────────────────────────────────
define( 'PT_VERSION',  '1.0.0' );
define( 'PT_PATH',     plugin_dir_path( __FILE__ ) );
define( 'PT_URL',      plugin_dir_url( __FILE__ ) );
define( 'PT_PLUGIN',   __FILE__ );

// ── Autoload includes ──────────────────────────────────────────────────────────
require_once PT_PATH . 'includes/class-pt-database.php';
require_once PT_PATH . 'includes/class-pt-invoice-manager.php';
require_once PT_PATH . 'includes/class-pt-email-handler.php';
require_once PT_PATH . 'includes/class-pt-helpers.php';
require_once PT_PATH . 'includes/class-pt-pdf.php';
// ── Activation ────────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'pt_activate' );
function pt_activate() {
    PT_Database::create_tables();
}

// ── Ensure tables exist on every load (safety net) ────────────────────────────
add_action( 'plugins_loaded', function() {
    PT_Database::ensure_tables();
} );

// ── Admin Menu ────────────────────────────────────────────────────────────────
add_action( 'admin_menu', 'pt_register_menus' );
function pt_register_menus() {
    add_menu_page(
        'Invoices',
        'Invoices',
        'manage_options',
        'pt-invoices',
        'pt_render_invoices_page',
        'dashicons-media-spreadsheet',
        31
    );
    add_submenu_page(
        'pt-invoices',
        'All Invoices',
        'All Invoices',
        'manage_options',
        'pt-invoices',
        'pt_render_invoices_page'
    );
    add_submenu_page(
        'pt-invoices',
        'Invoice Settings',
        'Settings',
        'manage_options',
        'pt-invoice-settings',
        'pt_render_settings_page'
    );
}

// ── Settings Page Callback ────────────────────────────────────────────────────
function pt_render_settings_page() {
    require_once PT_PATH . 'admin/pt-settings-page.php';
}

// ── Page Router ───────────────────────────────────────────────────────────────
function pt_render_invoices_page() {
    // Printable invoice — full page, no WP chrome
    if ( isset( $_GET['print'] ) && isset( $_GET['invoice_id'] ) ) {
        require_once PT_PATH . 'admin/pt-invoice-pdf.php';
        exit;
    }

    $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
    $view   = isset( $_GET['view'] )   ? intval( $_GET['view'] )         : 0;

    if ( $view && $action === 'create' ) {
        require_once PT_PATH . 'admin/pt-invoice-create.php';
    } elseif ( $view ) {
        require_once PT_PATH . 'admin/pt-invoice-single.php';
    } else {
        require_once PT_PATH . 'admin/pt-invoices-page.php';
    }
}

// ── AJAX: Mark term as paid ───────────────────────────────────────────────────
add_action( 'wp_ajax_pt_mark_term_paid', 'pt_ajax_mark_term_paid' );
function pt_ajax_mark_term_paid() {
    check_ajax_referer( 'pt_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    $term_id = intval( $_POST['term_id'] ?? 0 );
    if ( ! $term_id ) {
        wp_send_json_error( 'Invalid term ID' );
    }
    $result = PT_Invoice_Manager::mark_term_paid( $term_id );
    if ( $result['success'] ) {
        wp_send_json_success( $result );
    } else {
        wp_send_json_error( $result['message'] );
    }
}

// ── AJAX: Send single term invoice email ─────────────────────────────────────
add_action( 'wp_ajax_pt_send_term_invoice', 'pt_ajax_send_term_invoice' );
function pt_ajax_send_term_invoice() {
    check_ajax_referer( 'pt_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    $term_id = intval( $_POST['term_id'] ?? 0 );
    if ( ! $term_id ) {
        wp_send_json_error( 'Invalid term ID' );
    }
    $result = PT_Invoice_Manager::send_term_invoice( $term_id );
    if ( $result['success'] ) {
        wp_send_json_success( $result );
    } else {
        wp_send_json_error( $result['message'] );
    }
}

// ── AJAX: Generate invoice ────────────────────────────────────────────────────
add_action( 'wp_ajax_pt_generate_invoice', 'pt_ajax_generate_invoice' );
function pt_ajax_generate_invoice() {
    check_ajax_referer( 'pt_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $enquiry_id  = intval( $_POST['enquiry_id'] ?? 0 );
    $payment_type = sanitize_key( $_POST['payment_type'] ?? 'weekly' );
    $total_amount = floatval( $_POST['total_amount'] ?? 0 );
    $terms        = $_POST['terms'] ?? [];

    if ( ! $enquiry_id || empty( $terms ) ) {
        wp_send_json_error( 'Missing required data' );
    }

    $invoice_id = PT_Invoice_Manager::create_invoice( $enquiry_id, $payment_type, $total_amount, $terms );
    if ( $invoice_id ) {
        wp_send_json_success( [ 'invoice_id' => $invoice_id ] );
    } else {
        wp_send_json_error( 'Failed to create invoice' );
    }
}

// ── Admin Scripts ─────────────────────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', 'pt_admin_scripts' );
function pt_admin_scripts( $hook ) {
    if ( ! isset( $_GET['page'] ) ) return;
    $page = $_GET['page'];

    if ( $page === 'pt-invoices' ) {
        wp_enqueue_style( 'pt-admin-css', PT_URL . 'assets/css/pt-admin.css', [], PT_VERSION );
        wp_enqueue_script( 'pt-admin-js', PT_URL . 'assets/js/pt-admin.js', [ 'jquery' ], PT_VERSION, true );
        wp_localize_script( 'pt-admin-js', 'ptAdmin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'pt_admin_nonce' ),
        ] );
    }

    if ( $page === 'pt-invoice-settings' ) {
        wp_enqueue_media();
    }
}