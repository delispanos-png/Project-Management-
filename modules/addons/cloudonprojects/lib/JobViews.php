<?php
/**
 * Επισκεψιμότητα αγγελιών.
 *
 * Μετράει πόσοι είδαν κάθε θέση στη δημόσια σελίδα καριέρας, ανεξάρτητα από το
 * αν έκαναν αίτηση. Χωρίς αυτό δεν ξέρουμε αν μια θέση δεν φέρνει υποψηφίους
 * επειδή δεν τη βλέπει κανείς ή επειδή τη βλέπουν και δεν τους πείθει.
 *
 * ΙΔΙΩΤΙΚΟΤΗΤΑ: δεν αποθηκεύεται IP ούτε user agent. Κρατάμε μόνο ένα ανώνυμο
 * αποτύπωμα (md5 από IP + UA + ημερήσιο αλάτι) που αλλάζει κάθε μέρα, ώστε να
 * μετράμε μοναδικούς επισκέπτες χωρίς να ταυτοποιούμε πρόσωπα.
 */

namespace WHMCS\Module\Addon\CloudonProjects;

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

class JobViews
{
    const TABLE = 'mod_cpm_cv_job_views';

    /** Πόσες ημέρες κρατάμε αναλυτικά δεδομένα. */
    const RETENTION_DAYS = 400;

    private static $ready = false;

    public static function install()
    {
        if (self::$ready) {
            return;
        }
        if (!Capsule::schema()->hasTable(self::TABLE)) {
            Capsule::statement('CREATE TABLE `' . self::TABLE . '` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `job_id` INT NOT NULL DEFAULT 0,
                `day` DATE NOT NULL,
                `vhash` CHAR(32) NOT NULL,
                `views` INT NOT NULL DEFAULT 0,
                `forms` INT NOT NULL DEFAULT 0,
                `src` VARCHAR(80) NOT NULL DEFAULT "",
                `device` VARCHAR(10) NOT NULL DEFAULT "",
                `first_at` DATETIME NOT NULL,
                `last_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_visit` (`job_id`,`day`,`vhash`),
                KEY `job_day` (`job_id`,`day`),
                KEY `day` (`day`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        }
        self::$ready = true;
    }

    /** Ανώνυμο, ημερήσιο αποτύπωμα επισκέπτη. */
    private static function visitorHash()
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        // Το αλάτι αλλάζει καθημερινά: το αποτύπωμα δεν συνδέει επισκέψεις
        // διαφορετικών ημερών με το ίδιο πρόσωπο.
        return md5($ip . '|' . $ua . '|' . date('Y-m-d') . '|cnp-jobviews');
    }

    /** Από πού ήρθε — μόνο ο host, ποτέ ολόκληρο URL. */
    private static function source()
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref === '') {
            return 'απευθείας';
        }
        $host = parse_url($ref, PHP_URL_HOST);
        if (!$host) {
            return 'απευθείας';
        }
        $host = preg_replace('/^www\./i', '', $host);
        // Πλοήγηση μέσα στην ίδια σελίδα δεν είναι «πηγή».
        $self = preg_replace('/^www\./i', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        return strcasecmp($host, $self) === 0 ? 'εσωτερική' : mb_substr($host, 0, 80);
    }

    private static function device()
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (preg_match('/iPad|Tablet/i', $ua)) {
            return 'tablet';
        }
        return preg_match('/Mobile|Android|iPhone/i', $ua) ? 'mobile' : 'desktop';
    }

    /**
     * Καταγράφει μια επίσκεψη.
     *
     * @param int    $jobId 0 = η σελίδα καριέρας συνολικά, >0 = συγκεκριμένη θέση
     * @param string $what  'view' (είδε την αγγελία) ή 'form' (άνοιξε τη φόρμα)
     */
    public static function hit($jobId, $what = 'view')
    {
        // Τα bots θα φούσκωναν τα νούμερα χωρίς να σημαίνουν τίποτα.
        if (self::isBot()) {
            return;
        }

        try {
            self::install();

            $now  = date('Y-m-d H:i:s');
            $day  = date('Y-m-d');
            $hash = self::visitorHash();
            $col  = $what === 'form' ? 'forms' : 'views';

            $existing = Capsule::table(self::TABLE)
                ->where('job_id', (int) $jobId)->where('day', $day)->where('vhash', $hash)->first();

            if ($existing) {
                Capsule::table(self::TABLE)->where('id', $existing->id)->update([
                    $col      => (int) $existing->$col + 1,
                    'last_at' => $now,
                ]);
            } else {
                Capsule::table(self::TABLE)->insert([
                    'job_id'   => (int) $jobId,
                    'day'      => $day,
                    'vhash'    => $hash,
                    'views'    => $col === 'views' ? 1 : 0,
                    'forms'    => $col === 'forms' ? 1 : 0,
                    'src'      => self::source(),
                    'device'   => self::device(),
                    'first_at' => $now,
                    'last_at'  => $now,
                ]);
            }

            // Περιστασιακό καθάρισμα, χωρίς δικό του cron.
            if (mt_rand(1, 500) === 1) {
                Capsule::table(self::TABLE)
                    ->where('day', '<', date('Y-m-d', strtotime('-' . self::RETENTION_DAYS . ' days')))
                    ->delete();
            }
        } catch (\Throwable $e) {
            // Η μέτρηση δεν επιτρέπεται ποτέ να ρίξει τη δημόσια σελίδα.
        }
    }

    private static function isBot()
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($ua === '') {
            return true;
        }
        return (bool) preg_match(
            '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headless|monitor|uptime|curl|wget|python-requests|scrapy|semrush|ahrefs|petal|yandex|applebot/i',
            $ua
        );
    }

    /**
     * Συγκεντρωτικά ανά θέση για τις τελευταίες $days ημέρες.
     *
     * Επιστρέφει και τις αιτήσεις, ώστε να φαίνεται το ποσοστό μετατροπής —
     * το νούμερο που δείχνει αν η αγγελία πείθει.
     */
    public static function stats($days = 30)
    {
        self::install();

        $from = date('Y-m-d', strtotime('-' . max(1, (int) $days) . ' days'));

        $rows = Capsule::table(self::TABLE)
            ->selectRaw('job_id,
                         SUM(views) AS views,
                         SUM(forms) AS forms,
                         COUNT(DISTINCT vhash) AS uniques,
                         MAX(last_at) AS last_at')
            ->where('day', '>=', $from)
            ->groupBy('job_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->job_id] = [
                'views'   => (int) $r->views,
                'forms'   => (int) $r->forms,
                'uniques' => (int) $r->uniques,
                'last_at' => $r->last_at,
            ];
        }
        return $out;
    }

    /** Ημερήσια σειρά προβολών μιας θέσης — για το μικρογράφημα. */
    public static function daily($jobId, $days = 30)
    {
        self::install();

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $series[date('Y-m-d', strtotime("-$i days"))] = 0;
        }

        $rows = Capsule::table(self::TABLE)
            ->selectRaw('day, SUM(views) AS v')
            ->where('job_id', (int) $jobId)
            ->where('day', '>=', date('Y-m-d', strtotime('-' . $days . ' days')))
            ->groupBy('day')->get();

        foreach ($rows as $r) {
            if (isset($series[$r->day])) {
                $series[$r->day] = (int) $r->v;
            }
        }
        return $series;
    }

    /** Κατανομή πηγών και συσκευών για τις τελευταίες $days ημέρες. */
    public static function breakdown($days = 30)
    {
        self::install();
        $from = date('Y-m-d', strtotime('-' . max(1, (int) $days) . ' days'));

        $src = [];
        foreach (Capsule::table(self::TABLE)->selectRaw('src, COUNT(DISTINCT vhash) n')
                     ->where('day', '>=', $from)->groupBy('src')->orderByRaw('n DESC')->limit(8)->get() as $r) {
            $src[] = ['name' => $r->src ?: 'απευθείας', 'n' => (int) $r->n];
        }

        $dev = [];
        foreach (Capsule::table(self::TABLE)->selectRaw('device, COUNT(DISTINCT vhash) n')
                     ->where('day', '>=', $from)->groupBy('device')->get() as $r) {
            $dev[] = ['name' => $r->device ?: '—', 'n' => (int) $r->n];
        }

        return ['sources' => $src, 'devices' => $dev];
    }
}
