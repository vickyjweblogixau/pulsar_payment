<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PT_Panel {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'admin_footer',          [ $this, 'approval_popup_html' ] );
        add_action( 'admin_footer',          [ $this, 'payment_panel_html' ] );
    }

    /* ── Enqueue assets only on enquiry admin pages ──────── */
    public function enqueue( $hook ) {
        $screen = get_current_screen();
        if ( ! $screen ) return;
        // Only load on the custom build admin page
        if ( strpos( $hook, 'custom-build-enquiries' ) === false &&
             ! isset( $_GET['page'] ) ) return;
        if ( ! isset( $_GET['page'] ) ||
             strpos( $_GET['page'], 'custom-build-enquir' ) === false ) return;

        wp_enqueue_style(
            'pt-admin-css',
            PT_PLUGIN_URL . 'assets/css/admin.css',
            [], PT_VERSION
        );
        wp_enqueue_script(
            'pt-admin-js',
            PT_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ], PT_VERSION, true
        );
        wp_localize_script( 'pt-admin-js', 'ptVars', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'pt_nonce' ),
        ] );
    }

    /* ── Approval popup modal (injected into admin_footer) ── */
    public function approval_popup_html() {
        // Only render on the single enquiry page
        if ( ! isset( $_GET['page'] ) ||
             strpos( $_GET['page'], 'custom-build-enquir' ) === false ) return;
        if ( ! isset( $_GET['enquiry_id'] ) ) return;

        $enquiry_id = intval( $_GET['enquiry_id'] );

        // Pre-fill display name from customer's WP account
        global $wpdb;
        $enq = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . $wpdb->prefix . 'custom_build_enquiries WHERE id = %d',
            $enquiry_id
        ) );
        $default_name = '';
        if ( $enq ) {
            $u = get_user_by( 'email', $enq->customer_email );
            $default_name = $u ? $u->display_name : ( $enq->customer_name ?? '' );
        }
        ?>
        <!-- PT: Approval Popup -->
        <div id="pt-approval-overlay" style="display:none;">
            <div id="pt-approval-modal">
                <h2>💳 Set Up Payment Terms</h2>
                <p style="color:#555;font-size:13px;margin:0 0 18px;">
                    Set the invoice name and payment schedule before approving this enquiry.
                </p>

                <!-- Invoice Display Name -->
                <div class="pt-field-row">
                    <label for="pt-display-name">
                        Invoice Display Name
                        <span class="pt-tip">Name printed on all invoices for this enquiry</span>
                    </label>
                    <input type="text" id="pt-display-name"
                           value="<?php echo esc_attr( $default_name ); ?>"
                           placeholder="e.g. Vigneshwari" />
                </div>

                <!-- Number of terms -->
                <div class="pt-field-row" style="margin-top:14px;">
                    <label for="pt-term-count">Number of Payment Terms (1–5)</label>
                    <select id="pt-term-count">
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3" selected>3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>

                <!-- Dynamic term rows -->
                <div id="pt-terms-rows" style="margin-top:16px;"></div>

                <div id="pt-modal-error" style="display:none;color:#c0392b;font-size:13px;margin-top:10px;"></div>

                <div class="pt-modal-actions">
                    <button id="pt-save-btn" class="button button-primary">
                        ✅ Save &amp; Approve Enquiry
                    </button>
                    <button id="pt-cancel-btn" class="button">Cancel</button>
                </div>
            </div>
        </div>

        <!-- PT: Confirm payment received modal -->
        <div id="pt-confirm-overlay" style="display:none;">
            <div id="pt-confirm-modal">
                <h2>💰 Confirm Payment Received</h2>
                <p id="pt-confirm-text" style="color:#555;font-size:13px;"></p>
                <div class="pt-modal-actions">
                    <button id="pt-confirm-yes" class="button button-primary">Yes, Mark as Paid</button>
                    <button id="pt-confirm-no"  class="button">Cancel</button>
                </div>
            </div>
        </div>

        <script>
        // Pass enquiry ID to JS
        window.ptEnquiryId = <?php echo $enquiry_id; ?>;
        </script>
        <?php
    }

    /* ── Payment Terms panel (injected below enquiry detail) ─ */
    public function payment_panel_html() {
        if ( ! isset( $_GET['page'] ) ||
             strpos( $_GET['page'], 'custom-build-enquir' ) === false ) return;
        if ( ! isset( $_GET['enquiry_id'] ) ) return;

        $enquiry_id = intval( $_GET['enquiry_id'] );
        $terms      = PT_Database::get_terms( $enquiry_id );
        if ( empty( $terms ) ) return;

        $total_paid      = 0;
        $total_remaining = 0;
        foreach ( $terms as $t ) {
            if ( $t->status === 'paid' ) $total_paid      += $t->amount;
            else                         $total_remaining += $t->amount;
        }
        ?>
        <script>
        // Inject the payment panel into the sidebar after DOM ready
        document.addEventListener('DOMContentLoaded', function () {
            var panel = document.getElementById('pt-terms-panel');
            if (!panel) return;
            var sidebar = document.querySelector('.postbox-container-1') ||
                          document.querySelector('#side-sortables');
            if (sidebar) {
                sidebar.insertBefore(panel, sidebar.firstChild);
            } else {
                // Fallback: append after main content
                document.querySelector('#post-body-content')?.appendChild(panel);
            }
            panel.style.display = 'block';
        });
        </script>

        <div id="pt-terms-panel" class="postbox" style="display:none;margin-top:20px;">
            <div class="postbox-header">
                <h2 class="hndle" style="padding:12px 15px;font-size:14px;">
                    💳 Payment Terms &amp; Invoices
                    <span style="background:#d4edda;color:#155724;font-size:11px;
                          padding:2px 8px;border-radius:10px;font-weight:600;margin-left:8px;">
                        <?php echo count( $terms ); ?> Term<?php echo count( $terms ) > 1 ? 's' : ''; ?>
                    </span>
                </h2>
            </div>
            <div class="inside" style="padding:0;">

                <!-- Invoice name row -->
                <div style="padding:10px 15px;background:#f0f7ff;border-bottom:1px solid #dde;
                            font-size:12px;color:#555;">
                    <strong>Invoice Name:</strong>
                    <?php echo esc_html( PT_Database::get_display_name( $enquiry_id ) ); ?>
                </div>

                <table class="pt-terms-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Term</th>
                            <th>Invoice #</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Paid Date</th>
                            <th>Invoice</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $terms as $t ) : ?>
                        <tr class="pt-row-<?php echo esc_attr( $t->status ); ?>">
                            <td><?php echo $t->term_order; ?></td>
                            <td><?php echo esc_html( $t->term_name ); ?></td>
                            <td style="font-family:monospace;font-size:11px;">
                                <?php echo esc_html( $t->invoice_number ); ?>
                            </td>
                            <td>$<?php echo number_format( $t->amount, 2 ); ?></td>
                            <td>
                                <span class="pt-badge pt-badge-<?php echo esc_attr( $t->status ); ?>">
                                    <?php echo ucfirst( $t->status ); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $t->paid_date
                                    ? date( 'd M Y', strtotime( $t->paid_date ) )
                                    : '—'; ?>
                            </td>
                            <td>
                                <?php if ( $t->pdf_url ) : ?>
                                <a href="<?php echo esc_url( $t->pdf_url ); ?>"
                                   target="_blank" class="pt-pdf-link">📄 View PDF</a>
                                <?php else : ?>—<?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $t->status === 'pending' ) : ?>
                                <button class="button button-small pt-paid-btn"
                                        data-term-id="<?php echo $t->id; ?>"
                                        data-term-name="<?php echo esc_attr( $t->term_name ); ?>"
                                        data-amount="<?php echo number_format( $t->amount, 2 ); ?>">
                                    💰 Payment Received
                                </button>
                                <?php elseif ( $t->status === 'locked' ) : ?>
                                <span style="color:#999;font-size:12px;">🔒 Locked</span>
                                <?php else : ?>
                                <span style="color:#27ae60;font-size:12px;">✅ Paid</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align:right;font-weight:600;
                                padding:8px 12px;">Totals</td>
                            <td colspan="5" style="padding:8px 12px;">
                                <span style="color:#27ae60;font-weight:700;">
                                    Paid: $<?php echo number_format( $total_paid, 2 ); ?>
                                </span>
                                &nbsp;&nbsp;
                                <span style="color:#e67e22;font-weight:700;">
                                    Remaining: $<?php echo number_format( $total_remaining, 2 ); ?>
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php
    }
}