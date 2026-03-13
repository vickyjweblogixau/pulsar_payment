<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Handle Save ───────────────────────────────────────────────────────────────
if ( isset( $_POST['pt_save_settings'] ) && check_admin_referer( 'pt_save_settings' ) ) {
    $fields = [
        'pt_company_name',
        'pt_company_website',
        'pt_company_phone',
        'pt_company_email',
        'pt_company_tagline',
        'pt_company_logo_url',
        'pt_company_logo_id',
        'pt_bank_account_name',
        'pt_bank_name',
        'pt_bank_bsb',
        'pt_bank_account_number',
        'pt_invoice_notes',
    ];
    foreach ( $fields as $f ) {
        update_option( $f, sanitize_text_field( $_POST[ $f ] ?? '' ) );
    }
    // Textarea fields
    update_option( 'pt_invoice_notes', sanitize_textarea_field( $_POST['pt_invoice_notes'] ?? '' ) );
    echo '<div class="notice notice-success is-dismissible"><p>✅ Settings saved successfully.</p></div>';
}

// ── Current values ────────────────────────────────────────────────────────────
$co_name     = get_option( 'pt_company_name',       get_bloginfo('name') );
$co_website  = get_option( 'pt_company_website',    home_url() );
$co_phone    = get_option( 'pt_company_phone',      '' );
$co_email    = get_option( 'pt_company_email',      get_option('admin_email') );
$co_tagline  = get_option( 'pt_company_tagline',    '' );
$co_logo_url = get_option( 'pt_company_logo_url',   '' );
$co_logo_id  = get_option( 'pt_company_logo_id',    '' );
$bk_acc_name = get_option( 'pt_bank_account_name',  '' );
$bk_name     = get_option( 'pt_bank_name',          '' );
$bk_bsb      = get_option( 'pt_bank_bsb',           '' );
$bk_acc_no   = get_option( 'pt_bank_account_number','' );
$inv_notes   = get_option( 'pt_invoice_notes',      'Thank you for your business' );
?>
<div class="wrap" style="max-width:820px;">

    <h1 style="display:flex;align-items:center;gap:10px;">
        <span class="dashicons dashicons-admin-settings" style="font-size:28px;color:#4a7c59;"></span>
        Invoice Settings
    </h1>

    <form method="post">
        <?php wp_nonce_field( 'pt_save_settings' ); ?>

        <!-- Company Details -->
        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:25px;margin-bottom:20px;">
            <h2 style="margin-top:0;padding-bottom:12px;border-bottom:1px solid #eee;">
                🏢 Company Details
            </h2>
            <table class="form-table">
                <tr>
                    <th><label>Company Logo</label></th>
                    <td>
                        <div style="display:flex;align-items:center;gap:15px;">
                            <div id="pt-logo-preview" style="width:160px;height:60px;border:1px dashed #ccc;border-radius:6px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#f9f9f9;">
                                <?php if ( $co_logo_url ) : ?>
                                    <img src="<?php echo esc_url( $co_logo_url ); ?>" style="max-width:100%;max-height:100%;object-fit:contain;" />
                                <?php else : ?>
                                    <span style="font-size:11px;color:#aaa;">No logo</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <input type="hidden" name="pt_company_logo_url" id="pt_company_logo_url" value="<?php echo esc_attr( $co_logo_url ); ?>" />
                                <input type="hidden" name="pt_company_logo_id"  id="pt_company_logo_id"  value="<?php echo esc_attr( $co_logo_id ); ?>" />
                                <button type="button" id="pt-upload-logo" class="button">
                                    <span class="dashicons dashicons-upload" style="margin-top:3px;"></span> Upload Logo
                                </button>
                                <?php if ( $co_logo_url ) : ?>
                                <button type="button" id="pt-remove-logo" class="button" style="margin-left:8px;color:#c00;">
                                    Remove
                                </button>
                                <?php endif; ?>
                                <p class="description">Recommended: PNG, 300×80px or similar wide format.</p>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="pt_company_name">Company Name</label></th>
                    <td><input type="text" id="pt_company_name" name="pt_company_name"
                               value="<?php echo esc_attr( $co_name ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="pt_company_tagline">Tagline</label></th>
                    <td>
                        <input type="text" id="pt_company_tagline" name="pt_company_tagline"
                               value="<?php echo esc_attr( $co_tagline ); ?>" class="regular-text" />
                        <p class="description">e.g. WEB &amp; DIGITAL MARKETING EXPERTS</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="pt_company_website">Website</label></th>
                    <td><input type="text" id="pt_company_website" name="pt_company_website"
                               value="<?php echo esc_attr( $co_website ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="pt_company_phone">Phone</label></th>
                    <td><input type="text" id="pt_company_phone" name="pt_company_phone"
                               value="<?php echo esc_attr( $co_phone ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="pt_company_email">Email</label></th>
                    <td><input type="email" id="pt_company_email" name="pt_company_email"
                               value="<?php echo esc_attr( $co_email ); ?>" class="regular-text" /></td>
                </tr>
            </table>
        </div>

        <!-- Bank / Payment Details -->
        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:25px;margin-bottom:20px;">
            <h2 style="margin-top:0;padding-bottom:12px;border-bottom:1px solid #eee;">
                🏦 Bank / Payment Info
            </h2>
            <table class="form-table">
                <tr>
                    <th><label for="pt_bank_account_name">Account Name</label></th>
                    <td><input type="text" id="pt_bank_account_name" name="pt_bank_account_name"
                               value="<?php echo esc_attr( $bk_acc_name ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="pt_bank_name">Bank Name</label></th>
                    <td><input type="text" id="pt_bank_name" name="pt_bank_name"
                               value="<?php echo esc_attr( $bk_name ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="pt_bank_bsb">BSB Number</label></th>
                    <td><input type="text" id="pt_bank_bsb" name="pt_bank_bsb"
                               value="<?php echo esc_attr( $bk_bsb ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="pt_bank_account_number">Account Number</label></th>
                    <td><input type="text" id="pt_bank_account_number" name="pt_bank_account_number"
                               value="<?php echo esc_attr( $bk_acc_no ); ?>" class="regular-text" /></td>
                </tr>
            </table>
        </div>

        <!-- Invoice Notes -->
        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:25px;margin-bottom:20px;">
            <h2 style="margin-top:0;padding-bottom:12px;border-bottom:1px solid #eee;">
                📝 Invoice Footer Note
            </h2>
            <table class="form-table">
                <tr>
                    <th><label for="pt_invoice_notes">Footer Text</label></th>
                    <td>
                        <textarea id="pt_invoice_notes" name="pt_invoice_notes"
                                  rows="3" class="large-text"><?php echo esc_textarea( $inv_notes ); ?></textarea>
                        <p class="description">Shown at the bottom of every printed invoice. e.g. "Thank you for your business"</p>
                    </td>
                </tr>
            </table>
        </div>

        <p>
            <button type="submit" name="pt_save_settings" class="button button-primary button-large"
                    style="background:#4a7c59;border-color:#4a7c59;">
                💾 Save Settings
            </button>
        </p>
    </form>
</div>

<script>
(function($){
    // ── Media uploader for logo ───────────────────────────────────────────────
    var mediaFrame;
    $('#pt-upload-logo').on('click', function(e){
        e.preventDefault();
        if (mediaFrame) { mediaFrame.open(); return; }
        mediaFrame = wp.media({ title: 'Select Company Logo', button: { text: 'Use this logo' }, multiple: false });
        mediaFrame.on('select', function(){
            var att = mediaFrame.state().get('selection').first().toJSON();
            $('#pt_company_logo_url').val( att.url );
            $('#pt_company_logo_id').val( att.id );
            $('#pt-logo-preview').html('<img src="'+att.url+'" style="max-width:100%;max-height:100%;object-fit:contain;">');
        });
        mediaFrame.open();
    });

    $('#pt-remove-logo').on('click', function(){
        $('#pt_company_logo_url').val('');
        $('#pt_company_logo_id').val('');
        $('#pt-logo-preview').html('<span style="font-size:11px;color:#aaa;">No logo</span>');
    });
})(jQuery);
</script>