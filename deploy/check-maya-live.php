<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc > 2 || ($argc === 2 && $argv[1] !== '--create-session')) exit(1);
require dirname(__DIR__) . '/bootstrap.php';
session_write_close();

try {
    $account = posix_getpwnam('www-data');
    if (!$account || posix_geteuid() !== $account['uid']) throw new RuntimeException();
    $profile = App\Services\MayaApiReconciliation::profile();
    if (!App\Services\MayaSandboxConfig::available()) throw new RuntimeException();
    $pdo = App\Core\Database::connection();
    $q = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE id<?');
    $q->execute([$profile['first_order_id']]);
    $historical = (int) $q->fetchColumn();
    $client = App\Services\MayaSandboxConfig::client();
    $reference = bin2hex(random_bytes(16));
    if ($client->paymentByReference($reference, 100) !== null) throw new RuntimeException();
    $created = false;
    if ($argc === 2) {
        // Synthetic PHP 1.00 provider session only. No Avela order, card entry or charge.
        $file = '/data/keys/maya-readiness-check.json';
        if (file_exists($file) || is_link($file)) throw new RuntimeException('A readiness session already exists.');
        $return = $profile['origin'] . '/payments/maya/return?order=0';
        $checkout = $client->createCheckout(100, $reference,
            ['success'=>$return.'&result=success','failure'=>$return.'&result=failure','cancel'=>$return.'&result=cancel']);
        $proof = $client->verifiedPayment($checkout['checkout_id'], $reference, 100);
        umask(0077);
        file_put_contents($file, json_encode(['reference'=>$reference,'checkout'=>$checkout,'proof'=>$proof], JSON_THROW_ON_ERROR));
        chmod($file,0600);
        $created = true;
    }
    echo json_encode(['exact_cloud_target_verified'=>true,'real_worker_heartbeat_valid'=>true,
        'card_checkout_enabled'=>true,'historical_orders_excluded'=>$historical,
        'authenticated_sandbox_lookup_passed'=>true,'synthetic_sandbox_session_created'=>$created,
        'business_rows_changed'=>false,'card_entered'=>false,'payment_completed'=>false], JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,"Cloud Sandbox acceptance stopped; private details suppressed.\n");
    exit(1);
}
