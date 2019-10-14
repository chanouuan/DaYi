<?php
/**
 * 性别
 */

namespace app\common;

class Gender
{

    const SECRECY = 0;
    const MAN     = 1;
    const WOMAN   = 2;

    static $message = [
        0 => '保密',
        1 => '男',
        2 => '女'
    ];

    /**
     * 效验年龄
     * @param $age 年龄 1.01=一岁零一个月
     * @return float
     */
    public static function validationAge ($age)
    {
        if (empty($age)) {
            return null;
        }
        $age   = region_number($age, 0, 0, 150, 0);
        $year  = intval($age);
        $month = bcsub($age, $year, 2) * 100;
        if ($month > 0) {
            $year += $month > 11 ? bcdiv($month, 12) : 0;
            $month = $month % 12;
            $age   = $year + bcdiv($month, 100, 2);
        }
        return $age;
    }

    /**
     * 显示年龄
     * @param $age 年龄
     * @return string
     */
    public static function showAge ($age)
    {
        $year  = intval($age);
        $month = bcsub($age, $year, 2) * 100;
        $str = [];
        if ($year > 0) {
            $str[] = $year . '岁';
        }
        if ($month > 0) {
            $str[] = $month . '个月';
        }
        return $str ? implode('零', $str) : '无';
    }

    /**
     * 获取出生日期
     * @param $age 年龄
     * @return string
     */
    public static function getBirthDay ($age)
    {
        if (empty($age)) {
            return null;
        }
        $year  = intval($age);
        $month = bcsub($age, $year, 2) * 100;
        $time  = mktime(0, 0, 0, date('m', TIMESTAMP) - $month, date('d', TIMESTAMP), date('Y', TIMESTAMP) - $year);
        return date('Y-m-1', $time);
    }

    /**
     * 根据出生日期获取年龄
     * @param $birthday 出生日期
     * @return array
     */
    public static function getAgeByBirthDay ($birthday)
    {
        if (empty($birthday)) {
            return [];
        }
        $birthday = strtotime($birthday);
        if (!$birthday || $birthday > TIMESTAMP) {
            return [];
        }
        $start_time = new \DateTime(date('Y-m-d H:i:s', $birthday));
        $end_time   = new \DateTime(date('Y-m-d H:i:s', TIMESTAMP));
        $interval   = $end_time->diff($start_time);
        return [
            'y' => $interval->y,
            'm' => $interval->m,
        ];
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
