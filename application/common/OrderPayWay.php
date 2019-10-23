<?php
/**
 * 订单支付方式
 */

namespace app\common;

class OrderPayWay
{

    const CASH     = 'cash';
    const UNIONPAY = 'unionpay';
    const YIBAO    = 'yibao';
    const WEIXIN   = 'weixin';
    const ALIPAY   = 'alipay';
    const MULTIPAY = 'multipay';
    const WXPAYJS  = 'wxpayjs';
    const ALIPAYJS = 'alipayjs';

    static $message = [
        /* 线下付款 */
        'cash'     => '现金',
        'unionpay' => '银联卡',
        'yibao'    => '医保卡',
        'weixin'   => '微信',
        'alipay'   => '支付宝',
        'multipay' => '多种支付',
        /* 线上付款 */
        'wxpayjs'  => '微信',
        'alipayjs' => '支付宝'
    ];

    /**
     * 是否线下付款
     * @param $code
     * @return bool
     */
    public static function isLocalPayWay ($code)
    {
        return in_array($code, [
            self::CASH, self::UNIONPAY, self::YIBAO, self::WEIXIN, self::ALIPAY
        ]);
    }

    /**
     * 获取线下付款方式
     * @param $code
     * @return bool
     */
    public static function getLocalPayWay ()
    {
        $list = [];
        foreach (self::$message as $k => $v) {
            if (self::isLocalPayWay($k)) {
                $list[$k] = $v;
            }
        }
        return $list;
    }

    /**
     * 获取线上付款方式
     * @param $code
     * @return bool
     */
    public static function getNetworkPayWay ()
    {
        $list = [];
        foreach (self::$message as $k => $v) {
            if (!self::isLocalPayWay($k)) {
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
