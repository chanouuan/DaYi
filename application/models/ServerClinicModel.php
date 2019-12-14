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
use app\common\GenerateCache;
use app\common\OrderSource;
use Crud;

class ServerClinicModel extends Crud {

    /**
     * 首页统计
     * @return array
     */
    public function indexCount ($clinic_id)
    {
        $clinic_id = intval($clinic_id);

        $doctorOrderModel = new DoctorOrderModel(null, $clinic_id);
        $adminModel       = new AdminModel();

        // 今日就诊
        $jrjz = $doctorOrderModel->count([
            'clinic_id'   => $clinic_id,
            'status'      => ['>0'],
            'enum_source' => OrderSource::DOCTOR,
            'create_time' => ['>="' . date('Y-m-d', TIMESTAMP) . '"']
        ]);
        $jrjz = intval($jrjz);

        // 今日收入
        $jrsr = $doctorOrderModel->count([
            'clinic_id'   => $clinic_id,
            'status'      => ['>0'],
            'create_time' => ['>="' . date('Y-m-d', TIMESTAMP) . '"']
        ], 'sum(pay+discount-refund)');
        $jrsr = round_dollar($jrsr);

        // 累计患者
        $ljhz = $doctorOrderModel->count([
            'clinic_id'  => $clinic_id,
            'patient_id' => ['>0']
        ], null, 'patient_id');
        $ljhz = intval($ljhz);

        // 新增员工
        $xzyg = $adminModel->count([
            'clinic_id' => $clinic_id,
            'status'    => 1
        ]);
        $xzyg = intval($xzyg);

        // 销量占比
        $data = $doctorOrderModel->getDb()
            ->table('dayi_order_notes')
            ->field('category,count(*) as count')
            ->where([
                'clinic_id' => $clinic_id,
                'status' => ['>0']
            ])
            ->group('category')
            ->select();
        $xlzb = [];
        foreach ($data as $k => $v) {
            $xlzb[$v['category']] = [
                'name' => NoteCategory::getMessage($v['category']),
                'value' => $v['count']
            ];
        }
        foreach (NoteCategory::$message as $k => $v) {
            if (!isset($xlzb[$k])) {
                $xlzb[] = [
                    'name' => $v,
                    'value' => 0
                ]; 
            }
        }
        $xlzb = array_values($xlzb);

        // 患者年龄结构
        $data = $doctorOrderModel->select([
            'clinic_id'   => $clinic_id,
            'patient_id'  => ['>0'],
            'patient_age' => ['>0']
        ], 'CONVERT(patient_age, UNSIGNED) as patient_age,count(*) as count', null, null, 'patient_age');
        $data = array_column($data, 'count', 'patient_age');
        $category = [
            '<7岁'    => [0, 7],
            '7-13岁'  => [7, 13],
            '13-18岁' => [13, 18],
            '18-25岁' => [18, 25],
            '25-60岁' => [25, 60],
            '60-90岁' => [60, 90],
            '>90岁'   => [90, 1000]
        ];
        $hznljg = [];
        foreach ($category as $k => $v) {
            $hznljg[$k] = 0;
        }
        foreach ($data as $k => $v) {
            foreach ($category as $kk => $vv) {
                if ($k >= $vv[0] && $k < $vv[1]) {
                    $hznljg[$kk] += $v;
                    break;
                }
            }
        }

        // 近7天订单量
        $startDate = date('Y-m-d', TIMESTAMP - 6 * 86400);
        $data = $doctorOrderModel->select([
            'clinic_id'   => $clinic_id,
            'status'      => ['>0'],
            'create_time' => ['>="' . $startDate . '"']
        ], 'enum_source,left(create_time, 10) as date,count(*) as count', null, null, 'enum_source,date');
        $dataset = [];
        foreach ($data as $k => $v) {
            $dataset[$v['enum_source']][$v['date']] = $v['count'];
        }
        $startDate = strtotime($startDate);
        do {
            $date[] = date('Y-m-d', $startDate);
            $startDate += 86400;
        } while ($startDate < TIMESTAMP);
        $line = [
            $date, []
        ];
        foreach ($line[0] as $k => $v) {
            $line[0][$k] = substr($v, 5);
        }
        foreach ($dataset as $k => $v) {
            $set = [];
            foreach ($date as $vv) {
                $set[] = isset($v[$vv]) ? $v[$vv] : 0;
            }
            $line[1][OrderSource::getMessage($k)] = $set;
        }

        unset($data, $dataset);
        return success([
            [$jrjz, $jrsr, $ljhz, $xzyg],
            $xlzb,
            $hznljg,
            $line
        ]);
    }

    /**
     * 获取出入库方式
     * @return array
     */
    public function getStockWayEnum ($all = false)
    {
        $list = [];
        foreach (StockWay::getPull($all) as $k => $v) {
            $list[StockType::PULL][] = [
                'id' => $k,
                'name' => $v
            ];
        }
        foreach (StockWay::getPush($all) as $k => $v) {
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
     * 获取安装地址
     * @return array
     */
    public function getInstallAddr ()
    {
        return (new VersionModel())->getInstallAddr();
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
        $post['name'] = trim_space($post['name'], 0, 20);
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
        $post['name']      = trim_space($post['name'], 0, 20);
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
     * 搜索药品条形码
     * @return array
     */
    public function searchBarcode ($post)
    {
        $post['clinic_id'] = intval($post['clinic_id']);
        $post['barcode']   = trim_space($post['barcode'], 0, 20);
        if (!$post['clinic_id'] || !$post['barcode']) {
            return error('条形码参数错误');
        }
        if (!$list = (new DrugModel(null, $post['clinic_id']))->search($post, null, 1)) {
            return error('未找到「' . $post['barcode'] . '」匹配的药品');
        }
        $list = current($list);
        $list['note_category'] = DrugType::convertNoteCategory($list['drug_type']);
        return success($list);
    }

    /**
     * 搜索药品
     * @return array
     */
    public function searchDrug ($post)
    {
        $post['clinic_id'] = intval($post['clinic_id']);
        $post['name']      = trim_space($post['name'], 0, 20);
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
                ['key' => 'amount_unit', 'value' => '库存'],
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
        $post['name'] = trim_space($post['name'], 0, 20);
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
        $post['name']      = trim_space($post['name'], 0, 20);
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

        // 获取诊所信息
        $clinicInfo = GenerateCache::getClinic($userInfo['clinic_id']);
        $clinicInfo = array_merge($clinicInfo, (new ClinicModel())->find(['id' => $userInfo['clinic_id']], 'name,tel,address'));
        
        $userInfo['clinic_info'] = array_key_clean($clinicInfo, ['db_instance', 'db_chunk']);

        // 消息
        $userInfo['unread_count'] = 0; // 未读消息数

        return success($userInfo);
    }

}
