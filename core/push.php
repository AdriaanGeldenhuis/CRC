<?php
/**
 * CRC Web Push sender (pure PHP, no external dependencies)
 *
 * Implements the Web Push protocol so notifications can be delivered to the
 * browser even when the app is closed:
 *   - VAPID request authentication        (RFC 8292, ES256 JWT)
 *   - aes128gcm payload encryption        (RFC 8291 + RFC 8188)
 *
 * Requires the openssl extension (EC + ECDH via openssl_pkey_derive) and the
 * hash_hkdf() function, both standard on PHP 8. Subscriptions live in the
 * push_subscriptions table; VAPID keys come from config (see generate_vapid.php).
 */

// Prevent direct access
if (!defined('CRC_LOADED')) {
    die('Direct access not permitted');
}

class Push {
    /** Whether Web Push is configured (VAPID keys present). */
    public static function isConfigured(): bool {
        return defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY !== ''
            && defined('VAPID_PRIVATE_KEY') && VAPID_PRIVATE_KEY !== '';
    }

    /**
     * Send a push payload to every active subscription belonging to a user.
     * Subscriptions that the push service reports as gone (404/410) are
     * deactivated. Returns the number of successful sends.
     *
     * @param array $payload  title, body, url, tag
     */
    public static function sendToUser(int $userId, array $payload): int {
        if (!self::isConfigured()) {
            return 0;
        }

        $subs = Database::fetchAll(
            "SELECT id, endpoint, p256dh_key, auth_key FROM push_subscriptions WHERE user_id = ? AND is_active = 1",
            [$userId]
        );

        if (!$subs) {
            return 0;
        }

        $body = json_encode([
            'title' => $payload['title'] ?? 'CRC',
            'body'  => $payload['body'] ?? '',
            'url'   => $payload['url'] ?? '/notifications/',
            'tag'   => $payload['tag'] ?? 'crc',
        ], JSON_UNESCAPED_UNICODE);

        $sent = 0;
        foreach ($subs as $sub) {
            $status = self::deliver($sub['endpoint'], $sub['p256dh_key'], $sub['auth_key'], $body);

            if ($status >= 200 && $status < 300) {
                $sent++;
                Database::update('push_subscriptions', ['last_used_at' => date('Y-m-d H:i:s')], 'id = ?', [$sub['id']]);
            } elseif ($status === 404 || $status === 410) {
                // Subscription no longer valid – stop using it.
                Database::update('push_subscriptions', ['is_active' => 0], 'id = ?', [$sub['id']]);
            }
        }

        return $sent;
    }

    /**
     * Encrypt the payload for one subscription and POST it to the endpoint.
     * Returns the HTTP status code (0 on transport failure).
     */
    private static function deliver(string $endpoint, string $p256dhB64, string $authB64, string $payload): int {
        try {
            $uaPublic   = self::b64uDecode($p256dhB64);   // 65-byte uncompressed point
            $authSecret = self::b64uDecode($authB64);     // 16-byte auth secret
            if (strlen($uaPublic) !== 65 || strlen($authSecret) < 16) {
                return 0;
            }

            $encrypted = self::encryptPayload($payload, $uaPublic, $authSecret);

            $jwt = self::vapidJwt($endpoint);
            $headers = [
                'Authorization: vapid t=' . $jwt . ', k=' . VAPID_PUBLIC_KEY,
                'Content-Encoding: aes128gcm',
                'Content-Type: application/octet-stream',
                'TTL: 2419200',
            ];

            return self::httpPost($endpoint, $encrypted, $headers);
        } catch (\Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('Push deliver failed: ' . $e->getMessage());
            }
            return 0;
        }
    }

    /**
     * aes128gcm content encryption of a single record (RFC 8188) using the key
     * derivation defined for Web Push (RFC 8291).
     */
    private static function encryptPayload(string $plaintext, string $uaPublic, string $authSecret): string {
        // 1. Ephemeral (application server) ECDH key pair on P-256.
        $asKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ]);
        if ($asKey === false) {
            throw new \RuntimeException('EC key generation failed');
        }
        $asDetails = openssl_pkey_get_details($asKey);
        $asPublic = "\x04"
            . str_pad($asDetails['ec']['x'], 32, "\0", STR_PAD_LEFT)
            . str_pad($asDetails['ec']['y'], 32, "\0", STR_PAD_LEFT);

        // 2. ECDH shared secret with the user agent's public key.
        $uaPubPem = self::p256PublicPem($uaPublic);
        $ecdhSecret = openssl_pkey_derive($uaPubPem, $asKey, 32);
        if ($ecdhSecret === false) {
            throw new \RuntimeException('ECDH derivation failed');
        }

        // 3. Random salt (16 bytes).
        $salt = random_bytes(16);

        // 4. Key derivation (RFC 8291 §3.4).
        $keyInfo = 'WebPush: info' . "\x00" . $uaPublic . $asPublic;
        $ikm = hash_hkdf('sha256', $ecdhSecret, 32, $keyInfo, $authSecret);

        $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        // 5. Encrypt. Single record => 0x02 padding delimiter (RFC 8188 §2).
        $record = $plaintext . "\x02";
        $tag = '';
        $ciphertext = openssl_encrypt($record, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($ciphertext === false) {
            throw new \RuntimeException('AES-128-GCM encryption failed');
        }

        // 6. aes128gcm header: salt(16) | rs(4) | idlen(1) | keyid(as_public 65).
        $recordSize = 4096;
        $header = $salt
            . pack('N', $recordSize)
            . chr(strlen($asPublic))
            . $asPublic;

        return $header . $ciphertext . $tag;
    }

    /** Build a VAPID ES256 JWT for the endpoint's origin (RFC 8292). */
    private static function vapidJwt(string $endpoint): string {
        $parts = parse_url($endpoint);
        $aud = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        $header  = ['typ' => 'JWT', 'alg' => 'ES256'];
        $subject = (defined('VAPID_SUBJECT') && VAPID_SUBJECT !== '') ? VAPID_SUBJECT : 'mailto:admin@crc.org.za';
        $claims  = [
            'aud' => $aud,
            'exp' => time() + 12 * 3600,
            'sub' => $subject,
        ];

        $signingInput = self::b64uEncode(json_encode($header)) . '.' . self::b64uEncode(json_encode($claims));

        $privatePem = self::vapidPrivatePem();
        $derSignature = '';
        if (!openssl_sign($signingInput, $derSignature, $privatePem, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('VAPID JWT signing failed');
        }

        // ES256 needs a raw 64-byte R||S signature, not the DER OpenSSL returns.
        $rawSignature = self::derToRawSignature($derSignature);

        return $signingInput . '.' . self::b64uEncode($rawSignature);
    }

    /** Reconstruct an EC private key PEM from the configured VAPID key pair. */
    private static function vapidPrivatePem(): string {
        $d = self::b64uDecode(VAPID_PRIVATE_KEY);          // 32-byte scalar
        $point = self::b64uDecode(VAPID_PUBLIC_KEY);       // 65-byte point
        $d = str_pad($d, 32, "\0", STR_PAD_LEFT);

        // SEC1 ECPrivateKey (RFC 5915):
        //   SEQUENCE { INTEGER 1, OCTET STRING d, [0] params(prime256v1), [1] BIT STRING point }
        $der =
            "\x02\x01\x01" .                                   // version = 1
            "\x04\x20" . $d .                                  // privateKey (32 bytes)
            "\xA0\x0A\x06\x08\x2A\x86\x48\xCE\x3D\x03\x01\x07" . // [0] prime256v1 OID
            "\xA1\x44\x03\x42\x00" . $point;                   // [1] BIT STRING public point
        $der = "\x30" . chr(strlen($der)) . $der;             // SEQUENCE wrapper (len < 128)

        $pem = "-----BEGIN EC PRIVATE KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END EC PRIVATE KEY-----\n";

        return $pem;
    }

    /** Build an EC public key PEM (SubjectPublicKeyInfo) from a raw 65-byte point. */
    private static function p256PublicPem(string $point): string {
        // Fixed SPKI prefix for an uncompressed prime256v1 public key.
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    /** Convert an ASN.1/DER ECDSA signature to a raw 64-byte (R||S) signature. */
    private static function derToRawSignature(string $der): string {
        $offset = 0;
        if (($der[$offset++] ?? '') !== "\x30") {
            throw new \RuntimeException('Invalid DER signature');
        }
        $seqLen = ord($der[$offset++]);
        if ($seqLen & 0x80) {
            // Long-form length (unexpected for P-256, but handle defensively).
            $bytes = $seqLen & 0x7f;
            $offset += $bytes;
        }

        $readInt = function () use ($der, &$offset): string {
            if (($der[$offset++] ?? '') !== "\x02") {
                throw new \RuntimeException('Invalid DER integer');
            }
            $len = ord($der[$offset++]);
            $val = substr($der, $offset, $len);
            $offset += $len;
            // Strip a leading 0x00 sign byte, then left-pad to 32 bytes.
            $val = ltrim($val, "\x00");
            return str_pad($val, 32, "\0", STR_PAD_LEFT);
        };

        $r = $readInt();
        $s = $readInt();
        return $r . $s;
    }

    /** POST the encrypted body to the push service. Returns HTTP status code. */
    private static function httpPost(string $url, string $body, array $headers): int {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status;
    }

    private static function b64uEncode(string $bin): string {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function b64uDecode(string $str): string {
        $str = strtr($str, '-_', '+/');
        $pad = strlen($str) % 4;
        if ($pad) {
            $str .= str_repeat('=', 4 - $pad);
        }
        return base64_decode($str) ?: '';
    }
}
