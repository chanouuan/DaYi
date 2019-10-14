<?php
/**
 * 草药性质
 */

namespace app\common;

class NoteSide
{

    static $message = [
        1 => '内服',
        2 => '外用'
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
