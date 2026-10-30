<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Keep strict validation while supporting Avela's existing MySQL string literals. */
final class DatabaseSqlMode
{
    public static function compatible(string $mode): string
    {
        if (strlen($mode) > 4096 || !preg_match('/^[A-Za-z0-9_, ]*$/D', $mode)) {
            throw new RuntimeException('Unsupported database session configuration.');
        }
        $flags = array_values(array_unique(array_filter(array_map(
            static fn(string $flag): string => strtoupper(trim($flag)), explode(',', $mode)
        ))));
        if (in_array('ANSI', $flags, true)) {
            // ANSI is a shorthand that would re-enable ANSI_QUOTES if retained.
            // Preserve its other semantics even when only the shorthand is supplied.
            foreach (['REAL_AS_FLOAT', 'PIPES_AS_CONCAT', 'IGNORE_SPACE', 'ONLY_FULL_GROUP_BY'] as $flag) {
                if (!in_array($flag, $flags, true)) $flags[] = $flag;
            }
        }
        return implode(',', array_values(array_diff($flags, ['ANSI', 'ANSI_QUOTES'])));
    }
}
