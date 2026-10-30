<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc > 2 || ($argc === 2 && $argv[1] !== '--send-mail-check')) exit(1);
require dirname(__DIR__) . '/bootstrap.php';
session_write_close();
try {
    $account = posix_getpwnam('www-data');
    if (!$account || posix_geteuid() !== $account['uid'] || env('APP_ENV') !== 'production') {
        throw new RuntimeException('Run with the actual web service identity.');
    }
    $pdo = App\Core\Database::connection();
    $tls = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch();
    if (empty($tls['Value']) || env('DB_TLS_REQUIRED') !== true) throw new RuntimeException();
    $cipher = App\Services\PiiCipher::configured();
    $addresses = 0;
    foreach ($pdo->query('SELECT id,payload_ciphertext FROM customer_address_book') as $row) {
        $cipher->decrypt('customer_address_book', 'payload', (int) $row['id'], $row['payload_ciphertext']);
        $addresses++;
    }
    $authenticators = 0;
    foreach ($pdo->query('SELECT user_id,secret_encrypted FROM user_authenticators WHERE enabled_at IS NOT NULL') as $row) {
        (new App\Services\MfaCipher())->decrypt((int) $row['user_id'], $row['secret_encrypted']);
        $authenticators++;
    }
    $file = tempnam('/data/tmp', 'scanner-acceptance-');
    if (!$file) throw new RuntimeException();
    try {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j1ioAAAAASUVORK5CYII=', true);
        if (file_put_contents($file, $png) !== strlen($png) || !getimagesize($file)) throw new RuntimeException();
        App\Services\UploadScanner::assertClean($file);
        // Standard harmless antivirus test bytes, generated only in this private synthetic file.
        $test = base64_decode('WDVPIVAlQEFQWzRcUFpYNTQoUF4pN0NDKTd9JEVJQ0FSLVNUQU5EQVJELUFOVElWSVJVUy1URVNULUZJTEUhJEgrSCo=', true);
        if (strlen($test) !== 68 || file_put_contents($file, $test) !== 68) throw new RuntimeException();
        $exit = (new App\Services\UploadScanProcess())->run([
            '/usr/bin/clamdscan', '--config-file=/etc/clamav/clamd.conf', '--fdpass', '--no-summary', '--', $file,
        ], 15.0);
        if ($exit !== 1) throw new RuntimeException('Antivirus rejection was not confirmed.');
        $rejected = false;
        try { App\Services\UploadScanner::assertClean($file); } catch (RuntimeException) { $rejected = true; }
        if (!$rejected) throw new RuntimeException();
    } finally { if (is_file($file)) unlink($file); }
    $mail = (new App\Services\MailService())->smtpMailer();
    if (!$mail->smtpConnect()) throw new RuntimeException('SMTP authentication failed.');
    $sent = false;
    if ($argc === 2) {
        $mail->addAddress((string) env('MAIL_FROM_ADDRESS'));
        $mail->Subject = 'Avela cloud defense readiness check';
        $mail->Body = 'This is the single SMTP delivery check from the hosted Avela defense deployment. No account or verification code was changed.';
        $sent = $mail->send();
        if (!$sent) throw new RuntimeException();
    }
    $mail->smtpClose();
    echo json_encode([
        'web_service_identity_verified' => true, 'database_verified_tls' => true,
        'addresses_decrypted' => $addresses, 'authenticators_decrypted' => $authenticators,
        'clean_image_scan_passed' => true, 'antivirus_test_rejected' => true,
        'smtp_authenticated' => true, 'sender_check_accepted' => $sent,
        'business_rows_modified' => false,
    ], JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Cloud acceptance failed (' . get_class($error) . '); private details suppressed.' . PHP_EOL);
    exit(1);
}
