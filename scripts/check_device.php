<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
session_write_close();

// Read-only operator checks. Never print identities, keys, personal data or proofs.
$checks = [];
$check = static function (string $name, callable $test) use (&$checks): void {
    try { $checks[$name] = $test() === true; } catch (Throwable) { $checks[$name] = false; }
};
try { $pdo = App\Core\Database::connection(); }
catch (Throwable) {
    echo json_encode(['mode' => 'offline-read-only', 'checks' => ['database_connected' => false]]), PHP_EOL;
    exit(2);
}
$check('database_connected', static fn(): bool => $pdo->query('SELECT 1')->fetchColumn() == 1);
$check('private_device_identity_matches', static function () use ($pdo): bool {
    $root = dirname(__DIR__);
    $pointer = json_decode(file_get_contents($root . '/.avela-local.json'), true, 8, JSON_THROW_ON_ERROR);
    $context = json_decode(file_get_contents($pointer['runtime_directory'] . '/restore-context.json'), true, 16, JSON_THROW_ON_ERROR);
    $actual = $pdo->query('SELECT DATABASE() db,@@port port,@@hostname server,@@server_id sid,@@datadir dir')->fetch();
    $path = static fn(string $value): string => strtolower(rtrim(str_replace('\\', '/', $value), '/'));
    if ($path($context['project']) !== $path($root) || $actual['db'] !== $context['database']
        || (int) $actual['port'] !== (int) $context['port'] || env('DB_HOST') !== '127.0.0.1') return false;
    foreach (['port', 'server', 'sid', 'dir'] as $field) {
        if ($field === 'dir' ? $path($actual[$field]) !== $path($context['server_identity'][$field])
            : (string) $actual[$field] !== (string) $context['server_identity'][$field]) return false;
    }
    return true;
});
foreach (['ActivationMigration', 'AccountExperienceMigration', 'DeliveryLocationMigration', 'AddressBookMigration', 'PiiMigration', 'MayaMigration'] as $migration) {
    $check($migration, static function () use ($migration, $pdo): bool {
        $class = 'App\\Services\\' . $migration;
        return (new $class())->run($pdo, false)['pending_additions'] === 0;
    });
}
$check('pii_keyring_usable', static function (): bool { App\Services\PiiCipher::configured(); return true; });
$check('mfa_key_usable', static fn(): bool => (new App\Services\MfaCipher())->configured());
$check('protected_data_readable', static function () use ($pdo): bool {
    foreach (['customer_profiles' => 'user_id', 'orders' => 'id'] as $table => $key) {
        foreach ($pdo->query('SELECT * FROM ' . $table . ' WHERE pii_encrypted=1') as $row) {
            App\Services\PiiStorage::read($table, (int) $row[$key], $row);
        }
    }
    $cipher = App\Services\PiiCipher::configured();
    foreach ($pdo->query('SELECT id,payload_ciphertext FROM customer_address_book') as $row) {
        $cipher->decrypt('customer_address_book', 'payload', (int) $row['id'], $row['payload_ciphertext']);
    }
    return true;
});
$check('enrolled_authenticators_readable', static function () use ($pdo): bool {
    $cipher = new App\Services\MfaCipher();
    foreach ($pdo->query('SELECT user_id,secret_encrypted FROM user_authenticators WHERE enabled_at IS NOT NULL') as $row) {
        $cipher->decrypt((int) $row['user_id'], $row['secret_encrypted']);
    }
    return true;
});
$check('smtp_configuration_valid', static function (): bool { (new App\Services\MailService())->smtpMailer(); return true; });
$check('maya_destination_mode_safe', static function (): bool {
    $inactive = env('MAYA_SANDBOX_ENABLED', false) === false && env('MAYA_LOCAL_HOSTED_SANDBOX', false) === false;
    $approved = env('MAYA_SANDBOX_ENABLED', false) === true && env('MAYA_LOCAL_HOSTED_SANDBOX', false) === true
        && env('MAYA_MERCHANT_ORIGIN') === 'http://localhost:18080' && App\Services\MayaLocalTarget::approved();
    return ($inactive || $approved) && env('MAYA_LOCAL_SIMULATION', false) === false
        && env('MAYA_WEBHOOK_READY', false) === false && env('MAYA_RECONCILIATION_READY', false) === false;
});
echo json_encode(['mode' => 'offline-read-only', 'checks' => $checks,
    'active_products' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE status="active"')->fetchColumn(),
    'live_provider_requests' => 0, 'business_rows_changed' => 0], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
exit(in_array(false, $checks, true) ? 2 : 0);
