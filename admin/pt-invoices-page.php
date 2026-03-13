<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$rows = PT_Invoice_Manager::get_all_with_enquiries();
?>
<div class="wrap">

    <h1 style="display:flex;align-items:center;gap:10px;">
        <span class="dashicons dashicons-media-spreadsheet" style="font-size:28px;color:#4a7c59;"></span>
        Invoices
    </h1>

    <?php if ( isset( $_GET['message'] ) ) : ?>
        <?php $msgs = [
            'created' => [ 'type' => 'success', 'text' => '✅ Invoice created successfully.' ],
            'paid'    => [ 'type' => 'success', 'text' => '✅ Term marked as paid and receipt sent.' ],
            'error'   => [ 'type' => 'error',   'text' => '❌ Something went wrong. Please try again.' ],
        ];
        $m = $msgs[ sanitize_key( $_GET['message'] ) ] ?? null;
        if ( $m ) : ?>
            <div class="notice notice-<?php echo $m['type']; ?> is-dismissible"><p><?php echo $m['text']; ?></p></div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Summary bar -->
    <?php
    $total_invoices  = count( array_filter( $rows, fn($r) => $r->invoice_id ) );
    $paid_invoices   = count( array_filter( $rows, fn($r) => $r->invoice_status === 'paid' ) );
    $no_invoice      = count( array_filter( $rows, fn($r) => ! $r->invoice_id ) );
    ?>
    <div style="display:flex;gap:15px;margin:15px 0 20px;">
        <?php foreach ( [
            [ 'Accepted Enquiries', count($rows),       '#4a7c59', '#d4edda' ],
            [ 'Invoices Generated', $total_invoices,    '#0073aa', '#cce5ff' ],
            [ 'Fully Paid',         $paid_invoices,     '#28a745', '#d4edda' ],
            [ 'Awaiting Invoice',   $no_invoice,        '#856404', '#fff3cd' ],
        ] as [$label, $count, $color, $bg] ) : ?>
            <div style="background:<?php echo $bg; ?>;border-radius:8px;padding:15px 20px;flex:1;text-align:center;">
                <div style="font-size:28px;font-weight:700;color:<?php echo $color; ?>;"><?php echo $count; ?></div>
                <div style="font-size:12px;color:#555;margin-top:4px;"><?php echo $label; ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Table -->
    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;overflow:hidden;">
        <table class="wp-list-table widefat fixed" style="border:none;">
            <thead>
                <tr style="background:#f9f9f9;">
                    <th style="width:90px;">Enquiry #</th>
                    <th>Customer</th>
                    <th>Caravan</th>
                    <th style="width:120px;">Quote Value</th>
                    <th style="width:110px;">Invoice #</th>
                    <th style="width:110px;">Payment Type</th>
                    <th style="width:70px;">Terms</th>
                    <th style="width:110px;">Invoice Status</th>
                    <th style="width:160px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $rows ) ) : ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:40px;color:#888;">
                            <span class="dashicons dashicons-info" style="font-size:32px;display:block;margin-bottom:8px;"></span>
                            No accepted enquiries found.
                        </td>
                    </tr>
                <?php else : foreach ( $rows as $row ) :
                    $amount = PT_Helpers::money( $row->final_price ?: $row->total_price );
                    $view_url   = PT_Helpers::page_url( [ 'view' => $row->invoice_id ] );
                    $create_url = PT_Helpers::page_url( [ 'view' => $row->enquiry_id, 'action' => 'create' ] );
                ?>
                    <tr>
                        <td><strong>#<?php echo str_pad( $row->enquiry_id, 5, '0', STR_PAD_LEFT ); ?></strong></td>
                        <td>
                            <strong><?php echo esc_html( $row->customer_name ); ?></strong><br>
                            <small style="color:#888;"><?php echo esc_html( $row->customer_email ); ?></small>
                        </td>
                        <td><?php echo esc_html( $row->caravan_name ?: '—' ); ?></td>
                        <td><strong><?php echo esc_html( $amount ); ?></strong></td>
                        <td>
                            <?php echo $row->invoice_number
                                ? '<strong>' . esc_html( $row->invoice_number ) . '</strong>'
                                : '<span style="color:#aaa;">—</span>'; ?>
                        </td>
                        <td>
                            <?php echo $row->payment_type
                                ? esc_html( PT_Helpers::payment_type_label( $row->payment_type ) )
                                : '<span style="color:#aaa;">—</span>'; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php echo $row->terms_count
                                ? intval( $row->terms_count )
                                : '<span style="color:#aaa;">—</span>'; ?>
                        </td>
                        <td>
                            <?php echo $row->invoice_id
                                ? PT_Helpers::invoice_status_badge( $row->invoice_status )
                                : '<span style="color:#aaa;font-size:12px;">No Invoice</span>'; ?>
                        </td>
                        <td>
                            <?php if ( $row->invoice_id ) : ?>
                                <a href="<?php echo esc_url( $view_url ); ?>"
                                   class="button button-small button-primary">
                                    <span class="dashicons dashicons-visibility" style="margin-top:3px;font-size:14px;"></span>
                                    View Invoice
                                </a>
                            <?php else : ?>
                                <a href="<?php echo esc_url( $create_url ); ?>"
                                   class="button button-small"
                                   style="background:#4a7c59;border-color:#4a7c59;color:#fff;">
                                    <span class="dashicons dashicons-plus-alt2" style="margin-top:3px;font-size:14px;"></span>
                                    Generate
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</div><!-- .wrap -->