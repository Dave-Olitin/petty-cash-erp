<?php
$phpIni = php_ini_loaded_file();
$sslConf = dirname($phpIni) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
var_dump($sslConf);
var_dump(file_exists($sslConf));

$config = [
    'curve_name' => 'prime256v1',
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'config' => $sslConf
];
var_dump(openssl_pkey_new($config));
while($msg = openssl_error_string()) echo $msg."\n";
