<?php
// Only for `php -S` local testing; Apache uses .htaccess in production.
$f = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($_SERVER['REQUEST_URI'] !== '/' && is_file($f)) return false;
require __DIR__ . '/index.php';
