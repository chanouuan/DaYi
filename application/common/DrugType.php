<?php
/**
 * 药品类型
 */

namespace app\common;

class DrugType
{

    const WESTERN  = 1;
    const NEUTRAL  = 2;
    const CHINESE  = 3;
    const MATERIAL = 4;

    static $message = [
        1 => '西药',
        2 => '中成药',
        3 => '草药',
        4 => '材料'
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
