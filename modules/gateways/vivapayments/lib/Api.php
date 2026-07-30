<?php
/**
 * Viva.com (πρώην Viva Wallet) — API client για το Smart Checkout.
 *
 * Το Smart Checkout είναι μία φιλοξενούμενη σελίδα πληρωμής της Viva στην οποία
 * εμφανίζονται ΟΛΕΣ οι μέθοδοι που έχει ενεργές ο έμπορος: κάρτες, Apple Pay,
 * Google Pay, IRIS, δόσεις κ.λπ. Δεν χρειάζεται ξεχωριστός κώδικας ανά μέθοδο —
 * η ενεργοποίηση/απενεργοποίηση γίνεται από το portal της Viva.
 *
 * Ροή:
 *   1. OAuth2 client_credentials  → access token
 *   2. POST /checkout/v2/orders   → orderCode (16ψήφιο)
 *   3. redirect στο /web/checkout?ref=orderCode
 *   4. επιστροφή με ?t=transactionId&s=orderCode
 *   5. GET /checkout/v2/transactions/{t} → επαλήθευση (statusId = 'F')
 */

namespace CloudOn\Viva;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

class ApiException extends \Exception
{
}

class Api
{
    /** Παραγωγή. */
    const HOSTS_LIVE = [
        'accounts' => 'https://accounts.vivapayments.com',
        'api'      => 'https://api.vivapayments.com',
        'checkout' => 'https://www.vivapayments.com',
    ];

    /** Δοκιμαστικό περιβάλλον (demo). */
    const HOSTS_DEMO = [
        'accounts' => 'https://demo-accounts.vivapayments.com',
        'api'      => 'https://demo-api.vivapayments.com',
        'checkout' => 'https://demo.vivapayments.com',
    ];

    /** Επιτυχής/ολοκληρωμένη συναλλαγή. */
    const STATUS_PAID = 'F';

    /** Κωδικοί γεγονότων webhook. */
    const EVENT_PAYMENT_CREATED  = 1796;
    const EVENT_REVERSAL_CREATED = 1797;
    const EVENT_PAYMENT_FAILED   = 1798;

    /** ISO 4217 αριθμητικοί κωδικοί που δεχόμαστε. */
    const CURRENCY_EUR = 978;

    private $clientId;
    private $clientSecret;
    private $merchantId;
    private $apiKey;
    private $demo;

    public function __construct(array $cfg)
    {
        $this->clientId     = trim((string) ($cfg['clientId'] ?? ''));
        $this->clientSecret = trim((string) ($cfg['clientSecret'] ?? ''));
        $this->merchantId   = trim((string) ($cfg['merchantId'] ?? ''));
        $this->apiKey       = trim((string) ($cfg['apiKey'] ?? ''));
        $this->demo         = !empty($cfg['demo']);
    }

    /** Δημιουργεί client από τα params ενός WHMCS gateway. */
    public static function fromGatewayParams(array $params)
    {
        return new self([
            'clientId'     => $params['clientId'] ?? '',
            'clientSecret' => $params['clientSecret'] ?? '',
            'merchantId'   => $params['merchantId'] ?? '',
            'apiKey'       => $params['apiKey'] ?? '',
            'demo'         => ($params['environment'] ?? '') === 'Demo (δοκιμές)',
        ]);
    }

    public function isDemo()
    {
        return $this->demo;
    }

    private function host($which)
    {
        $h = $this->demo ? self::HOSTS_DEMO : self::HOSTS_LIVE;
        return $h[$which];
    }

    /** Η διεύθυνση στην οποία στέλνουμε τον πελάτη για να πληρώσει. */
    public function checkoutUrl($orderCode, $color = '')
    {
        $url = $this->host('checkout') . '/web/checkout?ref=' . rawurlencode((string) $orderCode);
        $color = ltrim(trim((string) $color), '#');
        if ($color !== '' && preg_match('/^[0-9a-fA-F]{6}$/', $color)) {
            $url .= '&color=' . $color;
        }
        return $url;
    }

    /* ------------------------------------------------------------------ */
    /* OAuth2                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Επιστρέφει access token (client_credentials).
     * Το token κρατάει 1 ώρα· το αποθηκεύουμε στη βάση για να μη ζητάμε νέο
     * σε κάθε κλήση.
     */
    /**
     * Δεν στέλνουμε παράμετρο scope: έτσι το token παίρνει όσα δικαιώματα έχει
     * το κλειδί στο portal της Viva (εκεί ορίζεται τι επιτρέπεται, όχι εδώ).
     * Ρητό scope που δεν έχει παραχωρηθεί απαντά invalid_scope και μπλοκάρει
     * όλη τη ροή — π.χ. το …:api:messages δεν δίνεται στα Smart Checkout κλειδιά.
     */
    public function accessToken($scope = '')
    {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new ApiException('Λείπουν τα Client ID / Client Secret του Smart Checkout.');
        }

        // Στο κλειδί μπαίνει και το secret: αν αλλάξουν τα credentials, το παλιό
        // token δεν επαναχρησιμοποιείται κατά λάθος μέχρι να λήξει.
        $key = ($this->demo ? 'demo:' : 'live:') . substr(sha1($scope), 0, 8) . ':'
            . substr(sha1($this->clientId . "\0" . $this->clientSecret), 0, 24);
        $cached = Db::tokenGet($key);
        if ($cached !== null) {
            return $cached;
        }

        [$code, $body] = $this->raw(
            'POST',
            $this->host('accounts') . '/connect/token',
            http_build_query(array_filter(['grant_type' => 'client_credentials', 'scope' => $scope])),
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            ]
        );

        $json = json_decode($body, true);
        if ($code !== 200 || empty($json['access_token'])) {
            throw new ApiException(self::describe($code, $body, 'Αποτυχία πιστοποίησης OAuth2 στη Viva'));
        }

        // Λήγει σε expires_in δευτερόλεπτα· κρατάμε περιθώριο 60".
        $ttl = max(60, ((int) ($json['expires_in'] ?? 3600)) - 60);
        Db::tokenPut($key, $json['access_token'], $ttl);

        return $json['access_token'];
    }

    /* ------------------------------------------------------------------ */
    /* Παραγγελίες / συναλλαγές                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Δημιουργεί payment order και επιστρέφει το orderCode.
     *
     * @param int   $amountCents Ποσό σε λεπτά (η Viva δουλεύει σε minor units).
     * @param array $opt         customerTrns, merchantTrns, customer[], sourceCode,
     *                           paymentTimeout, maxInstallments, disableWallet,
     *                           disableCash, tags[]
     */
    public function createOrder($amountCents, array $opt = [])
    {
        $amountCents = (int) $amountCents;
        if ($amountCents <= 0) {
            throw new ApiException('Μη έγκυρο ποσό πληρωμής.');
        }

        $payload = array_filter([
            'amount'              => $amountCents,
            'customerTrns'        => self::cut($opt['customerTrns'] ?? '', 2048),
            'merchantTrns'        => self::cut($opt['merchantTrns'] ?? '', 2048),
            'customer'            => $opt['customer'] ?? null,
            'paymentTimeout'      => isset($opt['paymentTimeout']) ? (int) $opt['paymentTimeout'] : 1800,
            'preauth'             => false,
            'allowRecurring'      => false,
            'maxInstallments'     => isset($opt['maxInstallments']) ? (int) $opt['maxInstallments'] : 0,
            'paymentNotification' => true,
            'disableCash'         => !empty($opt['disableCash']),
            'disableWallet'       => !empty($opt['disableWallet']),
            'sourceCode'          => $opt['sourceCode'] ?? null,
            'tags'                => $opt['tags'] ?? null,
        ], function ($v) {
            return $v !== null && $v !== '';
        });

        // Τα boolean false πρέπει να επιβιώσουν του array_filter.
        $payload['preauth']        = false;
        $payload['allowRecurring'] = false;
        $payload['disableCash']    = !empty($opt['disableCash']);
        $payload['disableWallet']  = !empty($opt['disableWallet']);

        [$code, $body] = $this->raw(
            'POST',
            $this->host('api') . '/checkout/v2/orders',
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken(),
            ]
        );

        // Τα orderCode είναι 16ψήφια — τα διαβάζουμε ως string για να μη χαθεί ακρίβεια.
        $json = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);
        $orderCode = $json['orderCode'] ?? $json['OrderCode'] ?? null;
        if ($code < 200 || $code >= 300 || !$orderCode) {
            throw new ApiException(self::describe($code, $body, 'Αποτυχία δημιουργίας παραγγελίας πληρωμής'));
        }

        return (string) $orderCode;
    }

    /**
     * Ανακτά συναλλαγή. Δοκιμάζει OAuth και, αν αποτύχει, Basic (Merchant ID /
     * API key) — η Viva δέχεται και τα δύο σε αυτό το endpoint.
     */
    public function getTransaction($transactionId)
    {
        $url = $this->host('api') . '/checkout/v2/transactions/' . rawurlencode((string) $transactionId);

        try {
            [$code, $body] = $this->raw('GET', $url, null, [
                'Authorization: Bearer ' . $this->accessToken(),
            ]);
        } catch (ApiException $e) {
            $code = 0;
            $body = '';
        }

        if ($code < 200 || $code >= 300) {
            if ($this->merchantId === '' || $this->apiKey === '') {
                throw new ApiException(self::describe($code, $body, 'Αποτυχία ανάκτησης συναλλαγής'));
            }
            [$code, $body] = $this->raw('GET', $url, null, [
                'Authorization: Basic ' . base64_encode($this->merchantId . ':' . $this->apiKey),
            ]);
        }

        $json = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);
        if ($code < 200 || $code >= 300 || !is_array($json)) {
            throw new ApiException(self::describe($code, $body, 'Αποτυχία ανάκτησης συναλλαγής'));
        }

        return self::lowerKeys($json);
    }

    /**
     * Επιστροφή χρημάτων (ολική ή μερική).
     *
     * @param int $amountCents Ποσό σε λεπτά.
     */
    public function refund($transactionId, $amountCents, $sourceCode = '')
    {
        if ($this->merchantId === '' || $this->apiKey === '') {
            throw new ApiException('Για επιστροφές χρειάζονται Merchant ID και API key από το portal της Viva.');
        }

        $qs = ['amount' => (int) $amountCents];
        if ($sourceCode !== '') {
            $qs['sourceCode'] = $sourceCode;
        }

        [$code, $body] = $this->raw(
            'DELETE',
            $this->host('api') . '/api/transactions/' . rawurlencode((string) $transactionId) . '?' . http_build_query($qs),
            null,
            ['Authorization: Basic ' . base64_encode($this->merchantId . ':' . $this->apiKey)]
        );

        $json = self::lowerKeys((array) json_decode($body, true, 512, JSON_BIGINT_AS_STRING));
        $ok = ($code >= 200 && $code < 300)
            && (($json['errorcode'] ?? 0) == 0)
            && (!isset($json['success']) || $json['success']);

        if (!$ok) {
            throw new ApiException(self::describe($code, $body, 'Αποτυχία επιστροφής χρημάτων'));
        }

        return $json;
    }

    /* ------------------------------------------------------------------ */
    /* Webhooks                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Το κλειδί επαλήθευσης που ζητά η Viva με GET πριν ενεργοποιήσει webhook.
     * Επιστρέφει το ωμό JSON σώμα ώστε να το στείλουμε αυτούσιο.
     */
    public function webhookVerificationBody()
    {
        $url = $this->host('api') . '/api/messages/config/token';
        $oauthError = '';

        try {
            [$code, $body] = $this->raw('GET', $url, null, [
                'Authorization: Bearer ' . $this->accessToken(),
            ]);
        } catch (ApiException $e) {
            $code = 0;
            $body = '';
            $oauthError = $e->getMessage();
        }

        if (($code < 200 || $code >= 300) && ($this->merchantId === '' || $this->apiKey === '') && $oauthError !== '') {
            throw new ApiException($oauthError);
        }

        // Με Basic auth το endpoint ζει στον παλιό host (www./demo.), όχι στο api.
        if (($code < 200 || $code >= 300) && $this->merchantId !== '' && $this->apiKey !== '') {
            [$code, $body] = $this->raw(
                'GET',
                $this->host('checkout') . '/api/messages/config/token',
                null,
                ['Authorization: Basic ' . base64_encode($this->merchantId . ':' . $this->apiKey)]
            );
        }

        if ($code < 200 || $code >= 300) {
            throw new ApiException(self::describe($code, $body, 'Αποτυχία λήψης κλειδιού webhook'));
        }

        return $body;
    }

    /* ------------------------------------------------------------------ */
    /* Βοηθητικά                                                           */
    /* ------------------------------------------------------------------ */

    /** Εκτελεί HTTP κλήση· επιστρέφει [http_code, body]. */
    private function raw($method, $url, $body, array $headers)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => array_merge($headers, ['Accept: application/json']),
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'CloudOn-WHMCS-Viva/1.0',
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $out = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($out === false) {
            throw new ApiException('Αδυναμία επικοινωνίας με τη Viva: ' . $err);
        }
        return [$code, (string) $out];
    }

    /** Φτιάχνει κατανοητό ελληνικό μήνυμα από απάντηση σφάλματος. */
    private static function describe($code, $body, $prefix)
    {
        $json = json_decode((string) $body, true);
        $detail = '';
        if (is_array($json)) {
            foreach (['message', 'Message', 'error_description', 'detail', 'ErrorText', 'errorText'] as $k) {
                if (!empty($json[$k])) {
                    $detail = (string) $json[$k];
                    break;
                }
            }
        }
        if ($detail === '') {
            $detail = trim(substr((string) $body, 0, 300));
        }

        $hint = '';
        if (stripos($detail, 'invalid_client') !== false) {
            $hint = ' — τα Client ID / Client Secret δεν αναγνωρίστηκαν. Έλεγξε ότι ανήκουν στο ίδιο περιβάλλον'
                . ' (Παραγωγή ή Demo) με αυτό που έχει επιλεγεί στις ρυθμίσεις.';
        } elseif ($code === 401 || $code === 403) {
            $hint = ' — έλεγξε Client ID/Secret και ότι το περιβάλλον (Παραγωγή/Demo) ταιριάζει με τα credentials.';
        } elseif ($code === 0) {
            $hint = ' — δεν υπήρξε απάντηση από τον server.';
        }

        return $prefix . ' (HTTP ' . $code . '): ' . $detail . $hint;
    }

    private static function cut($s, $max)
    {
        $s = trim((string) $s);
        return function_exists('mb_substr') ? mb_substr($s, 0, $max) : substr($s, 0, $max);
    }

    /** Η Viva γυρίζει άλλοτε camelCase κι άλλοτε PascalCase — τα ισοπεδώνουμε. */
    public static function lowerKeys(array $a)
    {
        $out = [];
        foreach ($a as $k => $v) {
            $out[strtolower((string) $k)] = is_array($v) ? self::lowerKeys($v) : $v;
        }
        return $out;
    }
}
