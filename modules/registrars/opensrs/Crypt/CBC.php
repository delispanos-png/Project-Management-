<?php
/**
 * Class to emulate Perl's Crypt::CBC module
 *
 * Blowfish support that is compatable with Perl requires libmcrypt >= 2.4.9.
 * If you are using libmcrypt <= 2.4.8, Blowfish encryption will work,
 * but your data will not be readable by Perl scripts.  It will work
 * "internally" .. i.e. this class will be able to encode/decode the data.
 *
 * Blowfish support that is compatable with PHP applications using
 * libmcrypt <= 2.4.8 requies you to use 'BLOWFISH-COMPAT' when
 * specifying the cipher.  Check the libmcrypt docs when in doubt.
 *
 * This class no longer works with libmcrypt 2.2.x versions.
 *
 * NOTE: the cipher names in this class may change depending on how
 * the author of libcrypt decides to name things internally.
 *
 *
 * @category  Encryption
 * @package   Crypt_CBC
 * @author    Colin Viebrock <colin@viebrock.com>
 * @copyright 2002-2012 Colin Viebrock
 * @version   $Revision: 322853 $
 * @license   http://www.opensource.org/licenses/bsd-license.php BSD
 * @link      http://pear.php.net/package/Crypt_CBC
 */

/**
 * Modified by WHMCS Limited: Removed inheritence/dependency on PEAR
 */

class Crypt_CBC {

    // Blowfish constants
    const BLOWFISH_KEYSIZE_MIN = 4;
    const BLOWFISH_KEYSIZE_MAX = 56;
    const BLOWFISH_BLOCKSIZE = 8;

    // DES constants
    const DES_BLOCKSIZE = 8;
    const DES_KEYSIZE = 8;

    // AES constants
    const AES_BLOCKSIZE = 16;
    const AES_128_KEYSIZE = 16;
    const AES_256_KEYSIZE = 32;

    // Default constants
    const DEFAULT_BLOCKSIZE = 8;
    const DEFAULT_KEYSIZE = 8;

    const KNOWN_CIPHERS = [
        'DES'               => 'des-cbc',
        'BLOWFISH'          => 'bf-cbc',
        'BLOWFISH-COMPAT'   => 'bf-cbc',
        'AES-128'           => 'aes-128-cbc',
        'AES-256'           => 'aes-256-cbc',
    ];
    const HEADER_SPEC = 'RandomIV';

    /**
    * OpenSSL cipher method
    * @var string
    */
    public $openssl_method;

    /**
    * blocksize of cipher
    * @var string
    */
    public $blocksize;

    /**
    * keysize of cipher
    * @var int
    */
    public $keysize;

    /**
    * mangled key
    * @var string
    */
    public $keyhash;

    /**
     * @var string|null
     */
    public $key = null;

    /**
     * Constructor for Crypt_CBC.
     *
     * @param string $key The encryption key to use.
     * @param string $cipher The cipher to use (default: 'DES').
     * @throw \Exception
     * @access public
     */
    function __construct($key, $cipher='DES')
    {
        if (!function_exists('openssl_encrypt')) {
            throw new \RuntimeException('The OpenSSL extension is not available.');
        }

        /* check for key */
        if (!$key) {
            throw new \RuntimeException('You did not specify a key.');
        }

        /* check for cipher */
        $cipher = strtoupper($cipher);
        if (!isset(self::KNOWN_CIPHERS[$cipher])) {
            throw new \RuntimeException(sprintf('Unknown Cipher: "%s"', $cipher));
        }

        $this->openssl_method = self::KNOWN_CIPHERS[$cipher];

        /* initialize cipher properties */
        // Set block size and key size based on cipher type
        switch ($cipher) {
            case 'DES':
                $this->blocksize = self::DES_BLOCKSIZE;
                $this->keysize = self::DES_KEYSIZE;
                break;
            case 'BLOWFISH':
            case 'BLOWFISH-COMPAT':
                $this->blocksize = self::BLOWFISH_BLOCKSIZE;
                // Dynamically set Blowfish key size based on provided key length (BLOWFISH_KEYSIZE_MIN-BLOWFISH_KEYSIZE_MAX bytes)
                $keylen = strlen($key);
                if ($keylen < self::BLOWFISH_KEYSIZE_MIN) {
                    $this->keysize = self::BLOWFISH_KEYSIZE_MIN;
                } elseif ($keylen > self::BLOWFISH_KEYSIZE_MAX) {
                    $this->keysize = self::BLOWFISH_KEYSIZE_MAX;
                } else {
                    $this->keysize = $keylen;
                }
                break;
            case 'AES-128':
                $this->blocksize = self::AES_BLOCKSIZE;
                $this->keysize = self::AES_128_KEYSIZE;
                break;
            case 'AES-256':
                $this->blocksize = self::AES_BLOCKSIZE;
                $this->keysize = self::AES_256_KEYSIZE;
                break;
            default:
                $this->blocksize = self::DEFAULT_BLOCKSIZE;
                $this->keysize = self::DEFAULT_KEYSIZE;
        }

        /* mangle key with MD5 */

        $this->keyhash = $this->_md5perl($key);
        while( strlen($this->keyhash) < $this->keysize ) {
            $this->keyhash .= $this->_md5perl($this->keyhash);
        }

        $this->key = substr($this->keyhash, 0, $this->keysize);
    }

    /**
    * Encryption method
    *
    * @param    $clear      plaintext
    *
    * @return   $crypt      encrypted text, or trigger_error
    *
    * @access   public
    *
    */

    function encrypt($clear)
    {
        /* new IV for each message */

        try {
            $iv = random_bytes($this->blocksize);
        } catch (ValueError|\Random\RandomException $e) {
            $this->error(sprintf('IV generation failed: %s', $e->getMessage()));
            return false;
        }

        /* create the message header */

        $crypt = self::HEADER_SPEC . $iv;

        $ciphertext = openssl_encrypt(
            $clear,
            $this->openssl_method,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
        if ($ciphertext === false) {
            $this->error('OpenSSL encryption failed.');
            return false;
        }
        $crypt .= $ciphertext;

        return $crypt;
    }



    /**
    * Decryption method
    *
    * @param    $crypt      encrypted text
    *
    * @return   $clear      plaintext, or trigger_error
    *
    * @access   public
    *
    */

    function decrypt($crypt) {

        /* get the IV from the message header */

        $iv_offset = strlen(self::HEADER_SPEC);
        $header = substr($crypt, 0, $iv_offset);
        $iv = substr ($crypt, $iv_offset, $this->blocksize);
        if ($header != self::HEADER_SPEC) {
            $this->error('The system could not find an initialization vector.');
            return false;
        }

        $crypt = substr($crypt, $iv_offset+$this->blocksize);

        // Validate ciphertext length is a multiple of blocksize
        if ((strlen($crypt) % $this->blocksize) !== 0) {
            $this->error('invalid ciphertext length');
            return false;
        }

        $clear = openssl_decrypt(
            $crypt,
            $this->openssl_method,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
        if ($clear === false) {
            $this->error('OpenSSL decryption failed.');
            return false;
        }

        /* OpenSSL handles PKCS#7 padding removal automatically */

        return $clear;

    }



    /**
    * Emulate Perl's MD5 function, which returns binary data
    *
    * @param    $string     string to MD5
    *
    * @return   $hash       binary hash
    *
    * @access private
    *
    */

    function _md5perl($string)
    {
        return pack('H*', md5($string));
    }

    /**
     * @param string $message
     * @return void
     */
    private function error($message, $level = E_USER_WARNING): void
    {
        trigger_error($message, $level);
    }
}

