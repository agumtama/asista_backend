<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$public = realpath(__DIR__.'/public');
$file = realpath($public.'/'.rawurldecode($uri));
if ($uri !== '/' && $file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
require $public.'/index.php';
