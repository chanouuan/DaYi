<?php
/**
 * 订单来源
 */

namespace app\common;

class OrderSource
{

    const DOCTOR      = 1;
    const BUY_DRUG    = 2;
    const APPOINTMENT = 3;

    static $message = [
        1 => '医生处方',
        2 => '购药',
        3 => '网上预约'
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
