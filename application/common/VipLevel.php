<?php
/**
 * vip等级
 */

namespace app\common;

class VipLevel
{

    const BETA   = 0;
    const SIMPLE = 1;
    const BASE   = 2;
    const HIGH   = 3;

    static $message = [
        0 => '试用中',
        1 => '基础版',
        2 => '高级版',
        3 => '豪华版'
    ];

    /**
     * 获取剩余时间
     * @return string
     */
    public static function getUseDate ($expire_date)
    {
        $expire_date = strtotime($expire_date . ' 23:59:59');
        if ($expire_date <= TIMESTAMP) {
            return '已到期';
        }
        return '剩余' . get_diff_time(TIMESTAMP, $expire_date, ['y'=>'年', 'm'=>'月', 'd'=>'天', 'h'=>'小时', 'i'=>'分']);
    }

    /**
     * 获取剩余天数
     * @return int
     */
    public static function getUseDays ($expire_date)
    {
        $expire_date = strtotime($expire_date . ' 23:59:59');
        if ($expire_date <= TIMESTAMP) {
            return 0;
        }
        return ceil(bcdiv($expire_date - TIMESTAMP, 86400, 6));
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
