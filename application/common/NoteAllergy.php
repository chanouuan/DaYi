<?php
/**
 * 处方过敏史
 */

namespace app\common;

class NoteAllergy
{

    static $message = [
        1 => '阿司匹林',
        2 => '青霉素',
        3 => '卡那霉素',
        4 => '磺胺嘧啶',
        5 => '布洛芬',
        6 => '链霉素',
        7 => '头孢',
        8 => '磺胺噻唑',
        9 => '氯丙嗪',
        10 => '氨基苄青霉素',
        11 => '普鲁卡因',
        12 => '长效磺胺',
        13 => '复方新诺明',
        14 => '苯巴比妥'
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
