<?php
if (!defined('ABSPATH')) {
    exit;
}

class CareToChina_Payment_Security {

    /**
     * Derive encryption key from WordPress salts
     */
    private static function get_encryption_key() {
        $salt1 = defined('AUTH_KEY') ? AUTH_KEY : 'default-ctc-salt-1';
        $salt2 = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'default-ctc-salt-2';
        return hash_hkdf('sha256', $salt1 . $salt2, 32, 'ctc-payment-encryption');
    }

    /**
     * Encrypt a secret string for storing in options
     */
    public static function encrypt_secret($plaintext) {
        if (empty($plaintext)) {
            return '';
        }

        // If already encrypted, return as is
        if (strpos($plaintext, 'ctc_enc:') === 0) {
            return $plaintext;
        }

        $key = self::get_encryption_key();

        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
            return 'ctc_enc:sodium:' . base64_encode($nonce . $ciphertext);
        } else {
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-gcm'));
            $tag = '';
            $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, 0, $iv, $tag);
            return 'ctc_enc:openssl:' . base64_encode($iv . $tag . $ciphertext);
        }
    }

    /**
     * Decrypt an encrypted secret string
     */
    public static function decrypt_secret($encrypted) {
        if (empty($encrypted)) {
            return '';
        }

        // Return plaintext directly if not encrypted with prefix
        if (strpos($encrypted, 'ctc_enc:') !== 0) {
            return $encrypted;
        }

        $key = self::get_encryption_key();
        $parts = explode(':', $encrypted, 3);
        
        if (count($parts) < 3) {
            return '';
        }

        $method = $parts[1];
        $raw_data = base64_decode($parts[2]);

        if ($method === 'sodium' && function_exists('sodium_crypto_secretbox_open')) {
            $nonce_len = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            $nonce = substr($raw_data, 0, $nonce_len);
            $ciphertext = substr($raw_data, $nonce_len);
            $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
            return $decrypted !== false ? $decrypted : '';
        } elseif ($method === 'openssl') {
            $iv_len = openssl_cipher_iv_length('aes-256-gcm');
            $iv = substr($raw_data, 0, $iv_len);
            $tag = substr($raw_data, $iv_len, 16);
            $ciphertext = substr($raw_data, $iv_len + 16);
            $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, 0, $iv, $tag);
            return $decrypted !== false ? $decrypted : '';
        }

        return '';
    }

    /**
     * Mask secret key for display in settings UI
     */
    public static function mask_secret($secret) {
        $decrypted = self::decrypt_secret($secret);
        if (empty($decrypted)) {
            return '';
        }
        $len = strlen($decrypted);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }
        return substr($decrypted, 0, 4) . '••••••••' . substr($decrypted, -4);
    }

    /**
     * Transient-based rate limiter for payment intent creation
     */
    public static function check_rate_limit($user_id, $ip, $max_requests = 10, $window_seconds = 900) {
        $key = 'ctc_rate_' . md5($user_id . '_' . $ip);
        $attempts = (int) get_transient($key);

        if ($attempts >= $max_requests) {
            return false;
        }

        set_transient($key, $attempts + 1, $window_seconds);
        return true;
    }

    /**
     * Acquire atomic lock for refund/cancel actions
     */
    public static function acquire_refund_lock($booking_id, $ttl = 30) {
        $lock_key = 'ctc_refund_lock_' . intval($booking_id);
        if (get_transient($lock_key)) {
            return false; // Lock in use
        }
        set_transient($lock_key, time(), $ttl);
        return true;
    }

    /**
     * Release atomic lock for refund/cancel actions
     */
    public static function release_refund_lock($booking_id) {
        $lock_key = 'ctc_refund_lock_' . intval($booking_id);
        delete_transient($lock_key);
    }
}
