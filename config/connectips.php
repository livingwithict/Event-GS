<?php

return [
    'base_url' => env('CONNECTIPS_BASE_URL', 'https://uat.connectips.com:7443'),
    'merchant_id' => env('CONNECTIPS_MERCHANT_ID'),
    'app_id' => env('CONNECTIPS_APP_ID'),
    'app_name' => env('CONNECTIPS_APP_NAME'),
    'app_password' => env('CONNECTIPS_APP_PASSWORD'),
    'private_key_path' => env('CONNECTIPS_PRIVATE_KEY_PATH'),
    'private_key_passphrase' => env('CONNECTIPS_PRIVATE_KEY_PASSPHRASE', ''),
    'private_key_format' => env('CONNECTIPS_PRIVATE_KEY_FORMAT', 'pem'),
    'client_cert_path' => env('CONNECTIPS_CLIENT_CERT_PATH'),
    'verify_ssl' => env('CONNECTIPS_VERIFY_SSL', true),
    'success_url' => env('CONNECTIPS_SUCCESS_URL'),
    'failure_url' => env('CONNECTIPS_FAILURE_URL'),
];
