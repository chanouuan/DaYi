<?php
/**
 * 角色
 */

namespace app\common;

class Role
{

    const ADMIN  = 1;
    const DOCTOR = 3;
    const NURSE  = 4;

    static $message = [
        1 => '管理员',
        3 => '医生',
        4 => '护士'
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
