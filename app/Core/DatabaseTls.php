<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class DatabaseTls
{
    public static function options(string $ca, bool $required): array
    {
        if ($ca === '') {
            if ($required) throw new RuntimeException('Verified database TLS configuration is required.');
            return [];
        }
        if (strlen($ca) > 2048 || preg_match('/[\x00-\x1f]/', $ca)) {
            throw new RuntimeException('Database CA configuration is invalid.');
        }
        $file = realpath($ca);
        if (!$file || !is_file($file) || !is_readable($file) || is_link($ca)) {
            throw new RuntimeException('The database CA certificate is unavailable.');
        }
        $driver = class_exists('Pdo\\Mysql') ? 'Pdo\\Mysql' : PDO::class;
        $prefix = $driver === PDO::class ? 'MYSQL_ATTR_' : 'ATTR_';
        return [constant($driver . '::' . $prefix . 'SSL_CA') => $file,
            constant($driver . '::' . $prefix . 'SSL_VERIFY_SERVER_CERT') => true];
    }
}
