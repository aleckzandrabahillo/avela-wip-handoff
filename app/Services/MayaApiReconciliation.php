<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/** Explicit HTTPS Sandbox mode using authenticated API proof, without proxy webhook exceptions. */
final class MayaApiReconciliation
{
    public const TARGET = '/data/keys/maya-api-target.json';
    public const HEARTBEAT = '/data/keys/maya-api-heartbeat.json';

    public static function profile(): array
    {
        if (PHP_OS_FAMILY !== 'Linux' || env('APP_ENV') !== 'production'
            || env('MAYA_CONFIRMATION_MODE', '') !== 'api_reconciliation'
            || env('MAYA_ENVIRONMENT', 'sandbox') !== 'sandbox'
            || env('MAYA_SANDBOX_ENABLED', false) !== true
            || env('MAYA_RECONCILIATION_READY', false) !== true
            || env('MAYA_WEBHOOK_READY', false) !== false
            || env('MAYA_LOCAL_HOSTED_SANDBOX', false) !== false
            || env('MAYA_LOCAL_SIMULATION', false) !== false
            || env('DB_TLS_REQUIRED', false) !== true) throw new RuntimeException('Sandbox API mode is not approved.');
        $file = self::TARGET;
        if (is_link($file) || !is_file($file) || !is_readable($file) || filesize($file) > 4096
            || (fileperms($file) & 0077) !== 0) throw new RuntimeException('Private Sandbox target is unavailable.');
        $profile = json_decode((string) file_get_contents($file), true, 8, JSON_THROW_ON_ERROR);
        $actual = Database::connection()->query('SELECT DATABASE() db,@@port port,@@hostname server,@@server_id sid,@@datadir dir')->fetch();
        if (!is_array($profile) || !self::matches($profile, $actual, (string) env('APP_URL'),
            (string) env('MAYA_MERCHANT_ORIGIN'), (string) env('DB_HOST'), (int) env('DB_PORT'), (string) env('DB_DATABASE')))
            throw new RuntimeException('Sandbox API target differs.');
        return $profile;
    }

    public static function matches(array $p, array $actual, string $origin, string $merchant, string $host, int $port, string $database): bool
    {
        $url = parse_url($origin);
        if (count($p) !== 8 || ($p['version'] ?? null) !== 1 || ($p['purpose'] ?? null) !== 'owner-authorized-cloud-maya-sandbox'
            || ($p['origin'] ?? null) !== $origin || $merchant !== $origin
            || !$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host'])
            || array_diff(array_keys($url), ['scheme', 'host']) || in_array($url['host'], ['localhost', '127.0.0.1', '::1'], true)
            || ($p['db_host'] ?? null) !== $host || ($p['network_port'] ?? null) !== $port || $port < 1 || $port > 65535
            || ($p['database'] ?? null) !== $database || ($actual['db'] ?? null) !== $database
            || !is_int($p['first_order_id'] ?? null) || $p['first_order_id'] < 1
            || !is_array($p['server_identity'] ?? null) || count($p['server_identity']) !== 4) return false;
        foreach (['port', 'server', 'sid', 'dir'] as $key) {
            if (!isset($p['server_identity'][$key], $actual[$key])
                || (!is_string($p['server_identity'][$key]) && !is_int($p['server_identity'][$key]))
                || (string) $p['server_identity'][$key] !== (string) $actual[$key]) return false;
        }
        return true;
    }

    public static function fingerprint(array $profile): string
    {
        return hash('sha256', json_encode($profile, JSON_THROW_ON_ERROR));
    }

    public static function healthyHeartbeat(array $heartbeat, string $fingerprint, int $now): bool
    {
        return count($heartbeat) === 3 && is_int($heartbeat['pid'] ?? null) && $heartbeat['pid'] > 1
            && is_int($heartbeat['at'] ?? null) && $heartbeat['at'] <= $now && $heartbeat['at'] >= $now - 90
            && is_string($heartbeat['target'] ?? null) && hash_equals($fingerprint, $heartbeat['target']);
    }

    public static function ready(): bool
    {
        try {
            $profile = self::profile(); $file = self::HEARTBEAT;
            clearstatcache(true, $file);
            if (is_link($file) || !is_file($file) || filesize($file) > 1024 || (fileperms($file) & 0077) !== 0) return false;
            $heartbeat = json_decode((string) file_get_contents($file), true, 4, JSON_THROW_ON_ERROR);
            return is_array($heartbeat) && self::healthyHeartbeat($heartbeat, self::fingerprint($profile), time())
                && function_exists('posix_kill') && posix_kill($heartbeat['pid'], 0);
        } catch (\Throwable) { return false; }
    }

    /** A bounded batch excludes all retained historical orders and deterministic simulations. */
    public static function pendingOrders(PDO $pdo, int $firstOrderId, int $afterOrderId = 0): array
    {
        if ($firstOrderId < 1) throw new RuntimeException('A positive order boundary is required.');
        $q = $pdo->prepare('SELECT order_id,checkout_id,merchant_reference FROM maya_payments WHERE order_id>=? AND order_id>? AND state IN ("initiating","pending") AND (checkout_id IS NULL OR REPLACE(LOWER(checkout_id),"-","")<>merchant_reference) ORDER BY order_id LIMIT 5');
        $q->execute([$firstOrderId, $afterOrderId]);
        return array_values(array_filter($q->fetchAll(), static fn(array $p): bool => !MayaPaymentService::simulatedReference($p)));
    }
}
