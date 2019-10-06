<?php
/**
 * 一般状态
 */

namespace app\common;

use CommonEnum;

class CommonStatus extends CommonEnum
{

    const NOT = 0;
    const OK  = 1;

    static $message = [
        0 => '禁用',
        1 => '正常'
    ];

}
