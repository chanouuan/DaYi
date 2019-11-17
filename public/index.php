<?php

date_default_timezone_set('PRC');

if (isset($_SERVER['PATH_INFO'])) {
    if (strpos($_SERVER['PATH_INFO'], '.')) {
        http_response_code(404);
        exit(0);
    }
}

header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
	http_response_code(200);
    exit(0);
}

define('APPLICATION_PATH', dirname(__DIR__));
define('APPLICATION_URL', rtrim(implode('', [$_SERVER['REQUEST_SCHEME'], '://', $_SERVER['HTTP_HOST'], str_replace('index.php', '', $_SERVER['SCRIPT_NAME'])]), '/'));
define('TIMESTAMP', $_SERVER['REQUEST_TIME']);
define('MICROTIME', isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true));
define('DEBUG_PASS', '__debug');
define('DEBUG_LEVEL', 3);
if (isset($_SERVER['HTTP_APIVERSION'])) {
    define('APIVERSION', 'v' . intval($_SERVER['HTTP_APIVERSION']));
} else if (isset($_POST['apiversion'])) {
    define('APIVERSION', 'v' . intval($_POST['apiversion']));
} else if (isset($_GET['apiversion'])) {
    define('APIVERSION', 'v' . intval($_GET['apiversion']));
} else {
    define('APIVERSION', 'v1');
}

$composerPath = APPLICATION_PATH . '/vendor/autoload.php';
if (file_exists($composerPath)) {
    require $composerPath;
}
require APPLICATION_PATH . '/application/library/Common.php';
require APPLICATION_PATH . '/application/library/Init.php';

$controller->run();
