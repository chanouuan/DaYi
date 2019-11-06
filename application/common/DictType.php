<?php
/**
 * 字典数据类型
 */

namespace app\common;

use app\library\DB;

class DictType
{

    const UNIT_1 = 1;
    const UNIT_2 = 2;
    const UNIT_3 = 3;
    const UNIT_4 = 4;
    const UNIT_5 = 5;
    const TITLE  = 6;

    static $message = [
        1 => '剂量单位',
        2 => '制剂单位',
        3 => '库存单位',
        4 => '材料单位',
        5 => '项目单位',
        6 => '职位'
    ];

    /**
     * 获取字典数据
     * @return array
     */
    public static function getCacheDict ()
    {
        if (false === F('dict')) {
            $res = DB::getInstance()->table('dayi_dict')->field('id,type,name')->where(['status' => 1])->select();
            $list = [];
            foreach ($res as $k => $v) {
                $list[$v['type']][$v['id']] = $v['name'];
            }
            F('dict', $list);
            return $list;
        }
        return F('dict');
    }

    /**
     * 获取药品单位
     * @return array
     */
    public static function getDrugUnit ()
    {
        $list = self::getCacheDict();
        return [
            self::UNIT_1 => isset($list[self::UNIT_1]) ? array_values($list[self::UNIT_1]) : [],
            self::UNIT_2 => isset($list[self::UNIT_2]) ? array_values($list[self::UNIT_2]) : [],
            self::UNIT_3 => isset($list[self::UNIT_3]) ? array_values($list[self::UNIT_3]) : [],
            self::UNIT_4 => isset($list[self::UNIT_4]) ? array_values($list[self::UNIT_4]) : []
        ];
    }

    /**
     * 获取诊疗项目单位
     * @return array
     */
    public static function getTreatmentUnit ()
    {
        $list = self::getCacheDict();
        return isset($list[self::UNIT_5]) ? array_values($list[self::UNIT_5]) : [];
    }

    /**
     * 获取职位
     * @return array
     */
    public static function getTitle ()
    {
        $list = self::getCacheDict();
        return isset($list[self::TITLE]) ? array_values($list[self::TITLE]) : [];
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
