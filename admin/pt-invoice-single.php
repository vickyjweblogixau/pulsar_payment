<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

$invoice_id = intval( $_GET['view'] ?? 0 );
$invoice    = PT_Invoice_Manager::get_invoice( $invoice_id );

if ( ! $invoice ) {
    echo '<div class="wrap"><div class="notice notice-error"><p>❌ Invoice not found.</p></div></div>';
    return;
}

$terms   = PT_Invoice_Manager::get_terms( $invoice_id );
$enquiry = $wpdb->get_row( $wpdb->prepare(
    "SELECT e.*, p.post_title AS caravan_name
     FROM {$wpdb->prefix}custom_build_enquiries e
     LEFT JOIN {$wpdb->posts} p ON p.ID = e.custom_build_id
     WHERE e.id = %d",
    $invoice->enquiry_id
) );

$list_url   = PT_Helpers::page_url();
$print_url  = add_query_arg( [ 'page' => 'pt-invoices', 'print' => '1', 'invoice_id' => $invoice->id ], admin_url('admin.php') );

$paid_total = array_sum( array_map( fn($t) => $t->status === 'paid' ? floatval($t->amount) : 0, $terms ) );
$remaining  = max( 0, floatval( $invoice->total_amount ) - $paid_total );
$paid_count = count( array_filter( $terms, fn($t) => $t->status === 'paid' ) );
$pct        = $invoice->total_amount > 0 ? round( ( $paid_total / floatval($invoice->total_amount) ) * 100 ) : 0;
?>

<style>
/* Term row states */
.pt-term-row-locked   { opacity:.55; background:#fafafa !important; }
.pt-term-row-unpaid   { background:#fffef5 !important; }
.pt-term-row-sent     { background:#f0f7ff !important; }
.pt-term-row-paid     { background:#f6fff8 !important; }

/* Buttons */
.pt-btn-send  { background:#0073aa;border-color:#0073aa;color:#fff;font-size:12px; }
.pt-btn-resend{ background:#6c757d;border-color:#6c757d;color:#fff;font-size:12px; }
.pt-btn-paid  { background:#4a7c59;border-color:#4a7c59;color:#fff;font-size:12px; }
.pt-btn-lock  { background:#e9ecef;border-color:#ccc;color:#aaa;font-size:12px;cursor:not-allowed; }

/* Status pills */
.pt-pill { display:inline-block;padding:3px 11px;border-radius:12px;font-size:12px;font-weight:600; }
.pt-pill-locked   { background:#e9ecef;color:#6c757d; }
.pt-pill-unpaid   { background:#fff3cd;color:#856404; }
.pt-pill-sent     { background:#cce5ff;color:#004085; }
.pt-pill-paid     { background:#d4edda;color:#155724; }

/* Step connector */
.pt-step-line { position:relative; }
/* .pt-step-line::before {
    content:'';position:absolute;left:21px;top:100%;width:2px;height:20px;
    background:#dee2e6;z-index:1;
}
.pt-step-line:last-child::before { display:none; }
*/
</style>

<div class="wrap">

    <!-- Header bar -->
    <div style="display:flex;justify-content:space-between;align-items:center;
                margin-bottom:20px;padding:18px 22px;background:#fff;
                border:1px solid #ddd;border-radius:8px;">
        <div style="display:flex;align-items:center;gap:14px;">
            <a href="<?php echo esc_url($list_url); ?>"
               style="text-decoration:none;color:#555;font-size:20px;line-height:1;">←</a>
            <h2 style="margin:0;">Invoice <?php echo esc_html($invoice->invoice_number); ?></h2>
            <?php echo PT_Helpers::invoice_status_badge( $invoice->status ); ?>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:13px;color:#888;">
                Created: <?php echo date('d M Y', strtotime($invoice->created_at)); ?>
            </span>
            <?php if ( $invoice->status !== 'cancelled' ) : ?>
            <a href="<?php echo esc_url($print_url); ?>" target="_blank" class="button"
               style="display:flex;align-items:center;gap:5px;">
                <span class="dashicons dashicons-printer" style="margin-top:3px;font-size:15px;"></span>
                Print / PDF
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( isset($_GET['message']) ) :
        $msgs = [
            'created'      => ['success','✅ Invoice created successfully.'],
            'term_sent'    => ['success','📧 Invoice emailed to customer.'],
            'term_resent'  => ['success','📧 Invoice re-sent to customer.'],
            'term_paid'    => ['success','✅ Payment confirmed. Receipt sent to customer.'],
            'error'        => ['error',  '❌ Something went wrong.'],
        ];
        [$type,$text] = $msgs[ sanitize_key($_GET['message']) ] ?? ['info','Action completed.'];
    ?>
        <div class="notice notice-<?php echo $type; ?> is-dismissible"><p><?php echo $text; ?></p></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;">

        <!-- LEFT: Terms -->
        <div>

            <!-- Invoice summary -->
            <div style="background:#fff;border:1px solid #ddd;border-radius:8px;
                        padding:20px;margin-bottom:20px;
                        display:grid;grid-template-columns:repeat(3,1fr);gap:15px;">
                <div>
                    <div style="font-size:11px;text-transform:uppercase;color:#888;margin-bottom:4px;">Total Amount</div>
                    <div style="font-size:22px;font-weight:700;color:#4a7c59;">
                        <?php echo PT_Helpers::money($invoice->total_amount); ?>
                    </div>
                </div>
                <!---div>
                    <div style="font-size:11px;text-transform:uppercase;color:#888;margin-bottom:4px;">Payment Type</div>
                    <div style="font-size:16px;font-weight:600;">
                        <?php echo esc_html(PT_Helpers::payment_type_label($invoice->payment_type)); ?>
                    </div>
                </div--->
                <div>
                    <div style="font-size:11px;text-transform:uppercase;color:#888;margin-bottom:4px;">Payment Stage</div>
                    <div style="font-size:16px;font-weight:600;">
                        <?php echo $paid_count; ?> / <?php echo count($terms); ?>
                    </div>
                </div>
                <div>
                    <div style="font-size:11px;text-transform:uppercase;color:#888;margin-bottom:4px;">Remaining Balance</div>
                    <div style="font-size:22px;font-weight:700;
                                color:<?php echo $remaining > 0 ? '#856404' : '#155724'; ?>;">
                        <?php echo PT_Helpers::money($remaining); ?>
                    </div>
                </div>
            </div>

            <!-- Progress bar -->
            <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px;">
                    <span style="font-weight:600;">Payment Progress</span>
                    <span style="color:#888;"><?php echo $paid_count; ?> / <?php echo count($terms); ?> terms paid</span>
                </div>
                <div style="background:#eee;border-radius:6px;height:12px;overflow:hidden;">
                    <div style="background:<?php echo $pct===100 ? '#4a7c59' : '#0073aa'; ?>;
                                height:100%;width:<?php echo $pct; ?>%;border-radius:6px;
                                transition:width .5s;"></div>
                </div>
                <div style="text-align:right;font-size:11px;color:#888;margin-top:5px;">
                    <?php echo $pct; ?>% — <?php echo PT_Helpers::money($paid_total); ?> collected
                </div>
            </div>

            <!-- Terms table -->
            <div style="background:#fff;border:1px solid #ddd;border-radius:8px;overflow:hidden;">
                <div style="padding:15px 20px;border-bottom:1px solid #eee;">
                    <h3 style="margin:0;">
                        Payment Terms
                        <span style="font-size:13px;font-weight:400;color:#888;margin-left:6px;">
                            (<?php echo count($terms); ?> terms)
                        </span>
                    </h3>
                </div>
                <table class="wp-list-table widefat fixed" style="border:none;">
                    <thead>
                        <tr style="background:#f9f9f9;">
                            <th style="width:10%;text-align:center;">#</th>
                            <th  style="width:20%;">Name</th>
                            <th style="width:15%;">Amount</th>
                            <th style="width:15%;">Due Date</th>
                            <th style="width:15%;">Status</th>
                            <th style="width:15%;">Send Invoice</th>
                            <th style="width:15%;">Mark Paid</th>
                            <th style="width:15%;">Paid On</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $term_count = count( $terms );
                    foreach ( $terms as $i => $term ) :
                        $is_locked  = $term->status === 'locked';
                        $is_unpaid  = $term->status === 'unpaid';
                        $is_sent    = $term->status === 'invoice_sent';
                        $is_paid    = $term->status === 'paid';
                        $is_last    = ( $i === $term_count - 1 );

                        // ── Overdue detection ─────────────────────────────────────────────────
                        $is_overdue = ( ( $is_unpaid || $is_sent ) && $term->due_date && strtotime( $term->due_date ) < strtotime( 'today' ) );

                        // ── Row CSS class ─────────────────────────────────────────────────────
                        if      ( $is_overdue ) $row_class = 'pt-term-row-overdue';
                        elseif  ( $is_sent    ) $row_class = 'pt-term-row-sent';
                        else                    $row_class = 'pt-term-row-' . $term->status;

                        // ── Circle background colour ──────────────────────────────────────────
                        if      ( $is_paid    ) $circle_bg = '#4a7c59';
                        elseif  ( $is_sent    ) $circle_bg = '#0073aa';
                        elseif  ( $is_overdue ) $circle_bg = '#dc3545';
                        elseif  ( $is_unpaid  ) $circle_bg = '#856404';
                        else                    $circle_bg = '#dee2e6';   // locked

                        $circle_color = $is_locked ? '#aaa' : '#fff';

                        // ── Date strings ──────────────────────────────────────────────────────
                        $due_str  = $term->due_date         ? date( 'd M Y', strtotime( $term->due_date ) )         : '—';
                        $paid_str = $term->paid_at          ? date( 'd M Y', strtotime( $term->paid_at ) )          : '—';
                        $sent_str = $term->invoice_sent_at  ? date( 'd M Y', strtotime( $term->invoice_sent_at ) )  : '';
                    ?>
                        <tr id="pt-row-<?php echo $term->id; ?>"
                            class=" <?php  echo $is_last ? '' : 'pt-step-line';  ?> <?php echo esc_attr( $row_class );  ?>">

                            <!-- Term number circle -->
                            <td style="text-align:center;">
                                <span style="display:inline-flex;align-items:center;justify-content:center;
                                            width:28px;height:28px;border-radius:50%;font-size:12px;font-weight:700;
                                            background:<?php echo $circle_bg; ?>;color:<?php echo $circle_color; ?>;">
                                    <?php echo $is_paid ? '✓' : intval( $term->term_number ); ?>
                                </span>
                            </td>

                            <!-- Name -->
                            <td>
                                <strong><?php echo esc_html( $term->term_name ); ?></strong>
                                <?php if ( $is_locked ) : ?>
                                    <br><span style="font-size:11px;color:#aaa;">🔒 Locked – pay previous term first</span>
                                <?php elseif ( $is_overdue ) : ?>
                                    <br><span style="font-size:11px;color:#dc3545;">⚠️ Overdue</span>
                                <?php elseif ( $is_sent && $sent_str ) : ?>
                                    <br><span style="font-size:11px;color:#0073aa;">📧 Sent: <?php echo esc_html( $sent_str ); ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Amount -->
                            <td><strong><?php echo PT_Helpers::money( $term->amount ); ?></strong></td>

                            <!-- Due date -->
                            <td style="font-size:13px;<?php echo $is_overdue ? 'color:#dc3545;font-weight:700;' : ''; ?>">
                                <?php echo esc_html( $due_str ); ?>
                            </td>

                            <!-- Status pill -->
                            <td id="pt-status-<?php echo $term->id; ?>">
                                <?php
                                if      ( $is_locked  ) echo '<span class="pt-pill pt-pill-locked">🔒 Locked</span>';
                                elseif  ( $is_overdue ) echo '<span class="pt-pill pt-pill-overdue">🔴 Overdue</span>';
                                elseif  ( $is_unpaid  ) echo '<span class="pt-pill pt-pill-unpaid">🟡 Unpaid</span>';
                                elseif  ( $is_sent    ) echo '<span class="pt-pill pt-pill-sent">📧 Invoice Sent</span>';
                                elseif  ( $is_paid    ) echo '<span class="pt-pill pt-pill-paid">✅ Paid</span>';
                                ?>
                            </td>

                            <!-- Send Invoice button -->
                            <td id="pt-send-cell-<?php echo $term->id; ?>">
                                <?php if ( $is_paid ) : ?>
                                    <span style="color:#aaa;font-size:12px;">—</span>
                                <?php elseif ( $is_locked ) : ?>
                                    <button class="button pt-btn-lock" disabled>🔒 Locked</button>
                                <?php elseif ( $is_unpaid || $is_overdue ) : ?>
                                    <button class="button pt-btn-send pt-send-term"
                                            data-term-id="<?php echo $term->id; ?>"
                                            data-term-name="<?php echo esc_attr( $term->term_name ); ?>">
                                        📧 Send Invoice
                                    </button>
                                <?php elseif ( $is_sent ) : ?>
                                    <button class="button pt-btn-resend pt-send-term"
                                            data-term-id="<?php echo $term->id; ?>"
                                            data-term-name="<?php echo esc_attr( $term->term_name ); ?>">
                                        🔄 Resend
                                    </button>
                                <?php endif; ?>
                            </td>

                            <!-- Mark Paid button -->
                            <td id="pt-paid-cell-<?php echo $term->id; ?>">
                                <?php if ( $is_paid ) : ?>
                                    <span style="color:#aaa;font-size:12px;">—</span>
                                <?php elseif ( $is_locked ) : ?>
                                    <button class="button pt-btn-lock" disabled>🔒 Locked</button>
                                <?php else : ?>
                                    <button class="button pt-btn-paid pt-mark-paid"
                                            data-term-id="<?php echo $term->id; ?>"
                                            data-term-name="<?php echo esc_attr( $term->term_name ); ?>"
                                            data-amount="<?php echo esc_attr( $term->amount ); ?>">
                                        ✅ Mark Paid
                                    </button>
                                <?php endif; ?>
                            </td>

                            <!-- Paid on date -->
                            <td id="pt-paidat-<?php echo $term->id; ?>" style="font-size:12px;color:#555;">
                                <?php echo $is_paid ? esc_html( $paid_str ) : '<span style="color:#aaa;">—</span>'; ?>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                    </tbody>

                    <tfoot>
                        <tr style="background:#f9f9f9;">
                            <td colspan="2" style="text-align:right;font-weight:700;padding:10px 12px;">Total</td>
                            <td style="font-weight:700;padding:10px 12px;">
                                <?php echo PT_Helpers::money( $invoice->total_amount ); ?>
                            </td>
                            <td colspan="5"></td>
                        </tr>
                    </tfoot>
                </table>
            </div><!-- terms table -->

        </div><!-- left -->

        <!-- RIGHT: Customer details -->
        <div>
            <?php if ( $enquiry ) : ?>
            <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;">
                <h3 style="margin:0 0 14px;padding-bottom:10px;border-bottom:1px solid #eee;">
                    <span class="dashicons dashicons-admin-users" style="color:#4a7c59;"></span> Customer
                </h3>
                <?php foreach ([
                    ['Name',         $enquiry->customer_name],
                    ['Email',        $enquiry->customer_email],
                    ['Phone',        $enquiry->customer_phone],
                    ['Caravan',      $enquiry->caravan_name ?: '—'],
                    ['Enquiry',      '#' . str_pad($enquiry->id, 5, '0', STR_PAD_LEFT)],
                    ['Enquiry Date', date('d M Y', strtotime($enquiry->created_at))],
                ] as [$lbl,$val]) : ?>
                    <div style="display:flex;justify-content:space-between;padding:7px 0;
                                border-bottom:1px solid #f5f5f5;font-size:13px;">
                        <span style="color:#888;"><?php echo $lbl; ?></span>
                        <span style="font-weight:600;text-align:right;max-width:170px;word-break:break-word;">
                            <?php echo esc_html($val); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Term timeline -->
            <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;">
                <h3 style="margin:0 0 16px;padding-bottom:10px;border-bottom:1px solid #eee;">
                    📋 Term Timeline
                </h3>
                <?php foreach ( $terms as $t ) :
                    $is_paid   = $t->status === 'paid';
                    $is_sent   = $t->status === 'invoice_sent';
                    $is_active = in_array($t->status, ['unpaid','invoice_sent']);
                    $dot_bg    = $is_paid ? '#4a7c59' : ($is_active ? '#0073aa' : '#dee2e6');
                    $dot_color = $is_active||$is_paid ? '#fff' : '#aaa';
                ?>
                    <div style="display:flex;gap:12px;margin-bottom:16px;position:relative;">
                        <!-- dot -->
                        <div style="flex-shrink:0;width:32px;height:32px;border-radius:50%;
                                    background:<?php echo $dot_bg; ?>;color:<?php echo $dot_color; ?>;
                                    display:flex;align-items:center;justify-content:center;
                                    font-size:13px;font-weight:700;z-index:2;">
                            <?php echo $is_paid ? '✓' : intval($t->term_number); ?>
                        </div>
                        <!-- info -->
                        <div style="flex:1;padding-top:4px;">
                            <div style="font-size:13px;font-weight:600;color:#000;">
                                <?php echo esc_html($t->term_name); ?>
                                &nbsp;
                                <?php
                                if      ($t->status==='paid')         echo '<span class="pt-pill pt-pill-paid" style="font-size:11px;">✅ Paid</span>';
                                elseif  ($t->status==='invoice_sent') echo '<span class="pt-pill pt-pill-sent" style="font-size:11px;">📧 Sent</span>';
                                elseif  ($t->status==='unpaid')       echo '<span class="pt-pill pt-pill-unpaid" style="font-size:11px;">Active</span>';
                                else                                   echo '<span class="pt-pill pt-pill-locked" style="font-size:11px;">🔒</span>';
                                ?>
                            </div>
                            <div style="font-size:12px;color:#888;margin-top:2px;">
                                <?php echo PT_Helpers::money($t->amount); ?>
                                <?php if ($t->due_date) echo ' · Due ' . date('d M Y', strtotime($t->due_date)); ?>
                            </div>
                            <?php if ($is_paid && $t->paid_at) : ?>
                                <div style="font-size:11px;color:#4a7c59;margin-top:2px;">
                                    Paid on <?php echo date('d M Y', strtotime($t->paid_at)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div><!-- right -->
    </div><!-- grid -->

</div><!-- .wrap -->

<!-- Toast -->
<div id="pt-toast" style="display:none;position:fixed;bottom:28px;right:28px;
     background:#4a7c59;color:#fff;padding:13px 22px;border-radius:8px;
     font-size:14px;font-weight:600;box-shadow:0 4px 14px rgba(0,0,0,.2);z-index:9999;"></div>

<script>
(function($){
    var NONCE = '<?php echo wp_create_nonce('pt_admin_nonce'); ?>';

    function toast(msg, err) {
        $('#pt-toast').css('background', err ? '#c00' : '#4a7c59').text(msg).fadeIn(200);
        setTimeout(function(){ $('#pt-toast').fadeOut(400); }, 3500);
    }

    // ── Send Invoice (per term) ───────────────────────────────────────────────
    $(document).on('click', '.pt-send-term', function(){
        var $btn     = $(this);
        var termId   = $btn.data('term-id');
        var termName = $btn.data('term-name');
        var isResend = $btn.hasClass('pt-btn-resend');

        if (!confirm( (isResend ? 'Re-send' : 'Send') + ' invoice for "' + termName + '" to the customer?' )) return;

        $btn.prop('disabled', true).text('Sending…');

        $.post(ptAdmin.ajax_url, {
            action  : 'pt_send_term_invoice',
            nonce   : NONCE,
            term_id : termId,
        }, function(res){
            if (res.success) {
                // Update status pill
                $('#pt-status-' + termId).html(
                    '<span class="pt-pill pt-pill-sent">📧 Invoice Sent</span>'
                );
                // Swap button to Resend
                $btn.removeClass('pt-btn-send').addClass('pt-btn-resend')
                    .text('🔄 Resend')
                    .prop('disabled', false)
                    .data('term-name', termName);
                // Update row highlight
                $('#pt-row-' + termId)
                    .removeClass('pt-term-row-unpaid')
                    .addClass('pt-term-row-sent');

                toast('📧 Invoice sent to customer.');
            } else {
                $btn.prop('disabled', false).text( isResend ? '🔄 Resend' : '📧 Send Invoice' );
                toast('❌ ' + (res.data || 'Failed to send.'), true);
            }
        }).fail(function(){
            $btn.prop('disabled', false).text( isResend ? '🔄 Resend' : '📧 Send Invoice' );
            toast('❌ Network error.', true);
        });
    });

    // ── Mark Paid ─────────────────────────────────────────────────────────────
    $(document).on('click', '.pt-mark-paid', function(){
        var $btn   = $(this);
        var termId = $btn.data('term-id');

        if (!confirm('Confirm payment received? A receipt will be emailed to the customer.')) return;
        $btn.prop('disabled', true).text('Processing…');

        $.post(ptAdmin.ajax_url, {
            action  : 'pt_mark_term_paid',
            nonce   : NONCE,
            term_id : termId,
        }, function(res){
            if (res.success) {
                var d = res.data;

                // Current term → Paid
                $('#pt-status-' + termId).html('<span class="pt-pill pt-pill-paid">✅ Paid</span>');
                $('#pt-send-cell-' + termId).html('<span style="color:#aaa;font-size:12px;">—</span>');
                $('#pt-paid-cell-' + termId).html('<span style="color:#aaa;font-size:12px;">—</span>');
                $('#pt-paidat-'    + termId).text(d.paid_at);
                $('#pt-row-'       + termId)
                    .removeClass('pt-term-row-unpaid pt-term-row-sent')
                    .addClass('pt-term-row-paid');

                toast('✅ Payment confirmed. Receipt sent to customer.');

                // Reload after 1.5s so next term unlocks (server already changed its status)
                setTimeout(function(){ location.reload(); }, 1500);
            } else {
                $btn.prop('disabled', false).text('✅ Mark Paid');
                toast('❌ ' + (res.data || 'Error.'), true);
            }
        }).fail(function(){
            $btn.prop('disabled', false).text('✅ Mark Paid');
            toast('❌ Network error.', true);
        });
    });

})(jQuery);
</script>