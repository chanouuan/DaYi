<?php
/**
 * 药品状态
 */

namespace app\common;

use CommonEnum;

class DrugStatus extends CommonEnum
{

    const NOSALES  = 0;
    const OFFSALES = 1;
    const ONSALES  = 2;

    static $message = [
        0 => '禁用',
        1 => '未采购',
        2 => '在售'
    ];

}
