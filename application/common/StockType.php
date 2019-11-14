<?php
/**
 * 出入库类型
 */

namespace app\common;

class StockType
{

    const PULL = 1;
    const PUSH = 2;

    static $message = [
        1 => '入库',
        2 => '出库'
    ];

    /**
     * 获取操作符号
     * @param $code
     * @return string
     */
    public static function getOp ($code)
    {
        if ($code == self::PULL) {
            return '+';
        }
        if ($code == self::PUSH) {
            return '-';
        }
        return null;
    }

    /**
     * 获取自动出入库方式（自动发药 / 自动退药）
     * @param $code
     * @return string
     */
    public static function getAutoWay ($code)
    {
        if ($code == self::PULL) {
            return StockWay::AUTO_BACK;
        }
        if ($code == self::PUSH) {
            return StockWay::AUTO_PUT;;
        }
        return null;
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
