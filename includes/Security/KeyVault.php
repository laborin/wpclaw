<?php

declare(strict_types=1);

namespace WPClaw\Security;

/**
 * Symmetric key helper for encrypting and decrypting provider secrets.
 */
final class KeyVault
{
    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }

        $key = $this->key_material();
        $iv = random_bytes(16);

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if (! is_string($ciphertext)) {
            return '';
        }

        $mac = hash_hmac('sha256', $iv . $ciphertext, $key, true);

        return base64_encode($iv . $mac . $ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        if ($encoded === '') {
            return '';
        }

        $raw = base64_decode($encoded, true);
        if (! is_string($raw) || strlen($raw) < 48) {
            return '';
        }

        $iv = substr($raw, 0, 16);
        $mac = substr($raw, 16, 32);
        $ciphertext = substr($raw, 48);

        $key = $this->key_material();
        $expectedMac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
        if (! hash_equals($expectedMac, $mac)) {
            return '';
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return is_string($plaintext) ? $plaintext : '';
    }

    private function key_material(): string
    {
        $base = defined('AUTH_KEY') ? (string) AUTH_KEY : 'wpclaw-default-key';

        return hash('sha256', $base, true);
    }
}
