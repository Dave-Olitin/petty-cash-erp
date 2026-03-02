<?php
$config = [
    'curve_name' => 'prime256v1',
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'config' => __DIR__ . '/openssl_webpush.cnf'
];
var_dump(openssl_pkey_new($config));
while($msg = openssl_error_string()) echo $msg."\n";
