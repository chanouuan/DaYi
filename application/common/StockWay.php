<?php
/**
 * 出入库方式
 */

namespace app\common;

class StockWay
{

    static $message = [
        1 => '采购入库',
        2 => '科室退药',
        3 => '调拨入库',
        4 => '其他入库',
        30 => '退货出库',
        31 => '科室领药',
        32 => '报损出库',
        33 => '调拨出库',
        34 => '赠品',
        35 => '其他出库'
    ];

    /**
     * 获取入库方式
     * @return array
     */
    public static function getPull ()
    {
        $list = [];
        foreach (self::$message as $k => $v) {
            if ($k < 30) {
                $list[$k] = $v;
            } else {
                break;
            }
        }
        return $list;
    }

    /**
     * 获取出库方式
     * @return array
     */
    public static function getPush ()
    {
        $list = [];
        foreach (self::$message as $k => $v) {
            if ($k > 29) {
                $list[$k] = $v;
            }
        }
        return $list;
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