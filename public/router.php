<?php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri === '/' || $uri === '') {
    $_GET['page'] = 'containers';
    require __DIR__ . '/index.php';
    return true;
}

if (file_exists(__DIR__ . $uri)) {
    return false;
}

if (strpos($uri, '?') !== false) {
    $query = parse_url($uri, PHP_URL_QUERY);
    parse_str($query, $params);
    $_GET = array_merge($_GET, $params);
    require __DIR__ . '/index.php';
    return true;
}

$query = parse_url($uri, PHP_URL_QUERY);
parse_str($query, $params);
$_GET = array_merge($_GET, $params);

$path = ltrim($uri, '/');
$segments = explode('/', $path);
$_GET['page'] = $path;

require __DIR__ . '/index.php';
return true;
