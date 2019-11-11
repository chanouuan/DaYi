<?php
/**
 * 处方类别
 */

namespace app\common;

class NoteCategory
{

    const WESTERN   = 1;
    const CHINESE   = 2;
    const TREATMENT = 3;
    const MATERIAL  = 4;

    static $message = [
        1 => '西药方',
        2 => '草药方',
        3 => '诊疗单',
        4 => '材料'
    ];

    /**
     * 是否药品 & 材料
     * @param $code
     * @return bool
     */
    public static function isDrug ($code)
    {
        return in_array($code, [self::WESTERN, self::CHINESE, self::MATERIAL]);
    }

    public static function format ($code)
    {
        return isset(self::$message[$code]) ? $code : null;
    }

    public static function getMessage ($code)
    {
        return isset(self::$message[$code]) ? self::$message[$code] : $code;
    }

}
