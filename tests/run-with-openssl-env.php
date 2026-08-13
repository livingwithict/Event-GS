<?php

if (PHP_OS_FAMILY === 'Windows') {
    // OpenSSL 3.x on Windows needs OPENSSL_CONF pointing at a real openssl.cnf,
    // otherwise openssl_pkey_new() fails with "BIO routines::no such file" and
    // every ConnectIPS test blows up. Probe the current PHP install first so this
    // works on any machine, then fall back to the Herd layout.
    $candidates = [
        getenv('OPENSSL_CONF') ?: null,
        dirname(PHP_BINARY).'\\extras\\ssl\\openssl.cnf',
        PHP_CONFIG_FILE_PATH.'\\extras\\ssl\\openssl.cnf',
        getenv('USERPROFILE').'\\.config\\herd\\openssl.cnf',
        'C:\\Users\\User\\.config\\herd\\openssl.cnf',
    ];
    foreach (array_filter($candidates) as $conf) {
        if (file_exists($conf)) {
            putenv("OPENSSL_CONF=$conf");
            break;
        }
    }

    // Herd's minimal openssl.cnf references $ENV::HOME/.rnd.
    $home = getenv('HOME') ?: getenv('USERPROFILE');
    if ($home && is_dir($home)) {
        putenv("HOME=$home");
    }
}

$cmd = 'php artisan test';
passthru($cmd, $exitCode);
exit($exitCode);
