<?php

declare(strict_types=1);

namespace App\Core;

/** Integer display widths are metadata; storage type and signedness are unchanged. */
final class MySqlType
{
    public static function normalize(string $type): string
    {
        return preg_replace('/\b(bigint|tinyint)\([0-9]+\)/', '$1', strtolower(trim($type)));
    }
}
