<?php
/**
 * 药品状态
 */

namespace app\common;

class DrugStatus
{

    const NOSALES  = 0;
    const OFFSALES = 1;
    const ONSALES  = 2;

    static $message = [
        0 => '禁用',
        1 => '未采购',
        2 => '在售'
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
