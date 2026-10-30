<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/DatabaseSqlMode.php';
use App\Core\DatabaseSqlMode;

$count = 0;
$equal = static function (string $input, string $expected) use (&$count): void {
    if (DatabaseSqlMode::compatible($input) !== $expected) throw new RuntimeException('SQL mode assertion failed.');
    $count++;
};
$equal('', '');
$equal('STRICT_TRANS_TABLES,NO_ZERO_DATE,ONLY_FULL_GROUP_BY', 'STRICT_TRANS_TABLES,NO_ZERO_DATE,ONLY_FULL_GROUP_BY');
$equal('REAL_AS_FLOAT,PIPES_AS_CONCAT,ANSI_QUOTES,IGNORE_SPACE,ONLY_FULL_GROUP_BY,ANSI,STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION', 'REAL_AS_FLOAT,PIPES_AS_CONCAT,IGNORE_SPACE,ONLY_FULL_GROUP_BY,STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION');
$equal('ANSI', 'REAL_AS_FLOAT,PIPES_AS_CONCAT,IGNORE_SPACE,ONLY_FULL_GROUP_BY');
$equal('ANSI_QUOTES', '');
$equal('STRICT_ALL_TABLES,ANSI_QUOTES,STRICT_ALL_TABLES', 'STRICT_ALL_TABLES');
$equal(' future_mode , ansi_quotes ', 'FUTURE_MODE');
foreach (["STRICT_ALL_TABLES\0", "ANSI_QUOTES'; DROP TABLE users", str_repeat('A', 4097)] as $invalid) {
    try { DatabaseSqlMode::compatible($invalid); } catch (RuntimeException) { $count++; continue; }
    throw new RuntimeException('Unsafe SQL mode input was accepted.');
}
echo 'PASS: ' . $count . " database SQL mode checks; no network requests.\n";
