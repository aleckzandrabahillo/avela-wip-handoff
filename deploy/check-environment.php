<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/Core/Env.php';
require dirname(__DIR__) . '/app/Support/helpers.php';
$errors = [];
foreach (['APP_URL', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'MAIL_HOST',
    'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'RECAPTCHA_SITE_KEY',
    'RECAPTCHA_SECRET_KEY', 'MFA_ENCRYPTION_KEY', 'PII_KEYRING_FILE', 'DB_SSL_CA'] as $name) {
    $value = env($name, '');
    if (!is_string($value) || trim($value) === '' || str_starts_with($value, 'your-')) $errors[] = $name;
}
$url = parse_url((string) env('APP_URL', ''));
if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host'])
    || in_array($url['host'], ['localhost', '127.0.0.1', '::1'], true)
    || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
    || !in_array($url['path'] ?? '', ['', '/'], true)) $errors[] = 'trusted HTTPS APP_URL';
foreach (['APP_ENV' => 'production', 'APP_DEBUG' => false, 'SESSION_SECURE_COOKIE' => true, 'MAIL_DRIVER' => 'smtp',
    'MAIL_LOCAL_STAFF_OTP' => false, 'DB_TLS_REQUIRED' => true, 'PII_ENCRYPTION_ENABLED' => true,
    'UPLOAD_SCANNER' => 'clamd', 'MAYA_SANDBOX_ENABLED' => false, 'MAYA_LOCAL_HOSTED_SANDBOX' => false,
    'MAYA_LOCAL_SIMULATION' => false, 'MAYA_WEBHOOK_READY' => false, 'MAYA_RECONCILIATION_READY' => false] as $key => $expected) {
    if (env($key, $expected === false ? false : null) !== $expected) $errors[] = $key;
}
if (!in_array(env('MAIL_ENCRYPTION'), ['tls', 'ssl'], true)) $errors[] = 'MAIL_ENCRYPTION';
if (env('RECAPTCHA_SITE_KEY') === '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI'
    || env('RECAPTCHA_SECRET_KEY') === '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe') $errors[] = 'production CAPTCHA keys';
if (strlen(base64_decode((string) env('MFA_ENCRYPTION_KEY'), true) ?: '') !== 32) $errors[] = 'MFA key format';
foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'gd', 'openssl', 'curl'] as $extension) {
    if (!extension_loaded($extension)) $errors[] = 'extension ' . $extension;
}
foreach (['proc_open', 'posix_kill'] as $function) if (!function_exists($function)) $errors[] = $function;
foreach (['PII_KEYRING_FILE', 'DB_SSL_CA'] as $key) if (!is_readable((string) env($key))) $errors[] = $key . ' readability';
if (!is_file(dirname(__DIR__) . '/vendor/autoload.php')) $errors[] = 'Composer dependencies';
if ($errors) {
    fwrite(STDERR, "Production configuration incomplete (names only):\n- " . implode("\n- ", array_unique($errors)) . "\n");
    exit(1);
}
echo "Production settings passed; live database, mail, CAPTCHA and scanner acceptance remain required.\n";
