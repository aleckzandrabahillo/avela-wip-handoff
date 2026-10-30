<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Services/MayaLocalTarget.php';
use App\Services\MayaLocalTarget;

$identity = ['port' => 13431, 'server' => 'synthetic-device', 'sid' => 47, 'dir' => 'C:/synthetic/mysql-data'];
$profile = ['version' => 1, 'purpose' => 'owner-authorized-local-maya-sandbox',
    'project' => 'C:/synthetic/avela', 'origin' => 'http://localhost:18080', 'db_host' => '127.0.0.1',
    'database' => 'synthetic_retained_store', 'network_port' => 13431, 'server_identity' => $identity];
$actual = ['db' => 'synthetic_retained_store'] + $identity;
$check = static fn(array $p, array $a = []) => MayaLocalTarget::matches($p, $a ?: $actual,
    'C:/synthetic/avela', '127.0.0.1', 'synthetic_retained_store', 13431);
$count = 0;
if (!$check($profile)) throw new RuntimeException('Approved exact target was rejected.');
$count++;
foreach (['version' => 2, 'purpose' => 'test', 'project' => 'C:/other/avela',
    'origin' => 'https://avela.onrender.com', 'db_host' => 'remote.example',
    'database' => 'another_store', 'network_port' => 13432] as $key => $bad) {
    if ($check([...$profile, $key => $bad])) throw new RuntimeException('Wrong target accepted: ' . $key);
    $count++;
}
foreach (['port' => 3306, 'server' => 'other-device', 'sid' => 48, 'dir' => 'C:/other/mysql-data'] as $key => $bad) {
    if ($check($profile, [...$actual, $key => $bad])) throw new RuntimeException('Server identity mismatch accepted.');
    $count++;
}
foreach (['server_identity', 'purpose', 'project'] as $missing) {
    $p = $profile; unset($p[$missing]);
    if ($check($p)) throw new RuntimeException('Incomplete approval accepted.');
    $count++;
}
if ($check([...$profile, 'unreviewed' => true])) throw new RuntimeException('Unexpected profile structure accepted.');
$count++;
foreach (['127.0.0.1', '::1'] as $loopback) {
    if (!MayaLocalTarget::loopbackWeb(['REMOTE_ADDR' => $loopback, 'SERVER_PORT' => '18080', 'HTTP_HOST' => 'localhost:18080'], 'cli-server')) throw new RuntimeException('Valid loopback rejected.');
    $count++;
}
foreach ([['REMOTE_ADDR' => '203.0.113.10', 'HTTP_X_FORWARDED_FOR' => '127.0.0.1'],
    ['SERVER_PORT' => 18081], ['HTTP_HOST' => 'attacker.example'], ['HTTP_HOST' => '127.0.0.1:18080']] as $bad) {
    if (MayaLocalTarget::loopbackWeb([...['REMOTE_ADDR' => '::1', 'SERVER_PORT' => 18080, 'HTTP_HOST' => 'localhost:18080'], ...$bad], 'cli-server')) throw new RuntimeException('Invalid local request accepted.');
    $count++;
}
if (MayaLocalTarget::loopbackWeb(['REMOTE_ADDR' => '::1', 'SERVER_PORT' => 18080, 'HTTP_HOST' => 'localhost:18080'], 'apache2handler')) throw new RuntimeException('Public runtime accepted as localhost.');
$count++;
echo 'PASS: ' . $count . " exact local Sandbox target checks; synthetic inputs, no database/provider requests.\n";
