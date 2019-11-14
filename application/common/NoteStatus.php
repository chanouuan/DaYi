<?php
/**
 * 处方状态
 */

namespace app\common;

class NoteStatus
{

    const NOPAY  = 0;
    const PAY    = 1;
    const REFUND = 2;

    static $message = [
        0 => '未付费',
        1 => '已付费',
        2 => '已退费'
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
