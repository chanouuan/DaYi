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
     * 换算成库存数量
     * @param $drug_type 药品类型
     * @param $amount 药品数量
     * @param $basic_amount 制剂数量
     * @return int
     */
    public static function convertStockAmount ($drug_type, $amount, $basic_amount)
    {
        if (!$amount || !self::isWestNeutralDrug($drug_type)) {
            return $amount;
        }
        return intval(bcdiv($amount, $basic_amount));
    }

    /**
     * 显示药品数量
     * @param $drug_type 药品类型
     * @param $amount 药品数量
     * @param $basic_amount 制剂数量
     * @param $dispense_unit 库存单位
     * @param $basic_unit 制剂单位
     * @return string
     */
    public static function showAmount ($drug_type, $amount, $basic_amount, $dispense_unit, $basic_unit)
    {
        if (!$amount || !self::isWestNeutralDrug($drug_type)) {
            return $amount . $dispense_unit;
        }
        $left  = abs(bcdiv($amount, $basic_amount));
        $right = abs(bcmod($amount, $basic_amount));
        $str   = [];
        if ($amount < 0) {
            $str[] = '-';
        }
        if ($left > 0) {
            $str[] = $left;
            $str[] = $dispense_unit;
        }
        if ($right > 0) {
            $str[] = $right;
            $str[] = $basic_unit;
        }
        return implode('', $str);
    }

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
