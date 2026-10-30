<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/MySqlType.php';
use App\Core\MySqlType;

$checks = 0;
foreach (['bigint(20) unsigned' => 'bigint unsigned', 'tinyint(1) unsigned' => 'tinyint unsigned',
    'tinyint(3) unsigned' => 'tinyint unsigned', ' BIGINT(20) UNSIGNED ' => 'bigint unsigned',
    'bigint unsigned' => 'bigint unsigned', 'varchar(32)' => 'varchar(32)'] as $actual => $expected) {
    if (MySqlType::normalize($actual) !== $expected) throw new RuntimeException('Compatible metadata rejected.');
    $checks++;
}
foreach ([['bigint', 'bigint unsigned'], ['int unsigned', 'bigint unsigned'],
    ['tinyint', 'tinyint unsigned'], ['smallint unsigned', 'tinyint unsigned'],
    ['bigint zerofill', 'bigint unsigned'], ['varchar(31)', 'varchar(32)'],
    ['char(32)', 'varchar(32)'], ['bigint(20) unsigned', 'tinyint unsigned']] as [$actual, $expected]) {
    if (MySqlType::normalize($actual) === $expected) throw new RuntimeException('Incompatible metadata accepted.');
    $checks++;
}
echo "PASS: {$checks} MySQL/MariaDB type compatibility and rejection checks.\n";
