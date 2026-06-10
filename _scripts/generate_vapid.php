<?php
/**
 * CRC VAPID Key Generator
 *
 * Generates a P-256 (prime256v1) key pair for Web Push (VAPID, RFC 8292).
 * Run once, then add the printed values to your environment / server config.
 *
 * Usage: php _scripts/generate_vapid.php
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

if (!extension_loaded('openssl')) {
    die("The openssl extension is required.\n");
}

$res = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name'       => 'prime256v1',
]);

if ($res === false) {
    die("Failed to generate EC key pair: " . openssl_error_string() . "\n");
}

$details = openssl_pkey_get_details($res);
$x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
$y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
$d = str_pad($details['ec']['d'], 32, "\0", STR_PAD_LEFT);

// Uncompressed public point: 0x04 || X || Y
$publicPoint = "\x04" . $x . $y;

$b64u = function (string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
};

echo "\n";
echo "VAPID key pair generated.\n";
echo "Add the following to your environment (.env or server config):\n\n";
echo "VAPID_PUBLIC_KEY=" . $b64u($publicPoint) . "\n";
echo "VAPID_PRIVATE_KEY=" . $b64u($d) . "\n";
echo "VAPID_SUBJECT=mailto:admin@crc.org.za\n\n";
echo "The public key is also handed to the browser as the applicationServerKey\n";
echo "when a user subscribes to push notifications. Keep the private key secret.\n\n";
