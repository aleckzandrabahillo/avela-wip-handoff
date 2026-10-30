<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/Env.php';
require dirname(__DIR__) . '/app/Support/helpers.php';
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) require dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});

use App\Services\MayaApiReconciliation as Api;
use App\Services\MayaSandboxConfig as Config;

$checks = 0;
function checkApi(bool $ok, string $name): void {
    global $checks;
    if (!$ok) throw new RuntimeException($name);
    $checks++;
}
$actual = ['db'=>'synthetic_sandbox', 'port'=>3306, 'server'=>'synthetic-mysql', 'sid'=>7, 'dir'=>'/synthetic/mysql/'];
$profile = ['version'=>1, 'purpose'=>'owner-authorized-cloud-maya-sandbox', 'origin'=>'https://store.example.invalid',
    'db_host'=>'mysql.example.invalid', 'network_port'=>10814, 'database'=>$actual['db'],
    'first_order_id'=>19, 'server_identity'=>array_diff_key($actual, ['db'=>true])];
$matches = static fn(array $p, array $a = []): bool => Api::matches($p, $a ?: $actual,
    'https://store.example.invalid', 'https://store.example.invalid', 'mysql.example.invalid', 10814, $actual['db']);
checkApi($matches($profile), 'Exact target accepts separate external and MySQL internal ports.');
foreach (['version'=>2, 'purpose'=>'disposable', 'origin'=>'http://store.example.invalid', 'db_host'=>'other.invalid',
    'network_port'=>3306, 'database'=>'other_db', 'first_order_id'=>0] as $key=>$wrong) {
    checkApi(!$matches(array_replace($profile, [$key=>$wrong])), 'Reject changed '.$key);
}
foreach (['port'=>3307, 'server'=>'other', 'sid'=>8, 'dir'=>'/other/'] as $key=>$wrong) {
    $p=$profile; $p['server_identity'][$key]=$wrong;
    checkApi(!$matches($p), 'Reject changed server identity '.$key);
}
checkApi(!$matches($profile + ['extra'=>true]), 'Reject unexpected target data.');
checkApi(!$matches(array_diff_key($profile, ['first_order_id'=>true])), 'Require historical boundary.');
checkApi(!Api::matches($profile,$actual,'https://store.example.invalid','https://attacker.invalid','mysql.example.invalid',10814,$actual['db']), 'Reject callback origin mismatch.');
$hash=Api::fingerprint($profile);$now=1000;$heartbeat=['pid'=>123,'at'=>$now,'target'=>$hash];
checkApi(Api::healthyHeartbeat($heartbeat,$hash,$now), 'Accept current matching heartbeat.');
foreach (['pid'=>0,'at'=>909,'target'=>str_repeat('0',64)] as $key=>$wrong) checkApi(!Api::healthyHeartbeat(array_replace($heartbeat,[$key=>$wrong]),$hash,$now),'Reject stale/mismatched heartbeat '.$key);
checkApi(!Api::healthyHeartbeat(array_replace($heartbeat,['at'=>1001]),$hash,$now),'Reject future heartbeat.');
checkApi(!Api::healthyHeartbeat($heartbeat+['extra'=>true],$hash,$now),'Reject extra heartbeat fields.');
$_ENV=['APP_ENV'=>'production','MAYA_CONFIRMATION_MODE'=>'api_reconciliation','MAYA_ENVIRONMENT'=>'sandbox',
    'MAYA_SANDBOX_ENABLED'=>'true','MAYA_WEBHOOK_READY'=>'false','MAYA_RECONCILIATION_READY'=>'true',
    'MAYA_LOCAL_SIMULATION'=>'false','MAYA_LOCAL_HOSTED_SANDBOX'=>'false'];
checkApi(!Config::available(), 'Flags alone never enable cloud payments without private target and live worker.');
echo "PASS: {$checks} cloud Sandbox API target/readiness cases; no database/provider calls.\n";
