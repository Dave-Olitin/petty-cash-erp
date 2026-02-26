<?php
$html = file_get_contents('http://127.0.0.1:8000/vouchers/login');
file_put_contents(__DIR__.'/login_output.html', $html);
