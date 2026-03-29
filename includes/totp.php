<?php
/**
 * TOTP — RFC 6238 Time-based One-Time Password
 *
 * Compatible with Authy, Google Authenticator, 1Password, Bitwarden, etc.
 * No external dependencies — pure PHP with openssl for secret encryption.
 */

require_once __DIR__ . '/../config/config.php';

class TOTP
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const DIGITS  = 6;
    private const PERIOD  = 30;   // seconds per time step
    private const SKEW    = 1;    // ±windows to accept (covers ~90s clock skew)

    // ── Key generation ────────────────────────────────────────────────────────

    /**
     * Generate a new random TOTP secret (20 bytes = 160 bits = 32 base32 chars).
     * This is the size recommended by RFC 4226.
     */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    // ── Code generation & verification ───────────────────────────────────────

    /** Compute the TOTP code for a given secret and time-step offset. */
    public static function getCode(string $secret, int $offset = 0): string
    {
        $key     = self::base32Decode($secret);
        $counter = intdiv(time(), self::PERIOD) + $offset;

        // Pack counter as unsigned 64-bit big-endian (RFC 4226 §5)
        $msg = pack('J', $counter);

        $hash = hash_hmac('sha1', $msg, $key, true);

        // Dynamic truncation (RFC 4226 §5.4)
        $offset = ord($hash[19]) & 0x0f;
        $code   = (
            ((ord($hash[$offset])     & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8)  |
             (ord($hash[$offset + 3]) & 0xff)
        ) % (10 ** self::DIGITS);

        return str_pad((string)$code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a user-supplied 6-digit code against the secret.
     * Accepts current window ±SKEW to handle clock drift.
     * Uses hash_equals() to prevent timing attacks.
     */
    public static function verify(string $secret, string $userCode): bool
    {
        $userCode = preg_replace('/\s+/', '', $userCode);
        if (!preg_match('/^\d{6}$/', $userCode)) return false;

        for ($i = -self::SKEW; $i <= self::SKEW; $i++) {
            if (hash_equals(self::getCode($secret, $i), $userCode)) {
                return true;
            }
        }
        return false;
    }

    // ── URI for QR codes ──────────────────────────────────────────────────────

    /**
     * Build the otpauth:// URI.
     * Authy / Authenticator apps parse this from a QR code.
     */
    public static function otpauthUri(string $secret, string $issuer, string $account): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            $label,
            rawurlencode($secret),
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD
        );
    }

    // ── Secret encryption (AES-256-CBC keyed to SECRET_KEY) ──────────────────
    //
    // Storing encrypted ciphertext means a DB dump alone is not enough to
    // clone TOTP — the attacker also needs config/config.php (SECRET_KEY).

    public static function encryptSecret(string $plainSecret): string
    {
        $key = hash('sha256', SECRET_KEY . 'totp', true); // 32 bytes
        $iv  = random_bytes(16);
        $enc = openssl_encrypt($plainSecret, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($enc === false) {
            throw new RuntimeException('TOTP secret encryption failed.');
        }
        $mac = hash_hmac('sha256', $iv . $enc, $key, true);
        return base64_encode($mac . $iv . $enc);
    }

    public static function decryptSecret(string $stored): string|false
    {
        $raw = base64_decode($stored, strict: true);
        if ($raw === false || strlen($raw) < 49) return false; // 32 mac + 16 iv + ≥1 enc

        $key = hash('sha256', SECRET_KEY . 'totp', true);
        $mac = substr($raw, 0, 32);
        $iv  = substr($raw, 32, 16);
        $enc = substr($raw, 48);

        // Verify MAC before decrypting (encrypt-then-MAC)
        $expected = hash_hmac('sha256', $iv . $enc, $key, true);
        if (!hash_equals($expected, $mac)) return false;

        $plain = openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $plain !== false ? $plain : false;
    }

    // ── DB helpers ────────────────────────────────────────────────────────────

    /** Load raw encrypted secret from DB. Returns null if not configured. */
    public static function loadEncryptedSecret(\PDO $db): string|null
    {
        $v = $db->prepare('SELECT setting_value FROM portal_settings WHERE setting_key = ?');
        $v->execute(['totp_secret']);
        $val = $v->fetchColumn();
        return ($val && $val !== '') ? (string)$val : null;
    }

    /** Returns true if MFA is enabled and a secret is stored. */
    public static function isEnabled(\PDO $db): bool
    {
        $s = $db->prepare('SELECT setting_value FROM portal_settings WHERE setting_key = ?');
        $s->execute(['totp_enabled']);
        $enabled = $s->fetchColumn();
        if ($enabled !== '1') return false;
        return self::loadEncryptedSecret($db) !== null;
    }

    /** Persist a new secret (encrypted) and mark MFA enabled. */
    public static function enable(\PDO $db, string $plainSecret): void
    {
        $encrypted = self::encryptSecret($plainSecret);
        $stmt = $db->prepare('REPLACE INTO portal_settings (setting_key, setting_value) VALUES (?,?)');
        $stmt->execute(['totp_secret',  $encrypted]);
        $stmt->execute(['totp_enabled', '1']);
    }

    /** Disable MFA and wipe the stored secret. */
    public static function disable(\PDO $db): void
    {
        $stmt = $db->prepare('REPLACE INTO portal_settings (setting_key, setting_value) VALUES (?,?)');
        $stmt->execute(['totp_enabled', '0']);
        $stmt->execute(['totp_secret',  '']);
    }

    /** Load and decrypt the secret. Returns null if unavailable. */
    public static function getPlainSecret(\PDO $db): string|null
    {
        $enc = self::loadEncryptedSecret($db);
        if ($enc === null) return null;
        $plain = self::decryptSecret($enc);
        return $plain !== false ? $plain : null;
    }

    // ── Base32 codec (RFC 4648) ───────────────────────────────────────────────

    public static function base32Encode(string $data): string
    {
        $chars  = self::BASE32;
        $result = '';
        $bits   = 0;
        $acc    = 0;
        for ($i = 0; $i < strlen($data); $i++) {
            $acc  = ($acc << 8) | ord($data[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $bits   -= 5;
                $result .= $chars[($acc >> $bits) & 0x1f];
            }
        }
        if ($bits > 0) {
            $result .= $chars[($acc << (5 - $bits)) & 0x1f];
        }
        return $result;
    }

    public static function base32Decode(string $data): string
    {
        $data   = strtoupper(preg_replace('/[\s=]/', '', $data));
        $chars  = self::BASE32;
        $result = '';
        $bits   = 0;
        $acc    = 0;
        for ($i = 0; $i < strlen($data); $i++) {
            $pos = strpos($chars, $data[$i]);
            if ($pos === false) continue;
            $acc  = ($acc << 5) | $pos;
            $bits += 5;
            if ($bits >= 8) {
                $bits   -= 8;
                $result .= chr(($acc >> $bits) & 0xff);
            }
        }
        return $result;
    }
}
