<?php
/**
 * 出入库类型
 */

namespace app\common;

class StockType
{

    const PULL = 1;
    const PUSH = 2;

    static $message = [
        1 => '入库',
        2 => '出库'
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
