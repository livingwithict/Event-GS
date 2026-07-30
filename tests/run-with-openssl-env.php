<?php

if (PHP_OS_FAMILY === 'Windows') {
    $conf = 'C:\\Users\\User\\.config\\herd\\openssl.cnf';
    $home = 'C:\\Users\\User';
    if (file_exists($conf)) {
        putenv("OPENSSL_CONF=$conf");
    }
    if (is_dir($home)) {
        putenv("HOME=$home");
    }
}

$cmd = 'php artisan test';
passthru($cmd, $exitCode);
exit($exitCode);
