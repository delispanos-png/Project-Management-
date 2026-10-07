<?php
/**
 * CloudOn Billing — πίνακες cob_*.
 * Επαναλήψιμο και ΠΡΟΣΘΕΤΙΚΟ: δημιουργεί ό,τι λείπει, δεν σβήνει ποτέ τίποτα.
 * Ποσά DECIMAL(12,2). Κάθε πίνακας με προέλευση από WHMCS κρατά whmcs_id (UNIQUE).
 */

namespace CloudOn\Billing;

final class Schema
{
    public static function tables()
    {
        $money = 'DECIMAL(12,2) NOT NULL DEFAULT 0';
        return [
            'cob_clients' => "
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                whmcs_id INT UNSIGNED NULL UNIQUE,
                uuid VARCHAR(64) NOT NULL DEFAULT '',
                kind VARCHAR(10) NOT NULL DEFAULT 'person',
                first_name VARCHAR(100) NOT NULL DEFAULT '',
                last_name VARCHAR(100) NOT NULL DEFAULT '',
                company VARCHAR(200) NOT NULL DEFAULT '',
                email VARCHAR(190) NOT NULL DEFAULT '',
                phone VARCHAR(60) NOT NULL DEFAULT '',
                address1 VARCHAR(200) NOT NULL DEFAULT '',
                address2 VARCHAR(200) NOT NULL DEFAULT '',
                city VARCHAR(100) NOT NULL DEFAULT '',
                state VARCHAR(100) NOT NULL DEFAULT '',
                postcode VARCHAR(20) NOT NULL DEFAULT '',
                country CHAR(2) NOT NULL DEFAULT '',
                vat_id VARCHAR(40) NOT NULL DEFAULT '',
                tax_office VARCHAR(100) NOT NULL DEFAULT '',
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                credit $money,
                tax_exempt TINYINT NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'Active',
                language VARCHAR(20) NOT NULL DEFAULT '',
                default_gateway VARCHAR(40) NOT NULL DEFAULT '',
                notes TEXT NULL,
                created_at DATETIME NULL,
                synced_at DATETIME NULL,
                KEY k_email (email)",
            'cob_products' => "
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                whmcs_id INT UNSIGNED NULL UNIQUE,
                group_name VARCHAR(150) NOT NULL DEFAULT '',
                name VARCHAR(200) NOT NULL DEFAULT '',
                type VARCHAR(20) NOT NULL DEFAULT 'other',
                module VARCHAR(40) NOT NULL DEFAULT '',
                pay_type VARCHAR(20) NOT NULL DEFAULT 'recurring',
                auto_setup VARCHAR(20) NOT NULL DEFAULT '',
                taxed TINYINT NOT NULL DEFAULT 0,
                hidden TINYINT NOT NULL DEFAULT 0,
                retired TINYINT NOT NULL DEFAULT 0,
                synced_at DATETIME NULL",
            'cob_product_prices' => "
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT UNSIGNED NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                cycle VARCHAR(20) NOT NULL,
                setup_fee $money,
                price $money,
                UNIQUE KEY u_pc (product_id, currency, cycle)",
            'cob_services' => "
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                whmcs_id INT UNSIGNED NULL,
                source VARCHAR(10) NOT NULL DEFAULT 'service',
                client_id INT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NULL,
                parent_service_id INT UNSIGNED NULL,
                label VARCHAR(255) NOT NULL DEFAULT '',
                status VARCHAR(20) NOT NULL DEFAULT 'Pending',
                cycle VARCHAR(20) NOT NULL DEFAULT '',
                qty INT NOT NULL DEFAULT 1,
                first_amount $money,
                amount $money,
                setup_fee $money,
                reg_date DATE NULL,
                next_due DATE NULL,
                next_invoice DATE NULL,
                terminated_at DATE NULL,
                payment_method VARCHAR(40) NOT NULL DEFAULT '',
                module_ref VARCHAR(100) NOT NULL DEFAULT '',
                dedicated_ip VARCHAR(64) NOT NULL DEFAULT '',
                assigned_ips TEXT NULL,
                options TEXT NULL,
                notes TEXT NULL,
                synced_at DATETIME NULL,
                UNIQUE KEY u_src (source, whmcs_id),
                KEY k_client (client_id), KEY k_due (next_due)",
            'cob_invoices' => "
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                whmcs_id INT UNSIGNED NULL UNIQUE,
                client_id INT UNSIGNED NOT NULL,
                number VARCHAR(40) NOT NULL DEFAULT '',
                date DATE NULL,
                due_date DATE NULL,
                paid_at DATETIME NULL,
                cancelled_at DATETIME NULL,
                refunded_at DATETIME NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'Unpaid',
                subtotal $money,
                credit $money,
                tax $money,
                tax2 $money,
                total $money,
                tax_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
                tax_rate2 DECIMAL(6,3) NOT NULL DEFAULT 0,
                payment_method VARCHAR(40) NOT NULL DEFAULT '',
                notes TEXT NULL,
                synced_at DATETIME NULL,
                KEY k_client (client_id), KEY k_status (status)",
            'cob_invoice_items' => "
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                whmcs_id INT UNSIGNED NULL UNIQUE,
                invoice_id INT UNSIGNED NOT NULL,
                type VARCHAR(30) NOT NULL DEFAULT '',
                service_id INT UNSIGNED NULL,
                ref_whmcs INT UNSIGNED NULL,
                description TEXT NULL,
                amount $money,
                taxed TINYINT NOT NULL DEFAULT 0,
                due_date DATE NULL,
                KEY k_inv (invoice_id)",
            'cob_payments' => "
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                whmcs_id INT UNSIGNED NULL UNIQUE,
                client_id INT UNSIGNED NULL,
                invoice_id INT UNSIGNED NULL,
                gateway VARCHAR(40) NOT NULL DEFAULT '',
                date DATETIME NULL,
                description TEXT NULL,
                amount_in $money,
                fees $money,
                amount_out $money,
                trans_id VARCHAR(190) NOT NULL DEFAULT '',
                refund_of INT UNSIGNED NULL,
                KEY k_client (client_id), KEY k_inv (invoice_id)",
            'cob_credit_log' => "
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                whmcs_id INT UNSIGNED NULL UNIQUE,
                client_id INT UNSIGNED NOT NULL,
                date DATE NULL,
                description TEXT NULL,
                amount $money,
                ref_invoice_whmcs INT UNSIGNED NULL,
                KEY k_client (client_id)",
            'cob_sync_log' => "
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                started_at DATETIME NOT NULL,
                finished_at DATETIME NULL,
                mode VARCHAR(20) NOT NULL DEFAULT 'import',
                stats TEXT NULL",
        ];
    }

    /** Δημιουργεί όσους πίνακες λείπουν. Επιστρέφει τη λίστα όσων φτιάχτηκαν. */
    public static function migrate()
    {
        $made = [];
        foreach (self::tables() as $name => $cols) {
            $exists = Db::val('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$name]);
            if (!$exists) {
                Db::exec('CREATE TABLE `' . $name . '` (' . $cols . ') ENGINE=InnoDB DEFAULT CHARSET=utf8');
                $made[] = $name;
            }
        }
        return $made;
    }
}
