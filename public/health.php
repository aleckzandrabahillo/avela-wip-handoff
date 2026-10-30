<?php

declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD'); http_response_code(405); exit;
}
try {
    require dirname(__DIR__) . '/vendor/autoload.php';
    require dirname(__DIR__) . '/app/Core/Env.php';
    require dirname(__DIR__) . '/app/Support/helpers.php';
    App\Core\Env::load(dirname(__DIR__) . '/.env');
    spl_autoload_register(static function (string $class): void {
        if (str_starts_with($class, 'App\\')) {
            require dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        }
    });
    $pdo = App\Core\Database::connection();
    $pdo->query('SELECT 1 FROM users LIMIT 1');
    if (!App\Services\AddressBook::installed($pdo)) throw new RuntimeException();
    App\Services\PiiCipher::configured();
    if (env('MAYA_CONFIRMATION_MODE', '') === 'api_reconciliation' && !App\Services\MayaApiReconciliation::ready()) throw new RuntimeException();
    if (env('APP_ENV') === 'production') {
        $socket = @stream_socket_client('unix:///run/clamav/clamd.ctl', $error, $message, 0.5);
        if (!$socket) throw new RuntimeException();
        stream_set_timeout($socket, 1);
        fwrite($socket, "zPING\0");
        $pong = fread($socket, 32);
        fclose($socket);
        if (rtrim($pong, "\0\r\n") !== 'PONG') throw new RuntimeException();
    }
    echo '{"ready":true}';
} catch (Throwable) {
    http_response_code(503); echo '{"ready":false}';
}
