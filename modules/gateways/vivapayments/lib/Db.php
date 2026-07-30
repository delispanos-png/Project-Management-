<?php
/**
 * Viva.com — αποθήκευση παραγγελιών, OAuth tokens και ιστορικού.
 *
 * Κρατάμε δικό μας πίνακα παραγγελιών ώστε:
 *  - να ξέρουμε σε ποιο τιμολόγιο αντιστοιχεί κάθε orderCode (το webhook στέλνει
 *    μόνο orderCode/transactionId),
 *  - να μη διπλοχρεώσουμε αν έρθουν και το redirect και το webhook.
 */

namespace CloudOn\Viva;

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

class Db
{
    const T_ORDERS = 'mod_viva_orders';
    const T_TOKENS = 'mod_viva_tokens';
    const T_LOG    = 'mod_viva_log';

    private static $ready = false;

    public static function install()
    {
        if (self::$ready) {
            return;
        }
        $sm = Capsule::schema();

        if (!$sm->hasTable(self::T_ORDERS)) {
            Capsule::statement('CREATE TABLE `' . self::T_ORDERS . '` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_code` VARCHAR(32) NOT NULL,
                `invoice_id` INT NOT NULL DEFAULT 0,
                `client_id` INT NOT NULL DEFAULT 0,
                `amount_cents` INT NOT NULL DEFAULT 0,
                `currency` VARCHAR(8) NOT NULL DEFAULT "EUR",
                `status` VARCHAR(16) NOT NULL DEFAULT "pending",
                `transaction_id` VARCHAR(64) NOT NULL DEFAULT "",
                `demo` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `order_code` (`order_code`),
                KEY `invoice_id` (`invoice_id`),
                KEY `transaction_id` (`transaction_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        }

        if (!$sm->hasTable(self::T_TOKENS)) {
            Capsule::statement('CREATE TABLE `' . self::T_TOKENS . '` (
                `k` VARCHAR(96) NOT NULL,
                `token` TEXT NOT NULL,
                `expires_at` DATETIME NOT NULL,
                PRIMARY KEY (`k`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        }

        if (!$sm->hasTable(self::T_LOG)) {
            Capsule::statement('CREATE TABLE `' . self::T_LOG . '` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `created_at` DATETIME NOT NULL,
                `kind` VARCHAR(32) NOT NULL DEFAULT "",
                `invoice_id` INT NOT NULL DEFAULT 0,
                `order_code` VARCHAR(32) NOT NULL DEFAULT "",
                `message` TEXT NULL,
                PRIMARY KEY (`id`),
                KEY `created_at` (`created_at`),
                KEY `invoice_id` (`invoice_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        }

        self::$ready = true;
    }

    private static function now()
    {
        return date('Y-m-d H:i:s');
    }

    /* ------------------------------ παραγγελίες ----------------------- */

    public static function orderCreate($orderCode, $invoiceId, $clientId, $amountCents, $currency, $demo)
    {
        self::install();
        Capsule::table(self::T_ORDERS)->insert([
            'order_code'   => (string) $orderCode,
            'invoice_id'   => (int) $invoiceId,
            'client_id'    => (int) $clientId,
            'amount_cents' => (int) $amountCents,
            'currency'     => (string) $currency,
            'status'       => 'pending',
            'demo'         => $demo ? 1 : 0,
            'created_at'   => self::now(),
            'updated_at'   => self::now(),
        ]);
    }

    public static function orderByCode($orderCode)
    {
        self::install();
        $r = Capsule::table(self::T_ORDERS)->where('order_code', (string) $orderCode)->first();
        return $r ? (array) $r : null;
    }

    /**
     * Κλειδώνει την παραγγελία ως πληρωμένη — μόνο ο πρώτος που θα το πετύχει
     * παίρνει true. Έτσι το redirect και το webhook δεν διπλοπερνούν την πληρωμή.
     */
    public static function orderClaim($orderCode, $transactionId)
    {
        self::install();
        $n = Capsule::table(self::T_ORDERS)
            ->where('order_code', (string) $orderCode)
            ->where('status', 'pending')
            ->update([
                'status'         => 'paid',
                'transaction_id' => (string) $transactionId,
                'updated_at'     => self::now(),
            ]);
        return $n > 0;
    }

    public static function orderMark($orderCode, $status, $transactionId = null)
    {
        self::install();
        $data = ['status' => $status, 'updated_at' => self::now()];
        if ($transactionId !== null) {
            $data['transaction_id'] = (string) $transactionId;
        }
        Capsule::table(self::T_ORDERS)->where('order_code', (string) $orderCode)->update($data);
    }

    /* -------------------------------- tokens -------------------------- */

    public static function tokenGet($key)
    {
        self::install();
        $r = Capsule::table(self::T_TOKENS)->where('k', $key)->first();
        if (!$r || strtotime($r->expires_at) <= time()) {
            return null;
        }
        return $r->token;
    }

    public static function tokenPut($key, $token, $ttlSeconds)
    {
        self::install();
        $row = ['token' => $token, 'expires_at' => date('Y-m-d H:i:s', time() + (int) $ttlSeconds)];
        $exists = Capsule::table(self::T_TOKENS)->where('k', $key)->exists();
        if ($exists) {
            Capsule::table(self::T_TOKENS)->where('k', $key)->update($row);
        } else {
            Capsule::table(self::T_TOKENS)->insert($row + ['k' => $key]);
        }
    }

    /* --------------------------------- log ---------------------------- */

    public static function log($kind, $message, $invoiceId = 0, $orderCode = '')
    {
        try {
            self::install();
            Capsule::table(self::T_LOG)->insert([
                'created_at' => self::now(),
                'kind'       => substr((string) $kind, 0, 32),
                'invoice_id' => (int) $invoiceId,
                'order_code' => substr((string) $orderCode, 0, 32),
                // Η βάση είναι utf8mb3: κρατάμε απλό κείμενο, χωρίς emoji.
                'message'    => (string) $message,
            ]);
            // Κράτα το ιστορικό λογικό σε μέγεθος.
            if (mt_rand(1, 50) === 1) {
                Capsule::table(self::T_LOG)
                    ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-120 days')))
                    ->delete();
            }
        } catch (\Throwable $e) {
            // Το log δεν πρέπει ποτέ να ρίξει πληρωμή.
        }
    }
}
