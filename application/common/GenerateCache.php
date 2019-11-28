<?php
/**
 * 缓存读取
 */

namespace app\common;

use app\library\DB;
use app\common\VipLevel;

class GenerateCache
{

    /**
     * 权限映射
     * @param $permissions 权限id
     * @return array
     */
    public static function mapPermissions (array $permissions)
    {
        if (empty($permissions)) {
            return [];
        }
        if (false === F('map_permissions')) {
            $list = DB::getInstance()
                ->table('admin_permissions')
                ->field('id,name')
                ->select();
            $list = array_column($list, 'name', 'id');
            F('map_permissions', $list);
        }
        $list = F('map_permissions');
        foreach ($permissions as $k => $v) {
            $permissions[$k] = isset($list[$v]) ? $list[$v] : null;
        }
        return array_values(array_filter($permissions));
    }

    /**
     * 获取诊所信息
     * @param $clinic_id 诊所ID
     * @return array
     */
    public static function getClinic ($clinic_id, $field = null)
    {
        static $clinicInfo = null;
        if (!$clinic_id) {
            return [];
        }
        if ($clinicInfo) {
            return $clinicInfo;
        }
        $clinicInfo = DB::getInstance()
            ->table('dayi_clinic')
            ->field($field ? $field : 'id,db_instance,db_chunk,is_ds,is_cp,is_rp,vip_level')
            ->where(['id' => $clinic_id])
            ->limit(1)
            ->find();
        if ($clinicInfo) {
            // vip等级0、1不提供库存功能，所以关闭库存相关配置
            if (isset($clinicInfo['vip_level'])) {
                if ($clinicInfo['vip_level'] <= VipLevel::SIMPLE) {
                    $clinicInfo['is_ds'] = 0;
                    $clinicInfo['is_cp'] = 0;
                    $clinicInfo['is_rp'] = 0;
                }
            }
            // 检查过期
            if (isset($clinicInfo['expire_date'])) {
                $clinicInfo['vip_expire'] = strtotime($clinicInfo['expire_date'] . '23:59:59') > TIMESTAMP ? 0 : 1;
            }
        }
        return $clinicInfo;
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
