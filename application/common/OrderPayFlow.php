<?php
/**
 * 资金流出操作类型
 */

namespace app\common;

use CommonEnum;

class OrderPayFlow extends CommonEnum
{

    const CHARGE = 1;
    const REFUND = 2;

    static $message = [
        1 => '收费',
        2 => '退费'
    ];

}
