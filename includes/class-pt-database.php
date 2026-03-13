<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PT_Database {

    const DB_VERSION = '1.1.0'; // bumped: added locked + invoice_sent statuses

    // ── Create both tables ────────────────────────────────────────────────────
    public static function create_tables() {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // wp_pt_invoices
        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pt_invoices (
            id              BIGINT(20)      NOT NULL AUTO_INCREMENT,
            enquiry_id      BIGINT(20)      NOT NULL,
            user_id         BIGINT(20)      NOT NULL DEFAULT 0,
            invoice_number  VARCHAR(50)     NOT NULL DEFAULT '',
            payment_type    VARCHAR(20)     NOT NULL DEFAULT 'weekly',
            total_amount    DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
            terms_count     INT(11)         NOT NULL DEFAULT 0,
            status          VARCHAR(20)     NOT NULL DEFAULT 'active',
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY enquiry_id (enquiry_id),
            KEY status (status)
        ) $c;" );

        /*
         * wp_pt_invoice_terms
         *
         * status values:
         *   locked        → not yet reachable (terms 2, 3 … on create)
         *   unpaid        → current active term (Send Invoice + Mark Paid enabled)
         *   invoice_sent  → invoice emailed to customer, awaiting payment
         *   paid          → payment confirmed, receipt sent
         */
        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pt_invoice_terms (
            id              BIGINT(20)      NOT NULL AUTO_INCREMENT,
            invoice_id      BIGINT(20)      NOT NULL,
            term_number     INT(11)         NOT NULL DEFAULT 1,
            term_name       VARCHAR(255)    NOT NULL DEFAULT '',
            amount          DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
            due_date        DATE            DEFAULT NULL,
            status          VARCHAR(20)     NOT NULL DEFAULT 'locked',
            invoice_sent_at DATETIME        DEFAULT NULL,
            paid_at         DATETIME        DEFAULT NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id),
            KEY status (status)
        ) $c;" );

        update_option( 'pt_db_version', self::DB_VERSION );
    }

    // ── Safety net on plugins_loaded ──────────────────────────────────────────
    public static function ensure_tables() {
        if ( get_option( 'pt_db_version' ) !== self::DB_VERSION ) {
            self::create_tables();
            // Add invoice_sent_at column to existing installs
            global $wpdb;
            $col = $wpdb->get_results(
                "SHOW COLUMNS FROM {$wpdb->prefix}pt_invoice_terms LIKE 'invoice_sent_at'"
            );
            if ( empty( $col ) ) {
                $wpdb->query(
                    "ALTER TABLE {$wpdb->prefix}pt_invoice_terms
                     ADD COLUMN invoice_sent_at DATETIME DEFAULT NULL AFTER status"
                );
            }
        }
    }
}