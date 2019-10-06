<?php
/**
 * 处方类别
 */

namespace app\common;

use CommonEnum;

class NoteCategory extends CommonEnum
{

    const WESTERN   = 1;
    const CHINESE   = 2;
    const TREATMENT = 3;

    static $message = [
        1 => '西药方',
        2 => '草药方',
        3 => '诊疗单'
    ];

    /**
     * 是否药品
     * @param $code
     * @return bool
     */
    public static function isDrug ($code)
    {
        return in_array($code, [self::WESTERN, self::CHINESE]);
    }

}
