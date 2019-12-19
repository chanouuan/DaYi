<?php
/**
 * 处方药品使用频率
 */

namespace app\common;

class NoteFrequency
{

    static $message = [
        1 =>  ['name' => '每天1次',     'daily_count' => 1, 'code' => 'qd'],
        2 =>  ['name' => '每天2次',     'daily_count' => 2, 'code' => 'bid'],
        3 =>  ['name' => '每天3次',     'daily_count' => 3, 'code' => 'tid'],
        4 =>  ['name' => '每天4次',     'daily_count' => 4, 'code' => 'qid'],
        5 =>  ['name' => '隔日1次',     'daily_count' => 1, 'code' => 'qod'],
        6 =>  ['name' => '每晚1次',     'daily_count' => 1, 'code' => 'qn'],
        7 =>  ['name' => '每周1次',     'daily_count' => 1, 'code' => 'qw'],
        8 =>  ['name' => '隔周1次',     'daily_count' => 1, 'code' => 'qwd'],
        9 =>  ['name' => '必要时',      'daily_count' => 1, 'code' => 'prn'],
        10 => ['name' => '每天6次',     'daily_count' => 6, 'code' => 'q4h'],
        11 => ['name' => '立即',        'daily_count' => 1, 'code' => 'st'],
        12 => ['name' => '每6小时1次',  'daily_count' => 4, 'code' => 'q6h'],
        13 => ['name' => '每8小时1次',  'daily_count' => 3, 'code' => 'q8h'],
        14 => ['name' => '每12小时1次', 'daily_count' => 2, 'code' => 'q12h'],
        15 => ['name' => '每早1次',     'daily_count' => 1, 'code' => 'QM']
    ];

    public static function getCode ($name)
    {
        foreach (self::$message as $k => $v) {
            if ($v['name'] == $name || $v['code'] == $name) {
                return $k;
            }
        }
        return null;
    }

    public static function getMessage ($code)
    {
        return isset(self::$message[$code]) ? self::$message[$code]['name'] : $code;
    }

    public static function format ($code)
    {
        return isset(self::$message[$code]) ? $code : null;
    }

}
