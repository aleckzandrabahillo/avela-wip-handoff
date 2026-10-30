<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);
try {
    $directory = '/data/keys';
    if (!is_dir($directory) || is_link($directory)) throw new RuntimeException();
    $write = static function (string $name, string $value) use ($directory): void {
        $path = $directory . '/' . $name;
        if (is_link($path)) throw new RuntimeException();
        if (is_file($path)) {
            if (!hash_equals((string) file_get_contents($path), $value)) throw new RuntimeException();
            return;
        }
        $handle = fopen($path, 'x');
        if (!$handle || fwrite($handle, $value) !== strlen($value)) throw new RuntimeException();
        fclose($handle);
        chmod($path, 0600);
    };
    $encoded = getenv('PII_KEYRING_BASE64');
    $keyring = is_string($encoded) ? base64_decode($encoded, true) : false;
    if ($keyring === false || strlen($keyring) > 4096) throw new RuntimeException();
    $data = json_decode($keyring, true, 8, JSON_THROW_ON_ERROR);
    if (array_keys($data) !== ['active', 'keys'] || !is_string($data['active'])
        || !is_array($data['keys']) || count($data['keys']) < 1 || count($data['keys']) > 8
        || !isset($data['keys'][$data['active']])) throw new RuntimeException();
    foreach ($data['keys'] as $id => $key) {
        if (!preg_match('/^[A-Za-z0-9_-]{1,24}$/D', $id) || !is_string($key)
            || strlen(base64_decode($key, true) ?: '') !== 32) throw new RuntimeException();
    }
    $ca = base64_decode((string) getenv('DB_SSL_CA_BASE64'), true);
    if ($ca === false || strlen($ca) > 16384 || openssl_x509_read($ca) === false) throw new RuntimeException();
    $write('pii-keyring.json', $keyring);
    $write('db-ca.pem', $ca);
    $maya = getenv('MAYA_API_TARGET_BASE64');
    if (is_string($maya) && $maya !== '') {
        $decoded = base64_decode($maya, true);
        if ($decoded === false || strlen($decoded) > 4096 || !is_array(json_decode($decoded, true, 8, JSON_THROW_ON_ERROR))) throw new RuntimeException();
        $write('maya-api-target.json', $decoded);
    }
    echo "Private keyring and database CA are ready.\n";
} catch (Throwable) {
    fwrite(STDERR, "Private configuration unavailable or differs from retained files. No keys were replaced.\n");
    exit(1);
}
