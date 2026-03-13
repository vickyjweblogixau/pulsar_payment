<?php
/**
 * Invoice PDF Template — mPDF compatible
 * Variables available (passed from WLWP_Invoice_PDF_Generator::renderTemplate):
 *   $invoice        — invoice row object from DB
 *   $items          — array of line-item arrays [description, payment_term, fee]
 *   $site_name      — get_bloginfo('name')
 *   $site_phone     — get_option('wl_invoice_phone')
 *   $site_email     — get_option('wl_invoice_email')
 *   $site_url       — get_option('wl_invoice_website')
 *   $site_logo_url  — get_option('wl_invoice_logo_url')
 *   $bank_name      — get_option('wl_invoice_bank_name')
 *   $bsb            — get_option('wl_invoice_bsb')
 *   $account_name   — get_option('wl_invoice_account_name')
 *   $account_number — get_option('wl_invoice_account_number')
 *   $payment_terms  — get_option('wl_invoice_payment_terms')  ← dynamic
 *   $invoice_number — formatted invoice #
 *   $invoice_date   — formatted date
 *   $bill_to_name   — client company / name
 *   $bill_to_attn   — attention person
 *
 * Payment Term labels (set in Invoice Settings):
 *   weekly | fortnightly | monthly | upon_completion | net_7 | net_14 | net_30 | custom
 */

// Compute totals
/* 
$sub_total = 0;
foreach ($items as $item) {
    $sub_total += floatval($item['fee']);
}
$total = $sub_total; // add GST logic here if needed */
$total = 0;
foreach ($items as $item) {
    $total += floatval($item['fee']);
}
$gst_amount = round( $total / 11, 2 );
$sub_total  = $total - $gst_amount;

// Payment terms label map — keys come from Invoice Settings select field
$term_labels = [
    'weekly'           => 'Weekly',
    'fortnightly'      => 'Fortnightly',
    'monthly'          => 'Monthly',
    'upon_completion'  => 'Upon Completion',
    'net_7'            => 'Net 7 Days',
    'net_14'           => 'Net 14 Days',
    'net_30'           => 'Net 30 Days',
    'custom'           => esc_html(get_option('wl_invoice_payment_terms_custom', 'As Agreed')),
];
$term_label = $term_labels[$payment_terms] ?? ucwords(str_replace('_', ' ', $payment_terms));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    /* ── Reset ───────────────────────────────────── */
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: Arial, sans-serif; font-size:12px; color:#222; }

    /* ── Header band ─────────────────────────────── */
    .header-band {
        background:#1a3a6b;
        color:#fff;
        padding:22px 30px 18px;
    }
    .header-band table { width:100%; border-collapse:collapse; }
    .header-band .logo-cell { width:55%; vertical-align:middle; }
    .header-band .logo-cell img { max-height:60px; }
    .header-band .logo-cell .site-name {
        font-size:22px; font-weight:bold; letter-spacing:1px;
    }
    .header-band .logo-cell .tagline {
        font-size:9px; letter-spacing:2px; color:#a8c4e8; margin-top:3px;
        text-transform:uppercase;
    }
    .header-band .contact-cell {
        text-align:right; vertical-align:middle; font-size:11px; line-height:18px;
    }
    .header-band .contact-cell a { color:#a8c4e8; text-decoration:none; }

    /* ── Invoice title bar ───────────────────────── */
    .invoice-title-bar {
        background:#f0f4fb;
        border-bottom:3px solid #1a3a6b;
        padding:12px 30px;
    }
    .invoice-title-bar table { width:100%; border-collapse:collapse; }
    .invoice-title-bar .title-cell {
        font-size:26px; font-weight:bold; color:#1a3a6b; vertical-align:middle;
    }
    .invoice-title-bar .meta-cell {
        text-align:right; vertical-align:middle; font-size:11px; line-height:20px;
    }
    .invoice-title-bar .meta-cell strong { color:#1a3a6b; }

    /* ── Body padding ────────────────────────────── */
    .body-wrap { padding:24px 30px; }

    /* ── Bill To / Payment Info columns ─────────── */
    .info-table { width:100%; border-collapse:collapse; margin-bottom:22px; }
    .info-table td { vertical-align:top; width:50%; padding-right:16px; }
    .info-table td:last-child { padding-right:0; }
    .info-box-title {
        font-size:10px; font-weight:bold; text-transform:uppercase;
        letter-spacing:1px; color:#1a3a6b;
        border-bottom:2px solid #1a3a6b; padding-bottom:4px; margin-bottom:8px;
    }
    .info-box-body { font-size:12px; line-height:20px; }
    .info-box-body strong { color:#1a3a6b; }

    /* ── Payment Terms badge ─────────────────────── */
    .terms-badge {
        display:inline-block;
        background:#1a3a6b; color:#fff;
        font-size:11px; font-weight:bold;
        padding:3px 12px; border-radius:12px;
        margin-top:4px;
    }

    /* ── Line items table ────────────────────────── */
    .items-table {
        width:100%; border-collapse:collapse; margin-bottom:20px;
    }
    .items-table thead tr th {
        background:#1a3a6b; color:#fff;
        font-size:11px; text-transform:uppercase; letter-spacing:0.5px;
        padding:9px 12px; text-align:left;
    }
    .items-table thead tr th.right { text-align:right; }
    .items-table tbody tr td {
        padding:9px 12px; border-bottom:1px solid #e4eaf4; font-size:12px;
        vertical-align:top;
    }
    .items-table tbody tr:nth-child(even) td { background:#f8f9fd; }
    .items-table tbody tr td.right { text-align:right; white-space:nowrap; }
    .items-table tfoot tr td {
        padding:8px 12px; font-size:12px;
    }
    .items-table tfoot .subtotal-row td { border-top:2px solid #dde3f0; }
    .items-table tfoot .total-row td {
        background:#1a3a6b; color:#fff;
        font-size:14px; font-weight:bold;
    }
    .items-table tfoot td.right { text-align:right; }

    /* ── Thank you footer ────────────────────────── */
    .footer-band {
        background:#f0f4fb;
        border-top:3px solid #1a3a6b;
        padding:14px 30px;
        text-align:center;
        font-size:11px; color:#555;
        margin-top:10px;
    }
    .footer-band .thank-you {
        font-size:14px; font-weight:bold; color:#1a3a6b; margin-bottom:4px;
    }
</style>
</head>
<body>

<!-- ════════════════════════════════ HEADER ═══ -->
<div class="header-band">
    <table>
        <tr>
            <td class="logo-cell">
                <?php if (!empty($site_logo_url)): ?>
                    <img src="<?php echo esc_url($site_logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>">
                <?php else: ?>
                    <div class="site-name"><?php echo esc_html($site_name); ?></div>
                <?php endif; ?>
                <div class="tagline">Web &amp; Digital Marketing Experts</div>
            </td>
            <td class="contact-cell">
                <?php if (!empty($site_phone)): ?>
                    <?php echo esc_html($site_phone); ?><br>
                <?php endif; ?>
                <?php if (!empty($site_email)): ?>
                    <a href="mailto:<?php echo esc_attr($site_email); ?>"><?php echo esc_html($site_email); ?></a><br>
                <?php endif; ?>
                <?php if (!empty($site_url)): ?>
                    <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html(str_replace(['https://','http://'], '', $site_url)); ?></a>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>

<!-- ════════════════════════════ INVOICE TITLE ═══ -->
<div class="invoice-title-bar">
    <table>
        <tr>
            <td class="title-cell">INVOICE</td>
            <td class="meta-cell">
                <strong>Invoice #:</strong> <?php echo esc_html($invoice_number); ?><br>
                <strong>Date:</strong> <?php echo esc_html($invoice_date); ?>
            </td>
        </tr>
    </table>
</div>

<!-- ════════════════════════════════ BODY ═══ -->
<div class="body-wrap">

    <!-- Bill To + Payment Info -->
    <table class="info-table">
        <tr>
            <!-- LEFT: Invoice To -->
            <td>
                <div class="info-box-title">Invoice To</div>
                <div class="info-box-body">
                    <strong><?php echo esc_html($bill_to_name); ?></strong><br>
                    <?php if (!empty($bill_to_attn)): ?>
                        Attn: <?php echo esc_html($bill_to_attn); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($invoice->client_address)): ?>
                        <?php echo nl2br(esc_html($invoice->client_address)); ?>
                    <?php endif; ?>
                </div>
            </td>
            <!-- RIGHT: Payment Info -->
            <td>
                <div class="info-box-title">Payment Info</div>
                <div class="info-box-body">
                    <strong>Account Name:</strong> <?php echo esc_html($account_name); ?><br>
                    <strong>Bank Name:</strong> <?php echo esc_html($bank_name); ?><br>
                    <strong>BSB Number:</strong> <?php echo esc_html($bsb); ?><br>
                    <strong>ACC Number:</strong> <?php echo esc_html($account_number); ?><br>
                    <br>
                    <strong>Payment Terms:</strong><br>
                    <span class="terms-badge"><?php echo esc_html($term_label); ?></span>
                </div>
            </td>
        </tr>
    </table>

    <!-- ── Line Items ── -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:5%;">#</th>
                <th style="width:55%;">Project Description</th>
                <th style="width:20%;">Payment Term</th>
                <th class="right" style="width:20%;">Project Fees</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $i => $item):
                $item_term = $term_labels[$item['payment_term'] ?? $payment_terms]
                           ?? ucwords(str_replace('_', ' ', $item['payment_term'] ?? $payment_terms));
            ?>
            <tr>
                <td><?php echo $i + 1; ?></td>
                <td><?php echo esc_html($item['description']); ?></td>
                <td><?php echo esc_html($item_term); ?></td>
                <td class="right">$<?php echo number_format(floatval($item['fee']), 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="subtotal-row">
                <td colspan="3" class="right"><strong>Sub Total:</strong></td>
                <td class="right">$<?php echo number_format($sub_total, 2); ?></td>
            </tr>
            <?php/*  if (!empty($invoice->tax_amount) && floatval($invoice->tax_amount) > 0): ?>
            <tr>
                <td colspan="3" class="right">GST (10%):</td>
                <td class="right">$<?php echo number_format(floatval($invoice->tax_amount), 2); ?></td>
            </tr>
            <?php endif; ?>
            <tr class="total-row">
                <td colspan="3" class="right">Total:</td>
                <td class="right">$<?php echo number_format($total, 2); ?></td>
            </tr> */ ?>
            <tr class="subtotal-row">
                <td colspan="3" class="right"><strong>Sub Total (ex. tax):</strong></td>
                <td class="right">$<?php echo number_format($sub_total, 2); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="right">GST (10%):</td>
                <td class="right">$<?php echo number_format($gst_amount, 2); ?></td>
            </tr>
            <tr class="total-row">
                <td colspan="3" class="right">Total (Inc. GST):</td>
                <td class="right">$<?php echo number_format($total, 2); ?></td>
            </tr>

        </tfoot>
    </table>

    <?php if (!empty($invoice->notes)): ?>
    <div style="margin-top:10px; padding:10px 14px; background:#f8f9fd; border-left:4px solid #1a3a6b; font-size:11px; color:#444;">
        <strong>Notes:</strong><br>
        <?php echo nl2br(esc_html($invoice->notes)); ?>
    </div>
    <?php endif; ?>

</div>
<!-- ════════════════════════════════ FOOTER ═══ -->
<div class="footer-band">
    <div class="thank-you">Thank you for your business</div>
    <?php echo esc_html($site_url ?: $site_name); ?>
    <?php if (!empty($site_phone)): ?> &nbsp;|&nbsp; <?php echo esc_html($site_phone); ?><?php endif; ?>
</div>

</body>
</html>