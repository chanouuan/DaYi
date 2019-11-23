<?php

namespace app\controllers;

use ActionPDO;

class Index extends ActionPDO {

    public function _init ()
    {
        \DebugLog::_debug(false);
        header('Access-Control-Allow-Origin: *'); // 允许任意域名发起的跨域请求
        header('Access-Control-Allow-Headers: X-Requested-With,X_Requested_With');
        if (!isset($_SERVER['PHP_AUTH_USER']) ||
            !isset($_SERVER['PHP_AUTH_PW']) ||
            $_SERVER['PHP_AUTH_USER'] != 'admin' ||
            $_SERVER['PHP_AUTH_PW'] != 'chang_123456') {
            header('HTTP/1.1 401 Unauthorized');
            http_response_code(401);
            header('WWW-Authenticate: Basic realm="Administrator Secret"');
            exit('Administrator Secret!');
        }
    }

    public function logger ()
    {
        $path = trim_space(ltrim($_GET['path'], '/'));
        $path = ltrim(str_replace('.', '', $path), '/');
        $path = $path ? $path : (date('Ym') . '/' . date('Ymd') . '_debug');
        $path = APPLICATION_PATH . '/log/' . $path . '.log';
        if ($_GET['dir']) {
            $list = get_list_dir(APPLICATION_PATH . '/log');
            if (count($list) > 30) {
                $list = array_slice($list, count($list) - 30);
            }
            foreach ($list as $k => $v) {
                $list[$k] =  '<a href="' . (APPLICATION_URL . '/index/logger?path=' . str_replace(APPLICATION_PATH . '/log/', '', substr($v, 0, -4)) . '&dir=1') . '">' . str_replace(APPLICATION_PATH . '/log', '', $v) . '</a> ' . byte_convert(filesize($v)) . ' <a href="' . APPLICATION_URL . '/index/logger?path=' . str_replace([APPLICATION_PATH . '/log', '.log'], '', $v) . '&dir=1&clear=1">DEL</a>';
            }
        }
        if ($_GET['clear']) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title></title>
            <meta name="viewport" content="width=device-width,user-scalable=yes, minimum-scale=1, initial-scale=1"/>
        </head>
        <body>
            <pre><?=implode("\n",$list)?></pre>
            <pre><?=file_exists($path)?file_get_contents($path):'404'?></pre>
        </body>
        </html>
        <?php
        exit(0);
    }

}
