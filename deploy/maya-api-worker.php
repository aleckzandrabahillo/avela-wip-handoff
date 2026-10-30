<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);
require dirname(__DIR__) . '/bootstrap.php';
session_write_close();

use App\Core\Database;
use App\Services\MayaApiReconciliation;
use App\Services\MayaPaymentService;
use App\Services\MayaSandboxConfig;
use App\Services\OrderNotification;

try {
    if (!function_exists('posix_geteuid') || posix_geteuid() === 0) throw new RuntimeException('Unprivileged worker required.');
    $profile = MayaApiReconciliation::profile();
    $lock = fopen('/data/keys/maya-api-worker.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Worker already running.');
    chmod('/data/keys/maya-api-worker.lock', 0600);
    $pdo = Database::connection();
    $service = new MayaPaymentService($pdo, MayaSandboxConfig::client());
    $pulse = static function () use ($profile): void {
        $temporary = MayaApiReconciliation::HEARTBEAT . '.tmp';
        if (is_link($temporary) || is_link(MayaApiReconciliation::HEARTBEAT)) throw new RuntimeException('Heartbeat path rejected.');
        $bytes = json_encode(['pid'=>getmypid(), 'at'=>time(), 'target'=>MayaApiReconciliation::fingerprint($profile)], JSON_THROW_ON_ERROR);
        if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes)) throw new RuntimeException('Heartbeat failed.');
        chmod($temporary, 0600);
        if (!rename($temporary, MayaApiReconciliation::HEARTBEAT)) throw new RuntimeException('Heartbeat failed.');
    };
    $pulse();
    echo "Sandbox API reconciliation started; historical orders excluded.\n";
    $cursor = 0;
    while (true) {
        // Revalidate the operator target each cycle, before touching a payment.
        if (MayaApiReconciliation::fingerprint(MayaApiReconciliation::profile()) !== MayaApiReconciliation::fingerprint($profile)) throw new RuntimeException('Target changed.');
        $batch = MayaApiReconciliation::pendingOrders($pdo, $profile['first_order_id'], $cursor);
        if (!$batch) $cursor = 0;
        foreach ($batch as $payment) {
            // Advance even after provider failure, so one failing payment cannot starve later ones.
            $cursor = (int) $payment['order_id'];
            try {
                $service->reconcile((int) $payment['order_id']);
                // Only this new, authenticated-result order; never drain the historical outbox.
                OrderNotification::deliver($pdo, (int) $payment['order_id']);
            } catch (Throwable) {
                // Provider errors never become paid evidence. Retry on a later bounded cycle.
                error_log('Sandbox API confirmation deferred.');
            }
        }
        $pulse();
        sleep(20);
    }
} catch (Throwable) {
    fwrite(STDERR, "Sandbox API worker stopped; private target/connection unavailable.\n");
    exit(1);
}
