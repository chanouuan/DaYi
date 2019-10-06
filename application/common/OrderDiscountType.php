<?php
/**
 * 线下收费优惠类型
 */

namespace app\common;

use CommonEnum;

class OrderDiscountType extends CommonEnum
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
     * @parm $money 原价
     * @return int
     */
    public static function getDiscountMoney ($code, $disval, $money)
    {
        switch ($code) {
            case self::RATIO:
                $money = bcmul($money, bcdiv(region_number(intval($disval), 0, 0, 100, 100), 100));
                break;
            case self::CASH:
                $money = bcsub($money, region_number(intval($disval), 0, 0, $money, $money));
                break;
        }
        return intval($money);
    }


}
