<?php
/**
 * 订单状态
 */

namespace app\common;

use CommonEnum;

class OrderStatus extends CommonEnum
{

    const REFUNDING = -2;
    const REFUND    = -1;
    const NOPAY     = 0;
    const PAY       = 1;

    static $message = [
        -2 => '退费中',
        -1 => '已退费',
        0  => '未收费',
        1  => '已收费'
    ];

}
