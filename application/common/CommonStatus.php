<?php
/**
 * 一般状态
 */

namespace app\common;

class CommonStatus
{

    const NOT = 0;
    const OK  = 1;

    static $message = [
        0 => '停用',
        1 => '启用'
    ];

    public static function format ($code)
    {
        return isset(self::$message[$code]) ? $code : null;
    }

    public static function getMessage ($code)
    {
        return isset(self::$message[$code]) ? self::$message[$code] : $code;
    }

}
