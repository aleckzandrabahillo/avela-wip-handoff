<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$socket = @stream_socket_client('unix:///run/clamav/clamd.ctl', $error, $message, 0.5);
if (!$socket) exit(1);
stream_set_timeout($socket, 1);
fwrite($socket, "zPING\0");
$response = fread($socket, 32);
fclose($socket);
exit(rtrim($response, "\0\r\n") === 'PONG' ? 0 : 1);
