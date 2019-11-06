<?php
/**
 * 提成
 */

namespace app\common;

class Royalty
{

    const NONE  = 0;
    const RATIO = 1;
    const FIXED = 2;

    static $message = [
        0 => '无',
        1 => '提成比例',
        2 => '固定金额'
    ];

    /**
     * 获取优惠后的金额 (分)（此函数可用于优惠金额计算）
     * @param $code 类型
     * @param $ratio 变量值 比例 (0-100) 金额 (元)
     * @param $money 原价 (分)
     * @return int
     */
    public static function getDiscountMoney ($code, $ratio, $money)
    {
        switch ($code) {
            case self::RATIO:
                $money = bcmul($money, bcdiv(region_number(intval($ratio), 0, 0, 100, 100), 100, 2));
                break;
            case self::FIXED:
                $money = bcsub($money, region_number(bcmul($ratio, 100), 0, 0, $money, $money));
                break;
        }
        return $money;
    }

    /**
     * 获取提成金额 (分)
     * @param $code 类型
     * @param $ratio 变量值 比例 (0-100) 金额 (分)
     * @param $money 原价 (分)
     * @return int
     */
    public static function getRoyaltyPrice ($code, $ratio, $money)
    {
        switch ($code) {
            case self::RATIO:
                return bcmul($money, bcdiv(region_number(intval($ratio), 0, 0, 100, 100), 100, 2));
            case self::FIXED:
                return region_number(intval($ratio), 0, 0, $money, $money);
        }
        return 0;
    }

    /**
     * 检查提成变量值
     * @param $code 类型
     * @param $ratio 变量值 比例 (0-100) 金额 (元)
     * @param $money 原价 (分)
     * @return int
     */
    public static function checkRoyaltyRatio ($code, $ratio, $money)
    {
        switch ($code) {
            case self::RATIO:
                return region_number(intval($ratio), 0, 0, 100, 100);
            case self::FIXED:
                return region_number(bcmul($ratio, 100), 0, 0, $money, $money);
        }
        return null;
    }

    /**
     * 显示提成变量值
     * @param $code 类型
     * @param $ratio 变量值 比例 (0-100) 金额 (分)
     * @param $unit 是否显示单位
     * @return int
     */
    public static function showRoyaltyRatio ($code, $ratio, $unit = false)
    {
        switch ($code) {
            case self::RATIO:
                return $unit ? ($ratio . '%') : $ratio;
            case self::FIXED:
                return $unit ? (($ratio / 100) . '元') : ($ratio / 100);
        }
        return $unit ? '无' : '';
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
