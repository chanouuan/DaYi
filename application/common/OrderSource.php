<?php
/**
 * 订单来源
 */

namespace app\common;

use CommonEnum;

class OrderSource extends CommonEnum
{

    const DOCTOR      = 1;
    const BUG_DRUG    = 2;
    const APPOINTMENT = 3;

    static $message = [
        1 => '医生处方',
        2 => '购药',
        3 => '网上预约'
    ];

}
