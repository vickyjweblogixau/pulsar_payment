<?php
/**
 * Printable / PDF Invoice View
 * URL: admin.php?page=pt-invoices&print=1&invoice_id=X
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

global $wpdb;

$invoice_id = intval( $_GET['invoice_id'] ?? 0 );
$invoice    = PT_Invoice_Manager::get_invoice( $invoice_id );
if ( ! $invoice ) wp_die( 'Invoice not found.' );

$terms   = PT_Invoice_Manager::get_terms( $invoice_id );
$enquiry = $wpdb->get_row( $wpdb->prepare(
    "SELECT e.*, p.post_title AS caravan_name
     FROM {$wpdb->prefix}custom_build_enquiries e
     LEFT JOIN {$wpdb->posts} p ON p.ID = e.custom_build_id
     WHERE e.id = %d",
    $invoice->enquiry_id
) );
if ( ! $enquiry ) wp_die( 'Enquiry not found.' );

// ── Company settings ──────────────────────────────────────────────────────────
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

$payment_lbl      = $invoice->payment_type === 'monthly' ? 'Monthly' : 'Weekly';
$inv_date         = date( 'd/m/Y', strtotime( $invoice->created_at ) );
$co_website_clean = str_replace( ['https://','http://'], '', $co_website );

// ── GST Calculations (GST-inclusive — amounts entered INCLUDE GST) ────────────
$grand_total = floatval( $invoice->total_amount );
$gst_amount  = round( $grand_total / 11, 2 );
$sub_total   = $grand_total - $gst_amount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo esc_html( $invoice->invoice_number ); ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size:13px; color:#000; background:#e9e9e9; }
        .page { width:794px; min-height:1123px; margin:0 auto; background:#fff; box-shadow:0 2px 20px rgba(0,0,0,.15); position:relative; }

        /* ── Header band (logo left, contact right) ── */
        .header-band { background:#1a1a2e; color:#fff; padding:22px 32px 18px; }
        .header-band table { width:100%; border-collapse:collapse; }
        .header-band .logo-cell { vertical-align:middle; }
        .header-band .logo-cell img { max-height:60px; max-width:200px; display:block; }
        .header-band .logo-cell .co-name { font-size:24px; font-weight:900; letter-spacing:2px; color:#fff; }
        .header-band .logo-cell .co-tagline { font-size:9px; letter-spacing:2px; color:#aaa; margin-top:4px; text-transform:uppercase; }
        .header-band .contact-cell { text-align:right; vertical-align:middle; font-size:11px; line-height:20px; color:#aaa; }

        /* ── Invoice title bar ── */
        .title-bar { background:#f0f4fb; border-bottom:3px solid #1a1a2e; padding:12px 32px; }
        .title-bar table { width:100%; border-collapse:collapse; }
        .title-bar .title-text { font-size:26px; font-weight:900; color:#1a1a2e; letter-spacing:3px; vertical-align:middle; }
        .title-bar .meta-cell { text-align:right; vertical-align:middle; font-size:12px; line-height:22px; }
        .title-bar .meta-cell strong { color:#1a1a2e; }

        /* ── Two-column info ── */
        .info-section { padding:20px 32px 18px; }
        .info-section table { width:100%; border-collapse:collapse; }
        .info-section td { vertical-align:top; width:50%; }
        .info-section td:first-child { padding-right:20px; }
        .info-section td:last-child { padding-left:20px; }
        .info-title { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:#1a1a2e; border-bottom:2px solid #1a1a2e; padding-bottom:5px; margin-bottom:10px; }
        .info-body { font-size:12px; line-height:22px; color:#333; }
        .info-body strong { color:#000; }
        .info-body .small { font-size:11px; color:#555; }

        /* ── Items table ── */
        .items-wrap { padding:0 32px 24px; }
        .items-table { width:100%; border-collapse:collapse; }
        .items-table thead tr { background:#1a1a2e; }
        .items-table thead th { padding:10px 14px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#fff; font-weight:700; }
        .items-table thead th.right { text-align:right; }
        .items-table thead th.center { text-align:center; }
        .items-table tbody tr { border-bottom:1px solid #f0f0f0; }
        .items-table tbody tr:nth-child(even) { background:#f9fafb; }
        .items-table tbody td { padding:11px 14px; font-size:13px; color:#000; vertical-align:middle; }
        .items-table tbody td.right { text-align:right; font-weight:700; }
        .items-table tbody td.center { text-align:center; color:#555; }
        .items-table tbody td .due-date { font-size:11px; color:#888; display:block; margin-top:2px; }
        .items-table tbody td .paid-badge { display:inline-block; background:#d4edda; color:#155724; border-radius:10px; padding:1px 8px; font-size:11px; font-weight:600; margin-left:6px; }
        .items-table tbody td .extax { font-size:11px; color:#888; font-weight:400; }

        /* Totals */
        .items-table tfoot td { padding:10px 14px; font-size:13px; }
        .items-table tfoot .sub-row td { border-top:2px solid #e5e7eb; color:#555; }
        .items-table tfoot .gst-row td { color:#555; border-bottom:1px solid #f0f0f0; }
        .items-table tfoot .total-row td { background:#1a1a2e; color:#fff; font-size:15px; font-weight:700; padding:12px 14px; }

        /* ── Footer ── */
        .footer-band { background:#f0f4fb; border-top:3px solid #1a1a2e; padding:14px 32px; text-align:center; margin-top:10px; }
        .footer-band .thank-you { font-size:14px; font-weight:700; color:#1a1a2e; margin-bottom:4px; }
        .footer-band .contact-line { font-size:11px; color:#555; }

        /* ── Print controls ── */
        .print-bar { width:794px; margin:0 auto 12px; display:flex; gap:10px; padding:14px 0; }
        .print-bar button { padding:10px 22px; border-radius:6px; border:none; cursor:pointer; font-size:13px; font-weight:600; }
        .btn-print { background:#1a1a2e; color:#fff; }
        .btn-print:hover { background:#2a2a4e; }
        .btn-back { background:#f0f0f0; color:#333; }
        .btn-back:hover { background:#ddd; }
        @media print { .print-bar { display:none !important; } body { background:#fff; } .page { width:100%; box-shadow:none; margin:0; } }
    </style>
</head>
<body>

<div class="print-bar">
    <button class="btn-print" onclick="window.print();">🖨 Print / Save as PDF</button>
    <button class="btn-back" onclick="window.close();">✕ Close</button>
</div>

<div class="page">

    <!-- ═══ HEADER BAND (Logo + Tagline left | Contact right) ═══ -->
    <div class="header-band">
        <table cellpadding="0" cellspacing="0">
            <tr>
                <td class="logo-cell">
                    <?php if ( $co_logo_url ) : ?>
                        <img src="<?php echo esc_url( $co_logo_url ); ?>" alt="<?php echo esc_attr( $co_name ); ?>" />
                    <?php else : ?>
                        <div class="co-name"><?php echo esc_html( $co_name ); ?></div>
                    <?php endif; ?>
                    <?php if ( $co_tagline ) : ?>
                        <div class="co-tagline"><?php echo esc_html( $co_tagline ); ?></div>
                    <?php endif; ?>
                </td>
                <td class="contact-cell">
                    <?php if ( $co_phone ) : ?><?php echo esc_html( $co_phone ); ?><br><?php endif; ?>
                    <?php if ( $co_email ) : ?><?php echo esc_html( $co_email ); ?><br><?php endif; ?>
                    <?php if ( $co_website ) : ?><?php echo esc_html( $co_website_clean ); ?><?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- ═══ INVOICE TITLE BAR ═══ -->
    <div class="title-bar">
        <table cellpadding="0" cellspacing="0">
            <tr>
                <td class="title-text">Consolidated Invoice</td>
                <td class="meta-cell">
                    <strong>Date:</strong> <?php echo esc_html( $inv_date ); ?><br>
                </td>
            </tr>
        </table>
    </div>

    <!-- ═══ INVOICE TO + PAYMENT INFO (Two columns) ═══ -->
    <div class="info-section">
        <table cellpadding="0" cellspacing="0">
            <tr>
                <!-- Invoice To -->
                <td>
                    <div class="info-title">Invoice To</div>
                    <div class="info-body">
                        <strong><?php echo esc_html( $enquiry->customer_name ); ?></strong><br>
                        <?php if ( $enquiry->customer_email ) : ?>
                            <span class="small"><?php echo esc_html( $enquiry->customer_email ); ?></span><br>
                        <?php endif; ?>
                        <?php if ( $enquiry->customer_phone ) : ?>
                            <span class="small"><?php echo esc_html( $enquiry->customer_phone ); ?></span><br>
                        <?php endif; ?>
                        <?php if ( $enquiry->caravan_name ) : ?>
                            <br><strong>Caravan:</strong> <?php echo esc_html( $enquiry->caravan_name ); ?>
                        <?php endif; ?>
                    </div>
                </td>
                <!-- Payment Info -->
                <td>
                    <div class="info-title">Payment Info</div>
                    <div class="info-body">
                        <?php if ( $bk_acc_name ) : ?><strong>Account Name:</strong> <?php echo esc_html( $bk_acc_name ); ?><br><?php endif; ?>
                        <?php if ( $bk_name )     : ?><strong>Bank Name:</strong> <?php echo esc_html( $bk_name ); ?><br><?php endif; ?>
                        <?php if ( $bk_bsb )      : ?><strong>BSB Number:</strong> <?php echo esc_html( $bk_bsb ); ?><br><?php endif; ?>
                        <?php if ( $bk_acc_no )   : ?><strong>ACC Number:</strong> <?php echo esc_html( $bk_acc_no ); ?><br><?php endif; ?>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- ═══ ITEMS TABLE (SL | Description | Payment Term | Fees) ═══ -->
    <div class="items-wrap">
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:5%;" class="center">SN.</th>
                    <th style="width:20%;" class="center">Invoice</th>
                    <th>Project Description</th>
                    
                    <th style="width:130px;" class="right">Project Fees</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $terms as $t ) :
                    $due = $t->due_date ? date( 'd M Y', strtotime( $t->due_date ) ) : '';
                    $paid_badge = $t->status === 'paid' ? '<span class="paid-badge">Paid</span>' : '';

                    // Per-term GST-inclusive breakdown
                    $term_inc   = floatval( $t->amount );
                    $term_gst   = round( $term_inc / 11, 2 );
                    $term_extax = $term_inc - $term_gst;
                ?>
                <tr>
                    <td class="center"><?php echo $t->term_number; ?></td>
                    <td class="center" style="color:#888;">
                         <?php echo esc_html( $invoice->invoice_number . '-' . sprintf('%02d', $t->term_number) ); ?>
                    </td>
                    <td>
                        <strong><?php echo esc_html( $t->term_name ); ?></strong><?php echo $paid_badge; ?>
                        <?php if ( $due ) : ?><span class="due-date">Due: <?php echo esc_html( $due ); ?></span><?php endif; ?>
                    </td>
                    
                    <td class="right">
                        $<?php echo number_format( $term_extax, 2 ); ?>
                        <span class="extax">(ex. tax)</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="sub-row">
                    <td colspan="3" style="text-align:right;">Sub Total (ex. tax):</td>
                    <td style="text-align:right;font-weight:700;">$<?php echo number_format( $sub_total, 2 ); ?></td>
                </tr>
                <tr class="gst-row">
                    <td colspan="3" style="text-align:right;">GST:</td>
                    <td style="text-align:right;font-weight:700;">$<?php echo number_format( $gst_amount, 2 ); ?></td>
                </tr>
                <tr class="total-row">
                    <td colspan="3" style="text-align:right;padding-right:14px;">Total:</td>
                    <td style="text-align:right;font-size:16px;font-weight:900;">$<?php echo number_format( $grand_total, 2 ); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- ═══ FOOTER ═══ -->
    <div class="footer-band">
        <div class="thank-you"><?php echo esc_html( $inv_notes ); ?></div>
        <div class="contact-line">
            <?php if ( $co_website ) echo esc_html( $co_website_clean ); ?>
            <?php if ( $co_phone ) echo '&nbsp;&nbsp;|&nbsp;&nbsp;' . esc_html( $co_phone ); ?>
        </div>
    </div>

</div>
</body>
</html>
<?php exit; ?>