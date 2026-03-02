<?php
$wp_conf = str_replace('\\', '/', __DIR__ . '/openssl_webpush.cnf');
var_dump($wp_conf);
putenv('OPENSSL_CONF=' . $wp_conf);
var_dump(openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]));
while($m = openssl_error_string()) echo $m . PHP_EOL;
