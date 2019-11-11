<?php
/**
 * 缓存读取
 */

namespace app\common;

use app\library\DB;

class GenerateCache
{

    /**
     * 获取诊所
     * @param $clinic_id 诊所ID
     * @return array
     */
    public static function getClinic ($clinic_id)
    {
        static $clinics = [];
        if (!$clinic_id) {
            return [];
        }
        if (isset($clinics[$clinic_id]) && $clinics[$clinic_id]) {
            return $clinics[$clinic_id];
        }
        $clinics[$clinic_id] = DB::getInstance()
            ->table('dayi_clinic')
            ->field('id,name,db_instance,db_chunk,is_ds,is_cp,is_rp')
            ->where(['id' => $clinic_id])
            ->limit(1)
            ->find();
        return $clinics[$clinic_id];
    }

    /**
     * 获取诊所分区表
     * @param $clinic_id 诊所ID
     * @return array
     */
    public static function getClinicPartition ($clinic_id)
    {
        $clinicInfo = self::getClinic($clinic_id);
        return $clinicInfo ? [$clinicInfo['db_instance'], $clinicInfo['db_chunk']] : [];
    }

}
