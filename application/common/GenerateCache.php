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
        $list = self::getPermissions();
        foreach ($permissions as $k => $v) {
            $permissions[$k] = isset($list[$v]) ? $list[$v] : null;
        }
        return array_values(array_filter($permissions));
    }

    /**
     * 获取所有权限
     * @return array
     */
    public static function getPermissions ()
    {
        if (false === F('permissions')) {
            $list = DB::getInstance()
                ->table('admin_permissions')
                ->field('id,name')
                ->select();
            $list = array_column($list, 'name', 'id');
            F('permissions', $list);
            return $list;
        }
        return F('permissions');
    }

    /**
     * 清除诊所信息
     * @param $clinic_id 诊所ID
     * @return bool
     */
    public static function removeClinic ($clinic_id)
    {
        if (!$clinic_id) {
            return false;
        }
        $keyName = 'clinic_chunk' . ($clinic_id % 100);
        return F($keyName, null);
    }

    /**
     * 获取诊所信息
     * @param $clinic_id 诊所ID
     * @return array
     */
    public static function getClinic ($clinic_id)
    {
        if (!$clinic_id) {
            return [];
        }

        $keyName = 'clinic_chunk' . ($clinic_id % 100);

        if (false === F($keyName)) {
            if (!$clinicInfo = DB::getInstance()
                ->table('dayi_clinic')
                ->field('id,name,db_instance,db_chunk,is_ds,is_cp,is_rp,is_pc,vip_level,expire_date,daily_cost,pay_sheet_size,status')
                ->where(['id' => $clinic_id])
                ->limit(1)
                ->find()) {
                return [];
            }
            F($keyName, [ $clinicInfo['id'] => $clinicInfo ]);
        } else {
            $list = F($keyName);
            if (isset($list[$clinic_id])) {
                $clinicInfo = $list[$clinic_id];
            } else {
                if (!$clinicInfo = DB::getInstance()
                    ->table('dayi_clinic')
                    ->field('id,name,db_instance,db_chunk,is_ds,is_cp,is_rp,is_pc,vip_level,expire_date,daily_cost,pay_sheet_size,status')
                    ->where(['id' => $clinic_id])
                    ->limit(1)
                    ->find()) {
                    return [];
                }
                $list[$clinicInfo['id']] = $clinicInfo;
                F($keyName, $list);
            }
            unset($list);
        }

        // 检查过期
        if (isset($clinicInfo['expire_date'])) {
            $clinicInfo['vip_expire'] = (!$clinicInfo['expire_date'] || strtotime($clinicInfo['expire_date'] . '23:59:59') > TIMESTAMP) ? 0 : 1;
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
