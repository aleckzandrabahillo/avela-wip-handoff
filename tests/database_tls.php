<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/DatabaseTls.php';
use App\Core\DatabaseTls;

$checks = 0;
function tlsCheck(bool $ok): void {
    global $checks;
    if (!$ok) throw new RuntimeException('Database TLS policy failed.');
    $checks++;
}
function tlsReject(callable $operation): bool {
    try { $operation(); return false; } catch (RuntimeException) { return true; }
}
tlsCheck(DatabaseTls::options('', false) === []);
tlsCheck(tlsReject(static fn() => DatabaseTls::options('', true)));
tlsCheck(tlsReject(static fn() => DatabaseTls::options(__DIR__ . '/missing-ca.pem', true)));
tlsCheck(tlsReject(static fn() => DatabaseTls::options("bad\0path", true)));
tlsCheck(tlsReject(static fn() => DatabaseTls::options(str_repeat('a', 2049), true)));
$handle = tmpfile();
if (!$handle) throw new RuntimeException('TLS fixture unavailable.');
try {
    fwrite($handle, 'Synthetic CA path fixture, not a handshake certificate.');
    $path = stream_get_meta_data($handle)['uri'];
    $options = DatabaseTls::options($path, true);
    $driver = class_exists('Pdo\\Mysql') ? 'Pdo\\Mysql' : PDO::class;
    $prefix = $driver === PDO::class ? 'MYSQL_ATTR_' : 'ATTR_';
    $caAttribute = constant($driver . '::' . $prefix . 'SSL_CA');
    $verifyAttribute = constant($driver . '::' . $prefix . 'SSL_VERIFY_SERVER_CERT');
    tlsCheck($options[$caAttribute] === realpath($path));
    tlsCheck($options[$verifyAttribute] === true);
    tlsCheck(DatabaseTls::options($path, false)[$verifyAttribute] === true);
} finally { fclose($handle); }
echo "PASS: {$checks} database TLS configuration checks; no network requests.\n";
