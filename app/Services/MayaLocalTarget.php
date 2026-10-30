<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Explicit operator approval for retained local demo data, separate from disposable tests. */
final class MayaLocalTarget
{
    public static function loopbackWeb(array $server, string $sapi): bool
    {
        return $sapi === 'cli-server' && in_array($server['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
            && (int) ($server['SERVER_PORT'] ?? 0) === 18080 && ($server['HTTP_HOST'] ?? '') === 'localhost:18080';
    }

    public static function approved(): bool
    {
        try {
            if (env('APP_ENV') !== 'development' || env('APP_URL') !== 'http://localhost:18080'
                || env('DB_HOST') !== '127.0.0.1' || env('MAYA_ENVIRONMENT', 'sandbox') !== 'sandbox') return false;
            $path = (string) env('MAYA_LOCAL_TARGET_FILE', '');
            if ($path === '' || strlen($path) > 2048 || preg_match('/[\x00-\x1f]/', $path)
                || str_contains($path, '://') || str_starts_with(str_replace('\\', '/', $path), '//')
                || is_link($path)) return false;
            $file = realpath($path); $root = realpath(dirname(__DIR__, 2));
            if (!$file || !$root || !is_file($file) || !is_readable($file) || filesize($file) > 4096
                || str_starts_with(self::path($file), self::path($root) . '/')
                || (PHP_OS_FAMILY !== 'Windows' && (fileperms($file) & 0077) !== 0)) return false;
            $profile = json_decode((string) file_get_contents($file), true, 8, JSON_THROW_ON_ERROR);
            $actual = Database::connection()->query('SELECT DATABASE() db,@@port port,@@hostname server,@@server_id sid,@@datadir dir')->fetch();
            return is_array($profile) && is_array($actual) && self::matches($profile, $actual, $root,
                (string) env('DB_HOST'), (string) env('DB_DATABASE'), (int) env('DB_PORT', 3306));
        } catch (\Throwable) { return false; }
    }

    public static function matches(array $profile, array $actual, string $root, string $host, string $database, int $port): bool
    {
        if (count($profile) !== 8 || ($profile['version'] ?? null) !== 1
            || ($profile['purpose'] ?? null) !== 'owner-authorized-local-maya-sandbox'
            || ($profile['origin'] ?? null) !== 'http://localhost:18080'
            || !is_string($profile['project'] ?? null) || self::path($profile['project']) !== self::path($root)
            || $host !== '127.0.0.1' || ($profile['db_host'] ?? null) !== $host
            || ($profile['database'] ?? null) !== $database || ($actual['db'] ?? null) !== $database
            || $port <= 1024 || in_array($port, [3306, 13307], true)
            || ($profile['network_port'] ?? null) !== $port || (int) ($actual['port'] ?? 0) !== $port
            || !is_array($profile['server_identity'] ?? null)) return false;
        foreach (['port', 'server', 'sid', 'dir'] as $field) {
            $expected = $profile['server_identity'][$field] ?? null; $observed = $actual[$field] ?? null;
            if ((!is_string($expected) && !is_int($expected)) || (!is_string($observed) && !is_int($observed))) return false;
            if ($field === 'dir' ? self::path((string) $expected) !== self::path((string) $observed)
                : (string) $expected !== (string) $observed) return false;
        }
        return true;
    }

    private static function path(string $path): string
    {
        return strtolower(rtrim(str_replace('\\', '/', $path), '/'));
    }
}
