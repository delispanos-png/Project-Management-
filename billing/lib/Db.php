<?php
/**
 * CloudOn Billing — σύνδεση βάσης.
 *
 * Δικό του PDO, ΟΧΙ το Capsule/init.php του WHMCS: η πλατφόρμα πρέπει να τρέχει και όταν
 * το WHMCS δεν θα υπάρχει. Προς το παρόν διαβάζει τα στοιχεία από το configuration.php
 * (ίδια βάση, πίνακες cob_*)· στη Φάση 5 περνά σε δικό του αρχείο ρυθμίσεων.
 */

namespace CloudOn\Billing;

if (PHP_SAPI !== 'cli' && !defined('COB_WEB')) {
    http_response_code(404);
    exit;
}

final class Db
{
    private static $pdo = null;

    public static function pdo()
    {
        if (self::$pdo) {
            return self::$pdo;
        }
        $cfg = dirname(__DIR__, 2) . '/configuration.php';
        $db_host = $db_name = $db_username = $db_password = $db_port = null;
        require $cfg;
        $dsn = 'mysql:host=' . $db_host . ($db_port ? ';port=' . $db_port : '') . ';dbname=' . $db_name . ';charset=utf8';
        self::$pdo = new \PDO($dsn, $db_username, $db_password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        return self::$pdo;
    }

    public static function all($sql, array $args = [])
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    }

    public static function one($sql, array $args = [])
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($args);
        $r = $st->fetch();
        return $r === false ? null : $r;
    }

    public static function val($sql, array $args = [])
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($args);
        $r = $st->fetchColumn();
        return $r === false ? null : $r;
    }

    public static function exec($sql, array $args = [])
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($args);
        return $st->rowCount();
    }

    /**
     * INSERT … ON DUPLICATE KEY UPDATE ανά whmcs_id (ή άλλο unique). Επιστρέφει το id της γραμμής.
     */
    public static function upsert($table, array $row, $key = 'whmcs_id')
    {
        $cols = array_keys($row);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES ('
            . implode(',', array_fill(0, count($cols), '?')) . ') ON DUPLICATE KEY UPDATE '
            . implode(',', array_map(function ($c) { return '`' . $c . '`=VALUES(`' . $c . '`)'; },
                array_filter($cols, function ($c) use ($key) { return $c !== $key; })))
            . ', `id`=LAST_INSERT_ID(`id`)';
        self::exec($sql, array_values($row));
        return (int) self::pdo()->lastInsertId();
    }
}
