<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
session_write_close();
try {
    $parts = filter_var(getenv('UPLOAD_SEED_PARTS'), FILTER_VALIDATE_INT);
    if ($parts === false || $parts < 0 || $parts > 32) throw new RuntimeException();
    if ($parts === 0) exit(0);
    $encoded = '';
    for ($index = 1; $index <= $parts; $index++) {
        $part = getenv(sprintf('UPLOAD_SEED_%03d', $index));
        if (!is_string($part) || strlen($part) > 32768) throw new RuntimeException();
        $encoded .= $part;
    }
    $bytes = base64_decode($encoded, true);
    if ($bytes === false || strlen($bytes) > 1048576) throw new RuntimeException();
    $files = json_decode($bytes, true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($files) || count($files) > 100) throw new RuntimeException();
    $saved = 0;
    foreach ($files as $file) {
        if (!is_array($file) || !preg_match('~^(products|profile)/[a-f0-9]{32}\.(jpg|png|webp)$~D', $file['path'] ?? '')) throw new RuntimeException();
        $content = base64_decode($file['content'] ?? '', true);
        if ($content === false || strlen($content) > App\Services\ImageUploadPolicy::maxBytes()
            || !hash_equals($file['sha256'] ?? '', hash('sha256', $content))) throw new RuntimeException();
        $destination = '/data/uploads/' . $file['path'];
        if (is_link($destination)) throw new RuntimeException();
        if (is_file($destination)) {
            if (!hash_equals(hash_file('sha256', $destination), $file['sha256'])) throw new RuntimeException();
            continue;
        }
        $temporary = tempnam('/data/tmp', 'import-');
        if (!$temporary) throw new RuntimeException();
        try {
            if (file_put_contents($temporary, $content) !== strlen($content)) throw new RuntimeException();
            App\Services\UploadScanner::assertClean($temporary);
            $info = getimagesize($temporary);
            $expected = match (pathinfo($destination, PATHINFO_EXTENSION)) {
                'jpg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'webp' => IMAGETYPE_WEBP,
            };
            if (!$info || $info[2] !== $expected || !rename($temporary, $destination)) throw new RuntimeException();
            chmod($destination, 0600);
            $saved++;
        } finally { if (is_file($temporary)) unlink($temporary); }
    }
    echo json_encode(['private_uploads_seeded' => $saved]), PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, "Private upload seed rejected. Existing files were not overwritten.\n");
    exit(1);
}
