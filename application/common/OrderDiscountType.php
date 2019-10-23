<?php
/**
 * 线下收费优惠类型
 */

namespace app\common;

class OrderDiscountType
{

    const NONE  = 0;
    const RATIO = 1;
    const CASH  = 2;

    static $message = [
        0 => '无优惠',
        1 => '折扣比例',
        2 => '优惠金额'
    ];
    /**
     * 获取优惠后的金额
     * @param $code 优惠类型
     * @param $disval 优惠变量值
     * @param $money 原价
     * @return int
     */
    public static function getDiscountMoney ($code, $disval, $money)
    {
        switch ($code) {
            case self::RATIO:
                $money = bcmul($money, bcdiv(region_number(intval($disval), 0, 0, 100, 100), 100, 2));
                break;
            case self::CASH:
                $money = bcsub($money, region_number(intval($disval * 100), 0, 0, $money, $money));
                break;
        }
        return intval($money);
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
