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

    /**
     * 转成处方类别
     * @param $code
     * @return bool
     */
    public static function convertNoteCategory ($code)
    {
        if (self::isWestNeutralDrug($code)) {
            return NoteCategory::WESTERN;
        }
        if ($code === self::CHINESE) {
            return NoteCategory::CHINESE;
        }
        if ($code === self::MATERIAL) {
            return NoteCategory::MATERIAL;
        }
    }

    /**
     * 是否西药/中成药
     * @param $code
     * @return bool
     */
    public static function isWestNeutralDrug ($code)
    {
        return in_array($code, [self::WESTERN, self::NEUTRAL]);
    }

    /**
     * 是否药品
     * @param $code
     * @return bool
     */
    public static function isDrug ($code)
    {
        return in_array($code, [self::WESTERN, self::NEUTRAL, self::CHINESE]);
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
