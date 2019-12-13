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
     * 显示年龄
     * @param $age 年龄（月）
     * @return string
     */
    public static function showAge ($age)
    {
        $age = intval($age);
        if ($age <= 0) {
            return '';
        }
        $year  = bcdiv($age, 12);
        $month = bcmod($age, 12);
        $str = [];
        if ($year > 0) {
            $str[] = $year . '岁';
        }
        if ($month > 0) {
            $str[] = $month . '个月';
        }
        return $str ? implode('', $str) : '';
    }

    /**
     * 获取出生日期
     * @param $age 年龄（月）
     * @return string
     */
    public static function getBirthDay ($age)
    {
        $age = intval($age);
        if ($age <= 0) {
            return null;
        }
        $year  = bcdiv($age, 12);
        $month = bcmod($age, 12);
        $time  = mktime(0, 0, 0, date('m', TIMESTAMP) - $month, date('d', TIMESTAMP), date('Y', TIMESTAMP) - $year);
        return date('Y-m-1', $time);
    }

    /**
     * 根据出生日期获取年龄（月）
     * @param $birthday 出生日期
     * @return array
     */
    public static function getAgeByBirthDay ($birthday)
    {
        if (!$birthday) {
            return 0;
        }
        $birthday = strtotime($birthday);
        if (!$birthday || $birthday > TIMESTAMP) {
            return 0;
        }
        $start_time = new \DateTime(date('Y-m-d H:i:s', $birthday));
        $end_time   = new \DateTime(date('Y-m-d H:i:s', TIMESTAMP));
        $interval   = $end_time->diff($start_time);
        return intval($interval->y) * 12 + intval($interval->m);
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
