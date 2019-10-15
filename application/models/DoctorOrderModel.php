<?php

namespace app\models;

use app\common\CommonStatus;
use app\common\DrugStatus;
use app\common\Gender;
use app\common\NoteCategory;
use app\common\NoteFrequency;
use app\common\NoteSide;
use app\common\NoteUsage;
use app\common\OrderDiscountType;
use app\common\OrderPayFlow;
use app\common\OrderPayWay;
use app\common\OrderSource;
use app\common\OrderStatus;
use app\common\NoteAllergy;
use app\common\Role;
use Crud;

class DoctorOrderModel extends Crud {

    protected $table = 'dayi_doctor_order';

    /**
     * 获取医生列表
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
    public function getDoctorList ($user_id)
    {
        // 获取用户信息
        $userInfo = $this->getUserInfo($user_id);
        if ($userInfo['errorcode'] !== 0) {
            return $userInfo;
        }
        $userInfo = $userInfo['result'];
        // 获取医生
        return success((new AdminModel())->getUserByRole($userInfo['store_id'], Role::DOCTOR));
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
     * 获取药品用法
     * @param category 药品分类
     * @return array
     */
    public function getUsageEnum ($category)
    {
        $list = [];
        $data = [];
        if ($category == NoteCategory::WESTERN) {
            $data = NoteUsage::getWesternUsage();
        } else if ($category == NoteCategory::CHINESE) {
            $data = NoteUsage::getChineseUsage();
        }
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
     * 搜索药品
     * @return array
     */
    public function searchDrug ($post)
    {
        $post['store_id'] = intval($post['store_id']);
        $post['name']     = trim_space($post['name']);
        if (!$post['store_id'] || !$post['name']) {
            return success([]);
        }
        return success((new DrugModel())->search($post['store_id'], $post['name']));
    }

    /**
     * 搜索诊疗项目
     * @return array
     */
    public function searchTreatmentSheet ($post)
    {
        $post['store_id'] = intval($post['store_id']);
        $post['name']     = trim_space($post['name']);
        if (!$post['store_id'] || !$post['name']) {
            return success([]);
        }
        return success((new TreatmentModel())->search($post['store_id'], $post['name']));
    }

    /**
     * 获取登录账号信息
     * @return array
     */
    public function getUserProfile ($user_id)
    {
        // 用户获取
        $userInfo = $this->getUserInfo($user_id);
        if ($userInfo['errorcode'] !== 0) {
            return $userInfo;
        }
        $userInfo = $userInfo['result'];

        // 获取诊所信息
        $userInfo['store_info'] = (new StoreModel())->find(['id' => $userInfo['store_id']], 'id,name,status');

        // 消息
        $userInfo['unread_count'] = rand(1, 10); // 未读消息数

        return success($userInfo);
    }

    /**
     * 购药
     * @return array
     */
    public function buyDrug ($user_id, $post)
    {
        // 用户获取
        $userInfo = $this->getUserInfo($user_id);
        if ($userInfo['errorcode'] !== 0) {
            return $userInfo;
        }
        $userInfo = $userInfo['result'];

        // 验证会诊单
        $post['advanced'] = true;
        $post['patient_name_safe'] = true; // 患者姓名不必填
        $post = $this->validationDoctorCard($post);
        if ($post['errorcode'] !== 0) {
            return $post;
        }
        $post = $post['result'];

        // 生成会诊单
        if (!$orderId = $this->getDb()->transaction(function ($db) use($post, $userInfo) {
            // 新增订单
            if (!$orderId = $db->insert($this->table, [
                'store_id'          => $userInfo['store_id'],
                'doctor_id'         => $userInfo['id'],
                'enum_source'       => OrderSource::BUG_DRUG,
                'patient_id'        => $post['patient_id'],
                'patient_name'      => $post['patient_name'],
                'patient_tel'       => $post['patient_tel'],
                'patient_gender'    => $post['patient_gender'],
                'patient_age'       => $post['patient_age'],
                'pay'               => $post['total_money'],
                'update_time'       => date('Y-m-d H:i:s', TIMESTAMP),
                'create_time'       => date('Y-m-d H:i:s', TIMESTAMP)
            ], null, null, true)) {
                return false;
            }
            // 新增处方笺
            foreach ($post['notes'] as $k => $v) {
                $post['notes'][$k]['order_id'] = $orderId;
            }
            if (!$db->insert('dayi_doctor_order_notes', $post['notes'])) {
                return false;
            }
            // 减库存
            $list = $this->totalAmount($post['notes']);
            foreach ($list as $k => $v) {
                if (!$db->update('dayi_drug', ['amount' => ['amount-' . $v]], ['id' => $k])) {
                    return false;
                }
            }
            return $orderId;
        })) {
            return error('库存不足，不能保存订单');
        }

        unset($post);
        return success([
            'order_id' => $orderId
        ]);
    }

    /**
     * 线下收费
     * @return array
     */
    public function localCharge ($user_id, $post)
    {
        $post['order_id']      = intval($post['order_id']);
        $post['payway']        = OrderPayWay::isLocalPayWay($post['payway']) ? $post['payway'] : null;
        $post['money']         = max(0, $post['money']);
        $post['second_payway'] = OrderPayWay::isLocalPayWay($post['second_payway']) ? $post['second_payway'] : null;
        $post['second_money']  = $post['second_payway'] ? max(0, $post['second_money']) : 0;
        $post['second_payway'] = $post['second_money'] ? $post['second_payway'] : null;
        $post['remark']        = trim_space($post['remark']);

        if (!$post['payway'] || !$post['money']) {
            return error('请填写至少一种付款方式');
        }

        // 用户获取
        $userInfo = $this->getUserInfo($user_id);
        if ($userInfo['errorcode'] !== 0) {
            return $userInfo;
        }
        $userInfo = $userInfo['result'];

        // 获取订单总金额
        if (!$orderInfo = $this->find(['id' => $post['order_id'], 'store_id' => $userInfo['store_id'], 'status' => OrderStatus::NOPAY], 'pay')) {
            return error('本次会诊已结束');
        }

        // 计算优惠金额
        $discount = OrderDiscountType::getDiscountMoney($post['discount_type'], $post['discount_val'], $orderInfo['pay']);

        // 验证实付金额是否等于应付金额
        if ($post['money'] + $post['second_money'] != $orderInfo['pay'] - $discount) {
            return error('付款金额验证失败');
        }

        // 更新订单已收费
        if (!$this->getDb()->update($this->table, [
            'pay'            => $orderInfo['pay'] - $discount,
            'discount'       => $discount,
            'charge_user_id' => $user_id,
            'print_code'     => null,
            'payway'         => $post['second_payway'] ? OrderPayWay::MULTIPAY : $post['payway'],
            'status'         => OrderStatus::PAY,
            'update_time'    => date('Y-m-d H:i:s', TIMESTAMP)
        ], [
            'id'          => $post['order_id'],
            'status'      => OrderStatus::NOPAY
        ])) {
            return error('保存订单失败');
        }

        // 添加资金流水
        (new PayFlowModel())->insert(OrderPayFlow::CHARGE, $post['order_id'], $post['payway'], $post['money'], $post['second_payway'], $post['second_money'], $post['remark']);

        return success('ok');
    }

    /**
     * 联诊
     * @return array
     */
    public function unionConsultation ($user_id, $print_code)
    {
        $print_code = intval($print_code);

        if (!$print_code) {
            return error('请填写取号号码');
        }

        // 用户获取
        $userInfo = $this->getUserInfo($user_id);
        if ($userInfo['errorcode'] !== 0) {
            return $userInfo;
        }
        $userInfo = $userInfo['result'];

        if (!$orderInfo = $this->find(['store_id' => $userInfo['store_id'], 'print_code' => $print_code, 'status' => OrderStatus::NOPAY], 'id')) {
            return error('本次会诊已结束');
        }

        return $this->getDoctorOrderDetail($orderInfo['id']);
    }

    /**
     * 获取会诊单详情
     * @return array
     */
    public function getDoctorOrderDetail ($order_id)
    {
        $order_id = intval($order_id);

        if (!$orderInfo = $this->find(['id' => $order_id], 'id,doctor_id,enum_source,patient_name,patient_tel,patient_gender,patient_age,patient_complaint,patient_allergies,patient_diagnosis,note_side,advice,voice,pay,discount,payway,status,create_time')) {
            return error('订单不存在');
        }

        // 获取会诊医生
        $doctorInfo = (new AdminModel())->getAdminInfo($orderInfo['doctor_id']);
        $orderInfo['doctor_name'] = $doctorInfo['nickname'];

        // 获取处方笺
        $orderInfo['notes'] = $this->getDb()->table('dayi_doctor_order_notes')->where(['order_id' => $order_id])->field('id,category,relation_id,name,package_spec,dispense_unit,dosage_unit,single_amount,total_amount,usage,frequency,drug_days,dose,remark')->order('id')->select();

        return success($orderInfo);
    }

    /**
     * 获取会诊单列表
     * @return array
     */
    public function getDoctorOrderList ($user_id, array $post)
    {
        // 搜索时间
        $post['start_time'] = strtotime($post['start_time']);
        $post['end_time']   = strtotime($post['end_time']);

        if ($post['start_time'] > $post['end_time']) {
            return error('开始时间不能大于截止时间');
        }

        if (!$post['start_time'] || !$post['end_time']) {
            $post['start_time'] = strtotime(date('Y-m-d', TIMESTAMP));
            $post['end_time']   = TIMESTAMP;
        }

        $post['start_time'] = date('Y-m-d H:i:s', $post['start_time']);
        $post['end_time']   = date('Y-m-d H:i:s', $post['end_time']);

        // 用户获取
        $userInfo = $this->getUserInfo($user_id);
        if ($userInfo['errorcode'] !== 0) {
            return $userInfo;
        }
        $userInfo = $userInfo['result'];

        // 条件查询
        $condition = [
            'store_id'    => $userInfo['store_id'],
            'enum_source' => OrderSource::DOCTOR,
            'create_time' => ['between', [$post['start_time'], $post['end_time']]]
        ];
        if (!is_null(OrderStatus::format($post['status']))) {
            $condition['status'] = $post['status'];
        }

        $count = $this->getDb()->table($this->table)->where($condition)->count();
        if ($count > 0) {
            $pagesize = getPageParams($post['page'], $count, max(20, $post['pagesize']));
            $list = $this->select($condition, 'id,patient_name,patient_gender,patient_age,create_time,status', 'id desc', $pagesize['limitstr']);
            foreach ($list as $k => $v) {
                $list[$k]['patient_gender'] = Gender::getMessage($v['patient_gender']);
                $list[$k]['patient_age']    = Gender::showAge($v['patient_age']);
                $list[$k]['status']         = OrderStatus::getMessage($v['status']);
            }
        }

        return success([
            'total' => $count,
            'list' => $list ? $list : []
        ]);
    }

    /**
     * 编辑保存会诊单
     * @return array
     */
    public function saveDoctorCard ($post)
    {
        // 验证会诊单
        $post['advanced'] = true;
        $post = $this->validationDoctorCard($post);
        if ($post['errorcode'] !== 0) {
            return $post;
        }
        $post = $post['result'];

        if (!$orderInfo = $this->find(['id' => $post['order_id'], 'status' => OrderStatus::NOPAY], 'update_time')) {
            return error('订单不存在');
        }

        // 获取处方笺
        if (false === ($notes = $this->getDb()->table('dayi_doctor_order_notes')->where(['order_id' => $post['order_id']])->field('id,category,relation_id,total_amount,dose')->select())) {
            return error('数据异常，请重新操作');
        }

        // 编辑会诊单
        if (!$this->getDb()->transaction(function ($db) use($post, $notes, $orderInfo) {
            if (!$db->update($this->table, [
                'patient_id'        => $post['patient_id'],
                'patient_name'      => $post['patient_name'],
                'patient_tel'       => $post['patient_tel'],
                'patient_gender'    => $post['patient_gender'],
                'patient_age'       => $post['patient_age'],
                'patient_complaint' => $post['patient_complaint'],
                'patient_allergies' => $post['patient_allergies'],
                'patient_diagnosis' => $post['patient_diagnosis'],
                'note_side'         => $post['note_side'],
                'advice'            => $post['advice'],
                'pay'               => $post['total_money'],
                'update_time'       => date('Y-m-d H:i:s', TIMESTAMP + 1)
            ], [
                'id'          => $post['order_id'],
                'status'      => OrderStatus::NOPAY,
                'update_time' => $orderInfo['update_time']
            ])) {
                return false;
            }
            // 删除之前处方笺
            if ($notes) {
                if (!$db->delete('dayi_doctor_order_notes', ['id' => ['in', array_column($notes, 'id')]])) {
                    return false;
                }
                // 加库存
                $list = $this->totalAmount($notes);
                foreach ($list as $k => $v) {
                    if (!$db->update('dayi_drug', ['amount' => ['amount+' . $v]], ['id' => $k])) {
                        return false;
                    }
                }
            }
            // 新增处方笺
            foreach ($post['notes'] as $k => $v) {
                $post['notes'][$k]['order_id'] = $post['order_id'];
            }
            if (!$db->insert('dayi_doctor_order_notes', $post['notes'])) {
                return false;
            }
            // 减库存
            $list = $this->totalAmount($post['notes']);
            foreach ($list as $k => $v) {
                if (!$db->update('dayi_drug', ['amount' => ['amount-' . $v]], ['id' => $k])) {
                    return false;
                }
            }
            return true;
        })) {
            return error('库存不足，不能保存订单');
        }

        unset($post);
        return success('ok');
    }

    /**
     * 创建会诊单
     * @return array
     */
    public function doctorCreateCard ($user_id, array $post)
    {
        // 用户获取
        $userInfo = $this->getUserInfo($user_id);
        if ($userInfo['errorcode'] !== 0) {
            return $userInfo;
        }
        $userInfo = $userInfo['result'];

        // 验证会诊单
        $post = $this->validationDoctorCard($post);
        if ($post['errorcode'] !== 0) {
            return $post;
        }
        $post = $post['result'];

        // 生成取号号码
        if (!$post['advanced']) {
            $printCode = $this->buildPrintCode($userInfo['store_id']);
        }

        // 生成会诊单
        if (!$orderId = $this->getDb()->transaction(function ($db) use($post, $userInfo, $printCode) {
            // 新增订单
            if (!$orderId = $db->insert($this->table, [
                'store_id'          => $userInfo['store_id'],
                'doctor_id'         => $userInfo['id'],
                'enum_source'       => OrderSource::DOCTOR,
                'print_code'        => $printCode,
                'patient_id'        => $post['patient_id'],
                'patient_name'      => $post['patient_name'],
                'patient_tel'       => $post['patient_tel'],
                'patient_gender'    => $post['patient_gender'],
                'patient_age'       => $post['patient_age'],
                'patient_complaint' => $post['patient_complaint'],
                'patient_allergies' => $post['patient_allergies'],
                'patient_diagnosis' => $post['patient_diagnosis'],
                'note_side'         => $post['note_side'],
                'advice'            => $post['advice'],
                'voice'             => $post['voice'],
                'pay'               => $post['total_money'],
                'update_time'       => date('Y-m-d H:i:s', TIMESTAMP),
                'create_time'       => date('Y-m-d H:i:s', TIMESTAMP)
            ], null, null, true)) {
                return false;
            }
            if (empty($post['notes'])) {
                return $orderId;
            }
            // 新增处方笺
            foreach ($post['notes'] as $k => $v) {
                $post['notes'][$k]['order_id'] = $orderId;
            }
            if (!$db->insert('dayi_doctor_order_notes', $post['notes'])) {
                return false;
            }
            // 减库存
            $list = $this->totalAmount($post['notes']);
            foreach ($list as $k => $v) {
                if (!$db->update('dayi_drug', ['amount' => ['amount-' . $v]], ['id' => $k])) {
                    return false;
                }
            }
            return $orderId;
        })) {
            return error('库存不足，不能保存订单');
        }

        unset($post);
        return success([
            'order_id'   => $orderId,
            'print_code' => $printCode
        ]);
    }

    /**
     * 获取用户信息
     * @param $user_id
     * @return array
     */
    public function getUserInfo ($user_id)
    {
        // 用户获取
        if (!$userInfo = (new AdminModel())->getAdminInfo($user_id)) {
            return error('用户不存在');
        }
        if ($userInfo['status'] != CommonStatus::OK) {
            return error('你已被禁用');
        }
        if (!$userInfo['store_id']) {
            return error('你未绑定诊所');
        }
        return success($userInfo);
    }

    /**
     * 验证会诊单
     * @return array
     */
    protected function validationDoctorCard (array $post)
    {
        $post['order_id']          = intval($post['order_id']);
        $post['patient_name']      = trim_space($post['patient_name']);
        $post['patient_name']      = $post['patient_name'] ? $post['patient_name'] : null;
        $post['patient_gender']    = Gender::format($post['patient_gender']);
        $post['patient_gender']    = $post['patient_gender'] ? $post['patient_gender'] : null;
        $post['patient_age']       = Gender::validationAge($post['patient_age']);
        $post['patient_tel']       = trim_space($post['patient_tel']);
        $post['patient_tel']       = $post['patient_tel'] ? $post['patient_tel'] : null;
        $post['patient_complaint'] = trim_space($post['patient_complaint']);
        $post['patient_complaint'] = $post['patient_complaint'] ? $post['patient_complaint'] : null;
        $post['patient_allergies'] = trim_space($post['patient_allergies']);
        $post['patient_allergies'] = $post['patient_allergies'] ? $post['patient_allergies'] : null;
        $post['patient_diagnosis'] = trim_space($post['patient_diagnosis']);
        $post['patient_diagnosis'] = $post['patient_diagnosis'] ? $post['patient_diagnosis'] : null;
        $post['note_dose']         = max(0, intval($post['note_dose']));
        $post['note_side']         = NoteSide::format($post['note_side']);
        $post['advice']            = trim_space($post['advice']);
        $post['advice']            = $post['advice'] ? $post['advice'] : null;
        $post['voice']             = ishttp($post['voice']) ? $post['voice'] : null;
        $post['notes']             = $post['notes'] ? array_slice(json_decode($post['notes'], true), 0, 20) : [];

        if ($post['patient_tel'] && !validate_telephone($post['patient_tel'])) {
            return error('患者手机号填写不正确');
        }

        // 判断是否高级模式
        if (empty($post['advanced'])) {
            $post['notes'] = null;
            $post['total_money'] = 0;
            return success($post);
        }

        // 高级模式

        if (empty($post['patient_name_safe'])) {
            if (empty($post['patient_name'])) {
                return error('请填写患者姓名');
            }
        }
        if ($post['patient_name']) {
            // 姓名不能全数字
            if (preg_match('/^\d+$/', $post['patient_name'])) {
                return error('请检查患者姓名是否正确');
            }
            // 保存患者信息
            if (empty($post['patient_id'] = (new PatientModel())->insertUpdate($post['patient_name'], $post['patient_tel'], $post['patient_age'], $post['patient_gender']))) {
                return error('患者信息保存失败');
            }
        }
        if (empty($post['notes'])) {
            return error('请填写处方笺');
        }

        // 处方笺
        if (!$post['notes'] = $this->arrangeNotes($post['notes'], $post['note_dose'])) {
            return error('处方笺填写格式不正确');
        }

        // 计算总金额
        if (!$post['total_money'] = $this->totalMoney($post['notes'])) {
            return error('订单金额异常');
        }

        return success($post);
    }

    /**
     * 生成打印票据号（4位）
     * @param $store_id 门店ID
     * @return string
     */
    protected function buildPrintCode ($store_id)
    {
        $code = (rand() % 10) . (rand() % 10) . (rand() % 10) . (rand() % 10);
        // 检查重复
        if ($this->getDb()->table($this->table)->wherer(['store_id' => $store_id, 'print_code' => intval($code), 'status' => OrderStatus::NOPAY])->count()) {
            return $this->buildPrintCode($store_id);
        }
        return $code;
    }

    /**
     * 合计处方总金额
     * @param array $notes
     * @return int
     */
    protected function totalMoney (array $notes)
    {
        $total = 0;
        foreach ($notes as $k => $v) {
            $price = $v['price'];
            if ($v['category'] == NoteCategory::CHINESE) {
                $price *= $v['dose']; // 草药剂量
            }
            $total += $price;
        }
        return $total;
    }

    /**
     * 合计处方药品总量
     * @param array $notes
     * @return array
     */
    protected function totalAmount (array $notes)
    {
        $list = [];
        foreach ($notes as $k => $v) {
            if (NoteCategory::isDrug($v['category'])) {
                // 药品
                if ($v['category'] == NoteCategory::CHINESE) {
                    // 草药剂量
                    $list[$v['relation_id']] += ($v['total_amount'] * $v['dose']);
                } else {
                    $list[$v['relation_id']] += $v['total_amount'];
                }
            }
        }
        return $list;
    }

    /**
     * 整理处方笺
     * @param $notes
     * @param $dose 草药计量
     * @return array
     */
    protected function arrangeNotes (array $notes, &$dose)
    {
        $drugModel      = new DrugModel();
        $treatmentModel = new TreatmentModel();

        foreach ($notes as $k => $v) {
            $notes[$k]['relation_id']   = intval($v['relation_id']);
            $notes[$k]['total_amount']  = max(0, intval($v['total_amount']));
            $notes[$k]['single_amount'] = max(0, intval($v['single_amount']));
            $notes[$k]['usage']         = NoteUsage::format($v['usage']);
            $notes[$k]['frequency']     = NoteFrequency::format($v['frequency']);
            $notes[$k]['drug_days']     = max(0, intval($v['drug_days']));
            if (!$notes[$k]['relation_id'] || !$notes[$k]['total_amount'] || !NoteCategory::format($v['category'])) {
                return false;
            }
        }

        $list = [];
        $lookChineseDrug = false;
        foreach ($notes as $k => $v) {
            if (NoteCategory::isDrug($v['category'])) {
                // 药品
                if ($v['category'] == NoteCategory::CHINESE) {
                    // 草药剂量
                    $lookChineseDrug = true;
                    $dose = region_number($dose, 0, 1, 1000, 1000);
                    $list[1][$v['relation_id']] += ($v['total_amount'] * $dose);
                } else {
                    $list[1][$v['relation_id']] += $v['total_amount'];
                }
            } else {
                // 诊疗
                $list[2][$v['relation_id']] = [];
            }
        }
        if (!$lookChineseDrug) {
            $dose = 0;
        }

        // 获取药品
        if (isset($list[1])) {
            foreach ($list[1] as $k => $v) {
                // 检查库存
                if (!$list[1][$k] = $drugModel->find(['id' => $k, 'status' => DrugStatus::ONSALES, 'amount' => ['>=', $v]], 'name,package_spec,dispense_unit,dosage_unit,retail_price')) {
                    return false;
                }
            }
        }

        // 获取诊疗
        if (isset($list[2])) {
            foreach ($list[2] as $k => $v) {
                if (!$list[2][$k] = $treatmentModel->find(['id' => $k, 'status' => CommonStatus::OK], 'name,unit,price')) {
                    return false;
                }
            }
        }

        $result = [];
        foreach ($notes as $k => $v) {
            if (NoteCategory::isDrug($v['category'])) {
                // 药品
                $result[] = [
                    'category'      => $v['category'],
                    'relation_id'   => $v['relation_id'],
                    'name'          => $list[1][$v['relation_id']]['name'],
                    'package_spec'  => $list[1][$v['relation_id']]['package_spec'],
                    'dispense_unit' => $list[1][$v['relation_id']]['dispense_unit'],
                    'dosage_unit'   => $list[1][$v['relation_id']]['dosage_unit'],
                    'single_amount' => $v['single_amount'],
                    'total_amount'  => $v['total_amount'],
                    'usage'         => $v['usage'],
                    'frequency'     => $v['frequency'],
                    'drug_days'     => $v['drug_days'],
                    'price'         => $v['total_amount'] * $list[1][$v['relation_id']]['retail_price'],
                    'remark'        => null,
                    'dose'          => $dose,
                    'create_time'   => date('Y-m-d H:i:s', TIMESTAMP)
                ];
            } else {
                // 诊疗
                $result[] = [
                    'category'      => $v['category'],
                    'relation_id'   => $v['relation_id'],
                    'name'          => $list[2][$v['relation_id']]['name'],
                    'package_spec'  => null,
                    'dispense_unit' => $list[2][$v['relation_id']]['unit'],
                    'dosage_unit'   => null,
                    'single_amount' => null,
                    'total_amount'  => $v['total_amount'],
                    'usage'         => null,
                    'frequency'     => null,
                    'drug_days'     => null,
                    'price'         => $v['total_amount'] * $list[2][$v['relation_id']]['price'],
                    'remark'        => $v['remark'],
                    'dose'          => $dose,
                    'create_time'   => date('Y-m-d H:i:s', TIMESTAMP)
                ];
            }
        }

        unset($drugModel, $drugModel, $notes, $list);
        return $result;
    }

    /**
     * 生成单号(16位)
     * @return string
     */
    protected function generateOrderCode ($user_id)
    {
        $code[] = date('Ymd', TIMESTAMP);
        $code[] = (rand() % 10) . (rand() % 10) . (rand() % 10) . (rand() % 10);
        $code[] = str_pad(substr($user_id, -4),4,'0',STR_PAD_LEFT);
        return implode('', $code);
    }

}
