<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

$enquiry_id = intval( $_GET['view'] ?? 0 );

// Load enquiry
$enquiry = $wpdb->get_row( $wpdb->prepare(
    "SELECT e.*, p.post_title AS caravan_name
     FROM {$wpdb->prefix}custom_build_enquiries e
     LEFT JOIN {$wpdb->posts} p ON p.ID = e.custom_build_id
     WHERE e.id = %d AND e.status = 'approved'",
    $enquiry_id
) );

if ( ! $enquiry ) {
    echo '<div class="wrap"><div class="notice notice-error"><p>❌ Enquiry not found or not approved.</p></div></div>';
    return;
}

// Already has invoice?
$existing = PT_Invoice_Manager::get_by_enquiry( $enquiry_id );
if ( $existing ) {
    wp_redirect( PT_Helpers::page_url( [ 'view' => $existing->id ] ) );
    exit;
}

$amount = floatval( $enquiry->final_price ?: $enquiry->total_price );
$list_url = PT_Helpers::page_url();
?>
<div class="wrap">

    <h1 style="display:flex;align-items:center;gap:10px;">
        <a href="<?php echo esc_url( $list_url ); ?>" style="text-decoration:none;color:#555;font-size:18px;">← Back</a>
        &nbsp; Generate Invoice
    </h1>

    <!-- Enquiry Summary -->
    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;display:grid;grid-template-columns:repeat(4,1fr);gap:15px;">
        <?php foreach ( [
            [ 'Enquiry #',  '#' . str_pad( $enquiry->id, 5, '0', STR_PAD_LEFT ) ],
            [ 'Customer',   $enquiry->customer_name ],
            [ 'Caravan',    $enquiry->caravan_name ?: '—' ],
            [ 'Quote Value', PT_Helpers::money( $amount ) ],
        ] as [$lbl, $val] ) : ?>
            <div>
                <div style="font-size:11px;text-transform:uppercase;color:#888;margin-bottom:4px;"><?php echo $lbl; ?></div>
                <div style="font-weight:600;font-size:15px;"><?php echo esc_html( $val ); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Invoice Form -->
    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:25px;">
        <h2 style="margin-top:0;">Invoice Details</h2>

        <table class="form-table" style="max-width:600px;">
            <tr>
                <th><label for="pt_total_amount">Total Amount</label></th>
                <td>
                    <input type="number" id="pt_total_amount" step="0.01" min="0"
                           value="<?php echo esc_attr( $amount ); ?>"
                           style="width:200px;" />
                    <p class="description">Auto-filled from quote. Edit if needed.</p>
                </td>
            </tr>
            <tr>
                <th><label>Payment Type</label></th>
                <td>
                    <label style="margin-right:20px;">
                        <input type="radio" name="pt_payment_type" value="weekly" checked> Per Week
                    </label>
                    <label>
                        <input type="radio" name="pt_payment_type" value="monthly"> Per Month
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="pt_terms_count">Number of Terms</label></th>
                <td>
                    <input type="number" id="pt_terms_count" min="1" max="24" value="3"
                           style="width:80px;" />
                    <button type="button" onclick="ptGenerateTermRows()"
                            class="button" style="margin-left:10px;">Set Terms</button>
                </td>
            </tr>
        </table>

        <!-- Dynamic term rows -->
        <div id="pt-terms-wrapper" style="margin-top:20px;"></div>

        <!-- Submit -->
        <div style="margin-top:25px;">
            <button type="button" id="pt-save-invoice" class="button button-primary button-large"
                    style="background:#4a7c59;border-color:#4a7c59;">
                💾 Save Invoice
            </button>
            <a href="<?php echo esc_url( $list_url ); ?>" class="button button-large" style="margin-left:10px;">
                Cancel
            </a>
            <span id="pt-save-msg" style="margin-left:15px;font-weight:600;"></span>
        </div>
    </div>

</div><!-- .wrap -->

<script>
(function($){
    var ENQUIRY_ID = <?php echo intval( $enquiry_id ); ?>;

    // ── Generate term rows ────────────────────────────────────────────────────
    window.ptGenerateTermRows = function() {
        var n = parseInt( $('#pt_terms_count').val() ) || 1;
        var html = '<h3>Payment Terms</h3>';
        html += '<table class="wp-list-table widefat fixed" style="max-width:750px;">';
        html += '<thead><tr><th style="width:60px;">Term</th><th>Name</th><th style="width:140px;">Amount ($)</th><th style="width:150px;">Due Date</th></tr></thead><tbody>';
        var names = ['First Payment','Second Payment','Third Payment','Fourth Payment','Fifth Payment'];
        for (var i = 0; i < n; i++) {
            html += '<tr>';
            html += '<td style="text-align:center;font-weight:700;">' + (i+1) + '</td>';
            html += '<td><input type="text" class="pt-term-name regular-text" placeholder="e.g. ' + (names[i] || 'Term '+(i+1)) + '" value="' + (names[i] || 'Term '+(i+1)) + '" /></td>';
            html += '<td><input type="number" class="pt-term-amount" step="0.01" min="0" placeholder="0.00" style="width:120px;" /></td>';
            html += '<td><input type="date" class="pt-term-date" /></td>';
            html += '</tr>';
        }
        html += '</tbody></table>';
        $('#pt-terms-wrapper').html(html);
    };

    // ── Auto-generate on load ─────────────────────────────────────────────────
    ptGenerateTermRows();

    // ── Save invoice via AJAX ─────────────────────────────────────────────────
    $('#pt-save-invoice').on('click', function(){
        var $btn = $(this);
        var terms = [];
        var valid = true;

        $('#pt-terms-wrapper tbody tr').each(function(){
            var name   = $(this).find('.pt-term-name').val().trim();
            var amount = $(this).find('.pt-term-amount').val().trim();
            var date   = $(this).find('.pt-term-date').val();
            if (!name || !amount) { valid = false; return false; }
            terms.push({ name: name, amount: amount, due_date: date });
        });

        if (!valid) {
            $('#pt-save-msg').css('color','#c00').text('⚠ Please fill all term names and amounts.');
            return;
        }
        if (!terms.length) {
            $('#pt-save-msg').css('color','#c00').text('⚠ Please set at least one term.');
            return;
        }

        $btn.prop('disabled', true).text('Saving…');
        $('#pt-save-msg').text('');

        $.post(ptAdmin.ajax_url, {
            action       : 'pt_generate_invoice',
            nonce        : ptAdmin.nonce,
            enquiry_id   : ENQUIRY_ID,
            payment_type : $('input[name="pt_payment_type"]:checked').val(),
            total_amount : $('#pt_total_amount').val(),
            terms        : terms,
        }, function(res){
            $btn.prop('disabled', false).text('💾 Save Invoice');
            if (res.success) {
                $('#pt-save-msg').css('color','green').text('✅ Invoice created! Redirecting…');
                setTimeout(function(){
                    window.location.href = '<?php echo esc_js( admin_url('admin.php?page=pt-invoices') ); ?>&view=' + res.data.invoice_id + '&message=created';
                }, 1000);
            } else {
                $('#pt-save-msg').css('color','#c00').text('❌ ' + (res.data || 'Error saving invoice.'));
            }
        }).fail(function(){
            $btn.prop('disabled', false).text('💾 Save Invoice');
            $('#pt-save-msg').css('color','#c00').text('❌ Network error.');
        });
    });
})(jQuery);
</script>
