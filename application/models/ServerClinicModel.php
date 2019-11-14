<?php

namespace app\models;

use app\common\DrugType;
use app\common\NoteCategory;
use app\common\NoteFrequency;
use app\common\NoteUsage;
use app\common\DrugDosage;
use app\common\DictType;
use app\common\OrderPayWay;
use app\common\NoteAllergy;
use app\common\StockType;
use app\common\StockWay;
use Crud;

class ServerClinicModel extends Crud {

    /**
     * 获取出入库方式
     * @return array
     */
    public function getStockWayEnum ()
    {
        $list = [];
        foreach (StockWay::getPull() as $k => $v) {
            $list[StockType::PULL][] = [
                'id' => $k,
                'name' => $v
            ];
        }
        foreach (StockWay::getPush() as $k => $v) {
            $list[StockType::PUSH][] = [
                'id' => $k,
                'name' => $v
            ];
        }
        return success($list);
    }

    /**
     * 获取员工职位
     * @return array
     */
    public function getEmployeeTitle ()
    {
        return success(DictType::getTitle());
    }

    /**
     * 版本号检查
     * @param $version 版本号
     * @return array
     */
    public function versionCheck ($version)
    {
        return (new VersionModel())->check('qt', $version);
    }

    /**
     * 获取医生列表
     * @return array
     */
    public function getDoctorList ($user_id, $all)
    {
        // 获取用户信息
        $adminModel = new AdminModel();
        $userInfo = $adminModel->checkAdminInfo($user_id);
        if ($userInfo['errorcode'] !== 0) {
            return $userInfo;
        }
        $userInfo = $userInfo['result'];
        // 获取医生
        $title = $all ? null : '医师';
        return success($adminModel->getUserByDoctor($userInfo['clinic_id'], $title));
    }

    /**
     * 获取过敏史
     * @return array
     */
    public function getAllergyEnum ()
    {
        return success(array_values(NoteAllergy::$message));
    }

    /**
     * 获取药品剂型
     * @return array
     */
    public function getDosageEnum ()
    {
        $list = [];
        foreach (DrugDosage::getNeutral() as $k => $v) {
            $list[DrugType::NEUTRAL][] = [
                'id' => $k,
                'name' => $v
            ];
        }
        foreach (DrugDosage::getWestern() as $k => $v) {
            $list[DrugType::WESTERN][] = [
                'id' => $k,
                'name' => $v
            ];
        }
        return success($list);
    }

    /**
     * 获取药品单位
     * @return array
     */
    public function getDrugUnitEnum ()
    {
        return success(DictType::getDrugUnit());
    }

    /**
     * 获取诊疗项目单位
     * @return array
     */
    public function getTreatmentUnitEnum ()
    {
        return success(DictType::getTreatmentUnit());
    }

    /**
     * 获取药品用法
     * @return array
     */
    public function getUsageEnum ()
    {
        $list = [];
        foreach (NoteUsage::getWesternUsage() as $k => $v) {
            $list[NoteCategory::WESTERN][] = [
                'id' => $k,
                'name' => $v
            ];
        }
        foreach (NoteUsage::getChineseUsage() as $k => $v) {
            $list[NoteCategory::CHINESE][] = [
                'id' => $k,
                'name' => $v
            ];
        }
        return success($list);
    }

    /**
     * 获取药品频率
     * @return array
     */
    public function getNoteFrequencyEnum ()
    {
        $list = [];
        foreach (NoteFrequency::$message as $k => $v) {
            $list[] = [
                'id' => $k,
                'name' => $v['name'],
                'daily_count' => $v['daily_count']
            ];
        }
        return success($list);
    }

    /**
     * 获取支付方式
     * @return array
     */
    public function getLocalPayWay ()
    {
        $list = [];
        $data = OrderPayWay::getLocalPayWay();
        foreach ($data as $k => $v) {
            $list[] = [
                'id' => $k,
                'name' => $v
            ];
        }
        unset($data);
        return success($list);
    }

    /**
     * 搜索患者
     * @return array
     */
    public function searchPatient ($post)
    {
        $post['name'] = trim_space($post['name']);
        if (!$post['name'] || preg_match('/^\d+$/', $post['name'])) {
            return success([]);
        }
        if (!$list = (new PatientModel())->search($post['name'])) {
            return success([]);
        }
        return success([
            'columns' => [
                ['key' => 'name', 'value' => '姓名'],
                ['key' => 'sex', 'value' => '性别'],
                ['key' => 'age', 'value' => '年龄'],
                ['key' => 'telephone', 'value' => '手机']
            ],
            'rows' => $list
        ]);
    }

    /**
     * 搜索批次
     * @return array
     */
    public function searchBatch ($post)
    {
        $post['clinic_id'] = intval($post['clinic_id']);
        $post['name']      = trim_space($post['name']);
        if (!$post['clinic_id'] || !$post['name']) {
            return success([]);
        }
        if (!$list = (new StockModel(null, $post['clinic_id']))->searchBatch($post)) {
            return success([]);
        }
        return success([
            'columns' => [
                ['key' => 'name', 'value' => '名称'],
                ['key' => 'package_spec', 'value' => '规格'],
                ['key' => 'amount_unit', 'value' => '库存'],
                ['key' => 'purchase_price', 'value' => '购入价'],
                ['key' => 'batch_number', 'value' => '批号'],
                ['key' => 'manufactor_name', 'value' => '生产商']
            ],
            'rows' => $list
        ]);
    }

    /**
     * 搜索药品
     * @return array
     */
    public function searchDrug ($post)
    {
        $post['clinic_id'] = intval($post['clinic_id']);
        $post['name']      = trim_space($post['name']);
        if (!$post['clinic_id'] || !$post['name']) {
            return success([]);
        }
        $post['drug_type'] = $post['drug_type'] == DrugType::WESTERN ? [DrugType::WESTERN, DrugType::NEUTRAL] : intval($post['drug_type']);
        if (!$list = (new DrugModel(null, $post['clinic_id']))->search($post)) {
            return success([]);
        }
        return success([
            'columns' => [
                ['key' => 'name', 'value' => '名称'],
                ['key' => 'package_spec', 'value' => '规格'],
                ['key' => 'price', 'value' => '零售价'],
                ['key' => 'dispense_unit', 'value' => '单位'],
                ['key' => 'amount', 'value' => '库存'],
                ['key' => 'manufactor_name', 'value' => '生产商']
            ],
            'rows' => $list
        ]);
    }

    /**
     * 药品查询
     * @return array
     */
    public function searchDrugDict ($post)
    {
        $post['name'] = trim_space($post['name']);
        if (!$post['name']) {
            return success([]);
        }
        $drugType = $post['drug_type'] == DrugType::WESTERN ? ['西药', '中成药'] : '草药';
        if (!$list = (new DrugDictModel())->searchDict($drugType, $post['name'])) {
            return success([]);
        }
        $columns = [
            ['key' => 'approval_num', 'value' => '国药准字'],
            ['key' => 'name', 'value' => '名称'],
            ['key' => 'package_spec', 'value' => '规格'],
            ['key' => 'barcode', 'value' => '条形码']
        ];
        if ($drugType == '草药') {
            $columns = [
                ['key' => 'name', 'value' => '名称'],
                ['key' => 'package_spec', 'value' => '规格'],
                ['key' => 'dispense_unit', 'value' => '单位'],
                ['key' => 'retail_price', 'value' => '售价']
            ];
        }
        return success([
            'columns' => $columns,
            'rows'    => $list
        ]);
    }

    /**
     * 搜索诊疗项目
     * @return array
     */
    public function searchTreatmentSheet ($post)
    {
        $post['clinic_id'] = intval($post['clinic_id']);
        $post['name']      = trim_space($post['name']);
        if (!$post['clinic_id'] || !$post['name']) {
            return success([]);
        }
        $list = (new TreatmentModel())->search($post['clinic_id'], $post['name']);
        return success([
            'columns' => [
                ['key' => 'ident', 'value' => '编码'],
                ['key' => 'name', 'value' => '名称'],
                ['key' => 'price', 'value' => '单价']
            ],
            'rows' => $list
        ]);
    }

    /**
     * 获取登录账号信息
     * @return array
     */
    public function getUserProfile ($user_id)
    {
        // 用户获取
        $userInfo = (new AdminModel())->checkAdminInfo($user_id);
        if ($userInfo['errorcode'] !== 0) {
            return $userInfo;
        }
        $userInfo = $userInfo['result'];

        // 获取诊所信息
        $userInfo['clinic_info'] = (new ClinicModel())->find(['id' => $userInfo['clinic_id']], 'id,name,status');

        // 消息
        $userInfo['unread_count'] = rand(1, 10); // 未读消息数

        return success($userInfo);
    }

}
