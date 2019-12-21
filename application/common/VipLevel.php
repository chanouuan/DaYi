<?php
/**
 * vip等级
 */

namespace app\common;

class VipLevel
{

    const BETA   = 0;
    const SIMPLE = 1;
    const BASE   = 2;
    const HIGH   = 3;
    const LIMIT  = 4;

    static $message = [
        0 => '试用版',
        1 => '基础版',
        2 => '高级版',
        3 => '豪华版',
        4 => '限量版'
    ];

    /**
     * 录音文件升配后是否可以下载
     * @param $clinic_id
     * @param $create_time 录音创建时间
     * @return bool
     */
    public static function isDownloadVoiceByUp ($clinic_id, $create_time)
    {
        if (!$clinicInfo = GenerateCache::getClinic($clinic_id)) {
            return false;
        }

        if ($clinicInfo['vip_expire']) {
            return true;
        }

        if ($clinicInfo['vip_level'] === self::HIGH) {
            return false;
        }

        $days = floor(bcdiv(TIMESTAMP - strtotime($create_time), 86400, 6));

        return $days < self::getVoiceSaveTime(self::HIGH);
    }

    /**
     * 录音文件剩余保存时间
     * @param $clinic_id
     * @param $create_time 录音创建时间
     * @return string
     */
    public static function checkVoiceSaveTime ($clinic_id, $create_time)
    {
        if (!$clinicInfo = GenerateCache::getClinic($clinic_id)) {
            return 0;
        }

        if ($clinicInfo['vip_expire']) {
            return 0;
        }

        // 已保存天数
        $days = floor(bcdiv(TIMESTAMP - strtotime($create_time), 86400, 6));

        $allowSave = self::getVoiceSaveTime($clinicInfo['vip_level']);

        if ($days >= $allowSave) {
            return 0;
        }

        return $allowSave - $days;
    }

    /**
     * 获取录音文件保存时间
     * @param $vip_level vip等级
     * @return int
     */
    public static function getVoiceSaveTime ($vip_level)
    {
        $allowSave = [
            self::BETA   => 3,
            self::SIMPLE => 7,
            self::BASE   => 30,
            self::HIGH   => 180,
            self::LIMIT  => 3
        ];
        return isset($allowSave[$vip_level]) ? $allowSave[$vip_level] : 0;
    }

    /**
     * 获取剩余时间
     * @return string
     */
    public static function getUseDate ($expire_date)
    {
        if (!$expire_date) {
            return '无限期';
        }
        $expire_date = strtotime($expire_date . ' 23:59:59');
        if ($expire_date <= TIMESTAMP) {
            return '已到期';
        }
        return '剩余' . get_diff_time(TIMESTAMP, $expire_date, ['y'=>'年', 'm'=>'月', 'd'=>'天', 'h'=>'小时', 'i'=>'分']);
    }

    /**
     * 获取剩余天数
     * @return int
     */
    public static function getUseDays ($expire_date)
    {
        if (!$expire_date) {
            return 0;
        }
        $expire_date = strtotime($expire_date . ' 23:59:59');
        if ($expire_date <= TIMESTAMP) {
            return 0;
        }
        return ceil(bcdiv($expire_date - TIMESTAMP, 86400, 6));
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
