<?php
/**
 * 处方药品使用频率
 */

namespace app\common;

use CommonEnum;

class NoteFrequency extends CommonEnum
{

    static $message = [
        1 => ['name' => '每天1次', 'daily_count' => 1],
        2 => ['name' => '每天2次', 'daily_count' => 2],
        3 => ['name' => '每天3次', 'daily_count' => 3],
        4 => ['name' => '每天4次', 'daily_count' => 4],
        5 => ['name' => '隔日1次', 'daily_count' => 1],
        6 => ['name' => '每晚1次', 'daily_count' => 1],
        7 => ['name' => '每周1次', 'daily_count' => 1],
        8 => ['name' => '隔周1次', 'daily_count' => 1],
        9 => ['name' => '必要时', 'daily_count' => 1],
        10 => ['name' => '每天6次', 'daily_count' => 6],
        11 => ['name' => '立即', 'daily_count' => 1],
        12 => ['name' => '每6小时1次', 'daily_count' => 4],
        13 => ['name' => '每8小时1次', 'daily_count' => 3],
        14 => ['name' => '每12小时1次', 'daily_count' => 2],
        15 => ['name' => '每早1次', 'daily_count' => 1]
    ];

    public static function getMessage ($code)
    {
        return isset(self::$message[$code]) ? self::$message[$code]['name'] : $code;
    }

}
