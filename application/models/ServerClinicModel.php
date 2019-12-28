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
use app\common\Royalty;
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
        ], 'patient_age/12 as patient_age,count(*) as count', null, null, 'patient_age');
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
     * 搜索疾病诊断
     * @return array
     */
    public function searchICD ($post)
    {
        $post['name'] = trim_space($post['name'], 0, 20);
        if (!$post['name']) {
            return success([]);
        }
        $list = (new ICDModel())->searchICD($post['name']);
        return success([
            'columns' => [
                ['key' => 'icd_code', 'value' => '编码'],
                ['key' => 'name', 'value' => '名称']
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

        // 是否已添加药品，前端会提示用户进入添加药品页
        $userInfo['have_drug'] = (new DrugModel(null, $userInfo['clinic_id']))->count(['clinic_id' => $userInfo['clinic_id']]) ? 1 : 0;

        // 消息
        $userInfo['unread_count'] = 0; // 未读消息数

        return success($userInfo);
    }

    /**
     * 下载 csv 模板
     * @return fixed
     */
    public function downloadCsvTemplate ($type)
    {
        if ($type == 1) {
            export_csv_data('西药信息模板', '"药品名称
(必填)","剂型
(必填)","规格
(必填)","剂量
(必填)","剂量单位
(必填)","制剂数量
(必填)","制剂单位
(必填)","库存单位
(必填)","零售价
(库存单位)
(必填)","药品类型
西药/中成药
(必填)","拆零价
(制剂单位)",国药准字,厂家,条形码,"药品编码
(仅用于医保对照)",默认用法,默认频率,"入库数量
(库存单位)","进货单价
(库存单位)"');
        }
        if ($type == 2) {
            export_csv_data('中药信息模板', '"中药名称
(必填)","规格
(必填)","零售单位
(必填)","零售价
(必填)",厂商,默认用法,入库数量,进货单价');
        }
        if ($type == 3) {
            export_csv_data('材料信息模板', '"材料名称
(必填)","规格
(必填)","零售单位
(必填)","零售价
(必填)",厂家,入库数量,进货单价');
        }
        if ($type == 4) {
            export_csv_data('诊疗项目模板', '"项目名称
(必填)","单价
(必填)","单位
(必填)",项目编号,"提成方式
(提成比例/固定金额)",提成数值');
        }
    }

    /**
     * 导入数据
     * @return array
     */
    public function importCsv ($user_id, $type)
    {
        set_time_limit(600);

        if ($_FILES['upfile']['error'] !== 0) {
            return error('上传文件为空');
        }
        if (strtolower(substr(strrchr($_FILES['upfile']['name'], '.'), 1)) != 'csv') {
            unlink($_FILES['upfile']['tmp_name']);
            return error('上传文件格式错误');
        }
        if ($_FILES['upfile']['size'] > 10000000) {
            unlink($_FILES['upfile']['tmp_name']);
            return error('上传文件太大');
        }

        // 转码
        if (false === file_put_contents($_FILES['upfile']['tmp_name'], $this->upEncodeUTF(file_get_contents($_FILES['upfile']['tmp_name'])))) {
            unlink($_FILES['upfile']['tmp_name']);
            return error($_FILES['upfile']['name'] . '转码失败');
        }

        if (false === ($handle = fopen($_FILES['upfile']['tmp_name'], "r"))) {
            unlink($_FILES['upfile']['tmp_name']);
            return error($_FILES['upfile']['name'] . '文件读取失败');
        }

        if ($type == 1) {
            $field = ['name','dosage_type','package_spec','dosage_amount','dosage_unit','basic_amount','basic_unit','dispense_unit','retail_price','drug_type','basic_price','approval_num','manufactor_name','barcode','drug_code','usages','frequency','amount','purchase_price'];
        } elseif ($type == 2) {
            $field = ['name','package_spec','dispense_unit','retail_price','manufactor_name','usages','amount','purchase_price'];
        } elseif ($type == 3) {
            $field = ['name','package_spec','dispense_unit','retail_price','manufactor_name','amount','purchase_price'];
        } elseif ($type == 4) {
            $field = ['name','price','unit','ident','royalty','royalty_ratio'];
        }
        
        $list = [];
        while(($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (empty($data)) {
                continue;
            }
            $arr = [];
            foreach ($field as $k => $v) {
                $arr[$v] = $this->upFilterData($data[$k]);
            }
            $list[] = $arr;
        }
        unset($arr, $list[0]);
        fclose($handle);
        unlink($_FILES['upfile']['tmp_name']);

        if (!count($list)) {
            return error('导入数据为空');
        }

        if ($type == 1) {
            $result = $this->importWestDrug($user_id, $list);
        } elseif ($type == 2) {
            $result = $this->importChineseDrug($user_id, $list);
        } elseif ($type == 3) {
            $result = $this->importMaterialDrug($user_id, $list);
        } elseif ($type == 4) {
            $result = $this->importTreatment($user_id, $list);
        }

        $list = null;
        return $result;
    }

    /**
     * 西药信息导入
     * @return array
     */
    private function importWestDrug($user_id, &$list)
    {
        $drugUnit = DictType::getDrugUnit();
        $drugDosage = [
            DrugType::NEUTRAL => DrugDosage::getNeutral(),
            DrugType::WESTERN => DrugDosage::getWestern()
        ];
        $pinYin = new \app\library\PinYin();
        // 效验数据
        foreach ($list as $k => $v) {
            if (!$v['name']) {
                return error('[第' . ($k + 1) . '行] 药品名称不能为空！');
            }
            $list[$k]['py_code'] = $pinYin->get_first_py($v['name']);
            $list[$k]['drug_type'] = array_search($v['drug_type'], DrugType::$message);
            if (!DrugType::isWestNeutralDrug($list[$k]['drug_type'])) {
                return error('[第' . ($k + 1) . '行] 药品类型填写不正确（请填写西药/中成药）！');
            }
            $list[$k]['dosage_type'] = array_search($v['dosage_type'], $drugDosage[$list[$k]['drug_type']]);
            if (!$list[$k]['dosage_type']) {
                return error('[第' . ($k + 1) . '行] 剂型名称填写不正确！<br/>(' . implode(',', $drugDosage[$list[$k]['drug_type']]) . ')');
            }
            if (!$v['package_spec']) {
                return error('[第' . ($k + 1) . '行] 规格不能为空！');
            }
            $list[$k]['dosage_amount'] = floatval($v['dosage_amount']);
            if ($list[$k]['dosage_amount'] <= 0) {
                return error('[第' . ($k + 1) . '行] 剂量必须大于0！');
            }
            if (!in_array($v['dosage_unit'], $drugUnit[DictType::UNIT_1])) {
                return error('[第' . ($k + 1) . '行] 剂量单位填写不正确！<br/>(' . implode(',', $drugUnit[DictType::UNIT_1]) . ')');
            }
            $list[$k]['basic_amount'] = intval($v['basic_amount']);
            if ($list[$k]['basic_amount'] <= 0) {
                return error('[第' . ($k + 1) . '行] 制剂数量必须大于0！');
            }
            if (!in_array($v['basic_unit'], $drugUnit[DictType::UNIT_2])) {
                return error('[第' . ($k + 1) . '行] 制剂单位填写不正确！<br/>(' . implode(',', $drugUnit[DictType::UNIT_2]) . ')');
            }
            if (!in_array($v['dispense_unit'], $drugUnit[DictType::UNIT_3])) {
                return error('[第' . ($k + 1) . '行] 库存单位填写不正确！<br/>(' . implode(',', $drugUnit[DictType::UNIT_3]) . ')');
            }
            $list[$k]['retail_price'] = floatval($v['retail_price']);
            if ($list[$k]['retail_price'] <= 0) {
                return error('[第' . ($k + 1) . '行] 零售价必须大于0！');
            }
            $list[$k]['basic_price'] = floatval($v['basic_price']);
            if ($list[$k]['basic_price'] < 0) {
                return error('[第' . ($k + 1) . '行] 拆零价必须大于0，若不拆零就不必填写！');
            }
            $list[$k]['amount'] = intval($v['amount']);
            if ($list[$k]['amount'] < 0) {
                return error('[第' . ($k + 1) . '行] 入库数量不能小于0！');
            }
            $list[$k]['purchase_price'] = floatval($v['purchase_price']);
            if ($list[$k]['purchase_price'] < 0) {
                return error('[第' . ($k + 1) . '行] 进货价不能小于0！');
            }
            $list[$k]['usages'] = array_search($v['usages'], NoteUsage::$message);
            $list[$k]['frequency'] = NoteFrequency::getCode($v['frequency']);
        }
        unset($drugUnit, $drugDosage);

        // 合并重复数据
        $list = array_values(array_column($list, null, 'name'));
        $rows = [
            'update' => 0,
            'insert' => 0
        ];

        // 添加药品
        $drugModel = new DrugModel($user_id);
        foreach ($list as $k => $v) {
            $list[$k]['id'] = $drugModel->drugExists(['name' => $v['name']]);
            $list[$k]['status'] = 1;
            // 保存药品
            $result = $drugModel->saveDrug($list[$k]);
            if ($result['errorcode'] !== 0) {
                return error('「' . $v['name'] . '」保存失败，请重试');
            }
            $rows[$list[$k]['id'] ? 'update' : 'insert'] ++;
            $list[$k]['id'] = $result['result']['drug_id'];
            if ($k % 10 == 0) {
                usleep(20000);
            }
        }

        // 入库
        $datails = [
            'stock_type' => StockType::PULL,
            'stock_date' => date('Y-m-d', TIMESTAMP),
            'stock_way' => StockWay::OTHER_PULL,
            'remark' => '数据导入',
            'details' => []
        ];
        foreach ($list as $k => $v) {
            if ($v['amount'] > 0) {
                $datails['details'][] = [
                    'drug_id' => $v['id'],
                    'amount' => $v['amount'],
                    'purchase_price' => $v['purchase_price'],
                    'manufactor_name' => $v['manufactor_name']
                ];
            }
        }
        if ($datails['details']) {
            $result = (new StockModel($user_id))->addStock($datails);
            if ($result['errorcode'] !== 0) {
                return error('库存保存失败，请重试');
            }
        }

        $result = [
            '新增药品信息 ' . $rows['insert'] . ' 条,更新药品信息 ' . $rows['update'] . ' 条'
        ];
        if ($datails['details']) {
            $result[] = '请在「库房管理->入库管理」入库确认';
        }
        unset($datails);
        return success(implode('<br/>', $result));
    }

    /**
     * 中药信息导入
     * @return array
     */
    private function importChineseDrug($user_id, &$list)
    {
        $drugUnit = DictType::getDrugUnit();
        $pinYin = new \app\library\PinYin();
        // 效验数据
        foreach ($list as $k => $v) {
            if (!$v['name']) {
                return error('[第' . ($k + 1) . '行] 中药名称不能为空！');
            }
            $list[$k]['py_code'] = $pinYin->get_first_py($v['name']);
            $list[$k]['drug_type'] = DrugType::CHINESE;
            if (!$v['package_spec']) {
                return error('[第' . ($k + 1) . '行] 规格不能为空！');
            }
            if (!in_array($v['dispense_unit'], $drugUnit[DictType::UNIT_4])) {
                return error('[第' . ($k + 1) . '行] 零售单位填写不正确！<br/>(' . implode(',', $drugUnit[DictType::UNIT_4]) . ')');
            }
            $list[$k]['retail_price'] = floatval($v['retail_price']);
            if ($list[$k]['retail_price'] <= 0) {
                return error('[第' . ($k + 1) . '行] 零售价必须大于0！');
            }
            $list[$k]['amount'] = intval($v['amount']);
            if ($list[$k]['amount'] < 0) {
                return error('[第' . ($k + 1) . '行] 入库数量不能小于0！');
            }
            $list[$k]['purchase_price'] = floatval($v['purchase_price']);
            if ($list[$k]['purchase_price'] < 0) {
                return error('[第' . ($k + 1) . '行] 进货价不能小于0！');
            }
            $list[$k]['usages'] = array_search($v['usages'], NoteUsage::$message);
        }
        unset($drugUnit);

        // 合并重复数据
        $list = array_values(array_column($list, null, 'name'));
        $rows = [
            'update' => 0,
            'insert' => 0
        ];

        // 添加药品
        $drugModel = new DrugModel($user_id);
        foreach ($list as $k => $v) {
            $list[$k]['id'] = $drugModel->drugExists(['name' => $v['name']]);
            $list[$k]['status'] = 1;
            // 保存药品
            $result = $drugModel->saveDrug($list[$k]);
            if ($result['errorcode'] !== 0) {
                return error('「' . $v['name'] . '」保存失败，请重试');
            }
            $rows[$list[$k]['id'] ? 'update' : 'insert'] ++;
            $list[$k]['id'] = $result['result']['drug_id'];
            if ($k % 10 == 0) {
                usleep(20000);
            }
        }

        // 入库
        $datails = [
            'stock_type' => StockType::PULL,
            'stock_date' => date('Y-m-d', TIMESTAMP),
            'stock_way' => StockWay::OTHER_PULL,
            'remark' => '数据导入',
            'details' => []
        ];
        foreach ($list as $k => $v) {
            if ($v['amount'] > 0) {
                $datails['details'][] = [
                    'drug_id' => $v['id'],
                    'amount' => $v['amount'],
                    'purchase_price' => $v['purchase_price'],
                    'manufactor_name' => $v['manufactor_name']
                ];
            }
        }
        if ($datails['details']) {
            $result = (new StockModel($user_id))->addStock($datails);
            if ($result['errorcode'] !== 0) {
                return error('库存保存失败，请重试');
            }
        }

        $result = [
            '新增药品信息 ' . $rows['insert'] . ' 条,更新药品信息 ' . $rows['update'] . ' 条'
        ];
        if ($datails['details']) {
            $result[] = '请在「库房管理->入库管理」入库确认';
        }
        unset($datails);
        return success(implode('<br/>', $result));
    }

    /**
     * 材料信息导入
     * @return array
     */
    private function importMaterialDrug($user_id, &$list)
    {
        $drugUnit = DictType::getDrugUnit();
        $pinYin = new \app\library\PinYin();
        // 效验数据
        foreach ($list as $k => $v) {
            if (!$v['name']) {
                return error('[第' . ($k + 1) . '行] 材料名称不能为空！');
            }
            $list[$k]['py_code'] = $pinYin->get_first_py($v['name']);
            $list[$k]['drug_type'] = DrugType::MATERIAL;
            if (!$v['package_spec']) {
                return error('[第' . ($k + 1) . '行] 规格不能为空！');
            }
            if (!in_array($v['dispense_unit'], $drugUnit[DictType::UNIT_4])) {
                return error('[第' . ($k + 1) . '行] 零售单位填写不正确！<br/>(' . implode(',', $drugUnit[DictType::UNIT_4]) . ')');
            }
            $list[$k]['retail_price'] = floatval($v['retail_price']);
            if ($list[$k]['retail_price'] <= 0) {
                return error('[第' . ($k + 1) . '行] 零售价必须大于0！');
            }
            $list[$k]['amount'] = intval($v['amount']);
            if ($list[$k]['amount'] < 0) {
                return error('[第' . ($k + 1) . '行] 入库数量不能小于0！');
            }
            $list[$k]['purchase_price'] = floatval($v['purchase_price']);
            if ($list[$k]['purchase_price'] < 0) {
                return error('[第' . ($k + 1) . '行] 进货价不能小于0！');
            }
        }
        unset($drugUnit);

        // 合并重复数据
        $list = array_values(array_column($list, null, 'name'));
        $rows = [
            'update' => 0,
            'insert' => 0
        ];

        // 添加药品
        $drugModel = new DrugModel($user_id);
        foreach ($list as $k => $v) {
            $list[$k]['id'] = $drugModel->drugExists(['name' => $v['name']]);
            $list[$k]['status'] = 1;
            // 保存药品
            $result = $drugModel->saveDrug($list[$k]);
            if ($result['errorcode'] !== 0) {
                return error('「' . $v['name'] . '」保存失败，请重试');
            }
            $rows[$list[$k]['id'] ? 'update' : 'insert'] ++;
            $list[$k]['id'] = $result['result']['drug_id'];
            if ($k % 10 == 0) {
                usleep(20000);
            }
        }

        // 入库
        $datails = [
            'stock_type' => StockType::PULL,
            'stock_date' => date('Y-m-d', TIMESTAMP),
            'stock_way' => StockWay::OTHER_PULL,
            'remark' => '数据导入',
            'details' => []
        ];
        foreach ($list as $k => $v) {
            if ($v['amount'] > 0) {
                $datails['details'][] = [
                    'drug_id' => $v['id'],
                    'amount' => $v['amount'],
                    'purchase_price' => $v['purchase_price'],
                    'manufactor_name' => $v['manufactor_name']
                ];
            }
        }
        if ($datails['details']) {
            $result = (new StockModel($user_id))->addStock($datails);
            if ($result['errorcode'] !== 0) {
                return error('库存保存失败，请重试');
            }
        }

        $result = [
            '新增材料信息 ' . $rows['insert'] . ' 条,更新材料信息 ' . $rows['update'] . ' 条'
        ];
        if ($datails['details']) {
            $result[] = '请在「库房管理->入库管理」入库确认';
        }
        unset($datails);
        return success(implode('<br/>', $result));
    }

    /**
     * 诊疗项目导入
     * @return array
     */
    private function importTreatment($user_id, &$list)
    {
        $unit = DictType::getTreatmentUnit();
        $pinYin = new \app\library\PinYin();
        // 效验数据
        foreach ($list as $k => $v) {
            if (!$v['name']) {
                return error('[第' . ($k + 1) . '行] 项目名称不能为空！');
            }
            $list[$k]['py_code'] = $pinYin->get_first_py($v['name']);
            if (!in_array($v['unit'], $unit)) {
                return error('[第' . ($k + 1) . '行] 单位填写不正确！<br/>(' . implode(',', $unit) . ')');
            }
            $list[$k]['price'] = floatval($v['price']);
            if ($list[$k]['price'] <= 0) {
                return error('[第' . ($k + 1) . '行] 单价必须大于0！');
            }
            $list[$k]['royalty'] = array_search($v['royalty'], Royalty::$message);
            $list[$k]['royalty_ratio'] = floatval($v['royalty_ratio']);
        }
        unset($unit);

        // 合并重复数据
        $list = array_values(array_column($list, null, 'name'));
        $rows = [
            'update' => 0,
            'insert' => 0
        ];

        // 添加药品
        $userInfo = (new AdminModel())->checkAdminInfo($user_id);
        $treatmentModel = new TreatmentModel();
        foreach ($list as $k => $v) {
            $list[$k]['id'] = $treatmentModel->treatmentExists(['clinic_id' => $userInfo['clinic_id'], 'name' => $v['name']]);
            $list[$k]['status'] = 1;
            $result = $treatmentModel->saveTreatment($user_id, $list[$k]);
            if ($result['errorcode'] !== 0) {
                return error('「' . $v['name'] . '」保存失败，请重试');
            }
            $rows[$list[$k]['id'] ? 'update' : 'insert'] ++;
            if ($k % 10 == 0) {
                usleep(20000);
            }
        }

        return success('新增诊疗项目 ' . $rows['insert'] . ' 条,更新诊疗项目 ' . $rows['update'] . ' 条');
    }

    /**
     * 上传数据转码 UTF-8
     * @return string
     */
    private function upEncodeUTF($text)
    {
        if (!$encode = mb_detect_encoding($text, array('UTF-8','GB2312','GBK','ASCII','BIG5'))) {
            return '';
        }
        if($encode != 'UTF-8') {
            return mb_convert_encoding($text, 'UTF-8', $encode);
        } else {
            return $text;
        }
    }

    /**
     * 过滤上传数据
     * @return string
     */
    private function upFilterData($data)
    {
        $data = trim(trim_space($data), '　');
        $data = str_replace(["\r", "\n", "\t", '"', '\''], '', $data);
        $data = mb_substr($data, 0, 200, 'UTF-8');
        $data = htmlspecialchars(rtrim($data, "\0"), ENT_QUOTES);
        return $data;
    }

}
