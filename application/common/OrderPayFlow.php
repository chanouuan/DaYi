<?php
/**
 * 资金流出操作类型
 */

namespace app\common;

class OrderPayFlow
{

    const CHARGE = 1;
    const REFUND = 2;

    static $message = [
        1 => '收费',
        2 => '退费'
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
