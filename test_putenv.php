<?php
putenv('OPENSSL_CONF=' . __DIR__ . '\openssl_webpush.cnf');
$config = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
var_dump(openssl_pkey_new($config));
while($msg = openssl_error_string()) echo $msg."\n";
