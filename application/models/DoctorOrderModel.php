<?php

namespace app\models;

use app\common\CommonStatus;
use app\common\Gender;
use app\common\NoteCategory;
use app\common\NoteFrequency;
use app\common\NoteSide;
use app\common\NoteUsage;
use app\common\DrugDosage;
use app\common\Royalty;
use app\common\OrderPayFlow;
use app\common\OrderPayWay;
use app\common\OrderSource;
use app\common\OrderStatus;
use app\common\GenerateCache;
use app\common\StockType;
use app\common\NoteStatus;
use app\common\VipLevel;
use Crud;

class DoctorOrderModel extends Crud {

    protected $table    = 'dayi_order';
    protected $userInfo = null;

    public function __construct ($user_id = null, $clinic_id = null)
    {
        // 分区
        if ($user_id) {
            $this->userInfo = (new AdminModel())->checkAdminInfo($user_id);
            $clinic_id = $this->userInfo['clinic_id'];
        }
        if (empty($clinic_id)) {
            json(null, '参数错误', -1);
        }
        list($this->link, $this->partition) = GenerateCache::getClinicPartition($clinic_id);
    }

    /**
     * 录音回调
     * @param order_id
     * @param url
     * @return array
     */
    public function notifyVoice ($order_id, $url)
    {
        $order_id = intval($order_id);

        if (!$order_id || !ishttp($url)) {
            return error('参数错误');
        }

        if (!$this->getDb()->where(['id' => $order_id, 'voice' => null])->update(['voice' => $url])) {
            return error('已保存');
        }
        return success('ok');
    }

    /**
     * 打印模板
     * @param type
     * @param order_id
     * @return array
     */
    public function printTemplete ($type, $order_id)
    {
        $orderInfo = $this->getDoctorOrderDetail($order_id);
        if ($orderInfo['errorcode'] !== 0) {
            return $orderInfo;
        }
        $orderInfo = $orderInfo['result'];

        if (!$content = $this->parsePrintTemplete($type, $orderInfo)) {
            return error('模板不存在');
        }

        return success([
            'content' => $content
        ]);
    }

    /**
     * 解析打印模板
     * @return string
     */
    protected function parsePrintTemplete ($type, array $data)
    {
        $filePath = APPLICATION_PATH . '/public/static/print_templete/' . intval($type) . '.html';
        if (!file_exists($filePath)) {
            return null;
        }
        ob_start();
        include $filePath;
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    /**
     * 购药
     * @return array
     */
    public function buyDrug (array $post)
    {
        // 验证会诊单
        $post['advanced'] = true;
        $post['patient_name_safe'] = true; // 患者姓名不必填
        $post['clinic_id'] = $this->userInfo['clinic_id'];
        $post = $this->validationDoctorCard($post);
        if ($post['errorcode'] !== 0) {
            return $post;
        }
        $post = $post['result'];

        // 生成会诊单
        if (!$orderId = $this->getDb()->transaction(function ($db) use ($post) {
            // 新增订单
            if (!$orderId = $db->insert([
                'clinic_id'      => $this->userInfo['clinic_id'],
                'enum_source'    => OrderSource::BUY_DRUG,
                'patient_id'     => $post['patient_id'],
                'patient_name'   => $post['patient_name'],
                'patient_tel'    => $post['patient_tel'],
                'patient_gender' => $post['patient_gender'],
                'patient_age'    => $post['patient_age'],
                'pay'            => $post['total_money'],
                'update_time'    => date('Y-m-d H:i:s', TIMESTAMP),
                'create_time'    => date('Y-m-d H:i:s', TIMESTAMP)
            ], null, true)) {
                return false;
            }
            // 新增处方笺
            foreach ($post['notes'] as $k => $v) {
                $post['notes'][$k]['clinic_id'] = $this->userInfo['clinic_id'];
                $post['notes'][$k]['order_id']  = $orderId;
            }
            if (!$db->partition($this->partition)->table('dayi_order_notes')->insert($post['notes'])) {
                return false;
            }
            return $orderId;
        })) {
            return error('保存订单失败');
        }

        unset($post);
        return success([
            'order_id' => $orderId
        ]);
    }

    /**
     * 线下退费
     * @return array
     */
    public function localRefund (array $post)
    {
        $post['order_id'] = intval($post['order_id']);
        $post['payway']   = OrderPayWay::isLocalPayWay($post['payway']) ? $post['payway'] : null;
        $post['remark']   = trim_space($post['remark'], 0, 80);
        // notes 格式 [{id:1,back_amount:1}]
        $post['notes'] = $post['notes'] ? array_slice(json_decode(htmlspecialchars_decode($post['notes']), true), 0, 100) : [];

        if (empty($post['notes'])) {
            return error('请选择退费项目');
        }

        // 获取订单
        if (!$orderInfo = $this->find(['id' => $post['order_id'], 'clinic_id' => $this->userInfo['clinic_id'], 'status' => ['in', [OrderStatus::PAY, OrderStatus::PART_REFUND]]], 'pay,discount,stock_id')) {
            return error('该订单不能退费');
        }

        // 获取收费项目
        if (!$notes = $this->getDb()->table('dayi_order_notes')->where(['clinic_id' => $this->userInfo['clinic_id'], 'order_id' => $post['order_id'], 'status' => NoteStatus::PAY])->field('id,category,relation_id,total_amount,dose,unit_price,back_amount')->select()) {
            return error('收费项目不存在');
        }

        // 判断部分退费或全退费
        $orderStatus = OrderStatus::FULL_REFUND;
        foreach ($notes as $k => $v) {
            // 退费数量不能大于总数量
            if ($v['total_amount'] <= $v['back_amount']) {
                unset($notes[$k]);
                continue;
            }
            $have = false;
            foreach ($post['notes'] as $kk => $vv) {
                if ($v['id'] == $vv['id']) {
                    $have = true;
                    // 验证退费数量
                    if ($vv['back_amount'] <= 0 || $vv['back_amount'] > ($v['total_amount'] - $v['back_amount'])) {
                        $orderStatus = OrderStatus::PART_REFUND;
                        $notes[$k]['total_amount'] = $v['total_amount'] - $v['back_amount'];
                        break;
                    }
                    // 验证是否全退费
                    if ($vv['back_amount'] != ($v['total_amount'] - $v['back_amount'])) {
                        $orderStatus = OrderStatus::PART_REFUND;
                    }
                    $notes[$k]['total_amount'] = $vv['back_amount'];
                    break;
                }
            }
            if (!$have) {
                $orderStatus = OrderStatus::PART_REFUND;
                unset($notes[$k]);
            }
        }

        if (empty($notes)) {
            return error('退费项目为空');
        }

        // 部分退费不支持原路退还方式
        if (!$post['payway'] && $orderStatus === OrderStatus::PART_REFUND) {
            return error('部分退费不支持原路退还，请选择其他退费方式！');
        }

        // 有优惠的订单，不支持单项退费
        if ($orderInfo['discount'] && $orderStatus !== OrderStatus::FULL_REFUND) {
            return error('该笔收费存在优惠金额，不支持单项退费！');
        }

        // 计算退款金额
        $refundPrice = $orderInfo['discount'] ? $orderInfo['pay'] : $this->totalMoney($notes);

        if (!$this->getDb()->transaction(function ($db) use ($post, $orderInfo, $notes, $orderStatus, $refundPrice) { 
            // 更新订单已退费
            if (!$this->getDb()->where(['id' => $post['order_id'], 'status' => ['in', [OrderStatus::PAY, OrderStatus::PART_REFUND]]])->update([
                'status'      => $orderStatus,
                'refund'      => ['refund+' . $refundPrice],
                'update_time' => date('Y-m-d H:i:s', TIMESTAMP)
            ])) {
                return false;
            }
            // 更新处方状态
            if ($orderStatus === OrderStatus::FULL_REFUND) {
                // 全退费
                if (!$this->getDb()
                        ->table('dayi_order_notes')
                        ->where([
                            'id' => ['in', array_column($notes, 'id')], 
                            'clinic_id' => $this->userInfo['clinic_id'], 
                            'order_id' => $post['order_id'], 
                            'status' => NoteStatus::PAY
                        ])->update([
                            'status' => NoteStatus::REFUND, 
                            'back_amount' => ['total_amount']
                        ])) {
                    return false;
                }
            } else {
                // 部分退费
                foreach ($notes as $k => $v) {
                    if (!$this->getDb()
                            ->table('dayi_order_notes')
                            ->where([
                                'id' => $v['id'], 
                                'clinic_id' => $this->userInfo['clinic_id'], 
                                'order_id' => $post['order_id'], 
                                'status' => NoteStatus::PAY,
                                'total_amount' => ['>=back_amount+' . $v['total_amount']]
                            ])->update([
                                'status' => ['if(total_amount>back_amount+' . $v['total_amount'] . ',' . NoteStatus::PAY . ',' . NoteStatus::REFUND . ')'],
                                'back_amount' => ['back_amount+' . $v['total_amount']]
                            ])) {
                        return false;
                    }
                }
            }
            // 合并退费项目计算总退费数量
            $notes = $this->totalAmount($notes);
            // 加药品库存
            if (!(new DrugModel(null, $this->userInfo['clinic_id']))->updateAmount($this->userInfo['clinic_id'], StockType::PULL, $notes)) {
                return false;
            }
            // 自动退药
            if (!(new StockModel(null, $this->userInfo['clinic_id']))->backDrug($this->userInfo['clinic_id'], $orderInfo['stock_id'], $notes)) {
                return false;
            }
            // 添加资金流水
            return (new PayFlowModel(['link' => $this->link, 'partition' => $this->partition]))->insert(OrderPayFlow::REFUND, $this->userInfo['clinic_id'], $post['order_id'], $post['payway'], $refundPrice, null, null, $post['remark']);
        })) {
            return error('保存订单失败');
        }

        return success('ok');
    }

    /**
     * 线下收费
     * @return array
     */
    public function localCharge (array $post)
    {
        $post['order_id']      = intval($post['order_id']);
        $post['payway']        = OrderPayWay::isLocalPayWay($post['payway']) ? $post['payway'] : null;
        $post['money']         = max(0, $post['money']);
        $post['money']         = $post['money'] * 100;
        $post['second_payway'] = OrderPayWay::isLocalPayWay($post['second_payway']) ? $post['second_payway'] : null;
        $post['second_money']  = $post['second_payway'] ? max(0, $post['second_money']) : 0;
        $post['second_money']  = $post['second_money'] * 100;
        $post['second_payway'] = $post['second_money'] ? $post['second_payway'] : null;
        $post['remark']        = trim_space($post['remark'], 0, 80);

        if (!$post['payway']) {
            return error('请填写至少一种付款方式');
        }

        // 获取订单总金额
        if (!$orderInfo = $this->find(['id' => $post['order_id'], 'clinic_id' => $this->userInfo['clinic_id'], 'status' => OrderStatus::NOPAY], 'pay')) {
            return error('本次会诊已结束');
        }

        // 计算优惠后的金额
        $discount = Royalty::getDiscountMoney($post['discount_type'], $post['discount_val'], $orderInfo['pay']);

        // 验证实付金额是否等于应付金额
        if ($post['money'] + $post['second_money'] != $discount) {
            return error('付款金额验证失败');
        }

        // 获取处方笺
        if (!$notes = $this->getDb()->table('dayi_order_notes')->where(['clinic_id' => $this->userInfo['clinic_id'], 'order_id' => $post['order_id']])->field('id,category,relation_id,total_amount,dose')->select()) {
            return error('处方不存在');
        }
        $notes = $this->totalAmount($notes);

        if (!$this->getDb()->transaction(function ($db) use ($orderInfo, $discount, $notes, $post) {
            // 自动发药
            if (!$stockId = (new StockModel(null, $this->userInfo['clinic_id']))->putDrug($this->userInfo['clinic_id'], $notes)) {
                return false;
            }
            // 更新订单已收费
            if (!$this->getDb()->where(['id' => $post['order_id'], 'status' => OrderStatus::NOPAY])->update([
                'pay'            => $discount,
                'discount'       => $orderInfo['pay'] - $discount,
                'charge_user_id' => $this->userInfo['id'],
                'print_code'     => null,
                'payway'         => $post['second_payway'] ? OrderPayWay::MULTIPAY : $post['payway'],
                'status'         => OrderStatus::PAY,
                'stock_id'       => !is_bool($stockId) ? $stockId : null,
                'update_time'    => date('Y-m-d H:i:s', TIMESTAMP)
            ])) {
                return false;
            }
            // 更新处方状态
            if (!$this->getDb()
                    ->table('dayi_order_notes')
                    ->where(['clinic_id' => $this->userInfo['clinic_id'], 'order_id' => $post['order_id'], 'status' => NoteStatus::NOPAY])
                    ->update(['status' => NoteStatus::PAY])) {
                return false;
            }
            // 减药品库存
            if (!(new DrugModel(null, $this->userInfo['clinic_id']))->updateAmount($this->userInfo['clinic_id'], StockType::PUSH, $notes)) {
                return false;
            }
            // 添加资金流水
            return (new PayFlowModel(['link' => $this->link, 'partition' => $this->partition]))->insert(OrderPayFlow::CHARGE, $this->userInfo['clinic_id'], $post['order_id'], $post['payway'], $post['money'], $post['second_payway'], $post['second_money'], $post['remark']);
        })) {
            return error('保存订单失败，请检查库存');
        }

        return success('ok');
    }

    /**
     * 联诊
     * @return array
     */
    public function unionConsultation ($print_code)
    {
        $print_code = intval($print_code);

        if (!$print_code) {
            return error('请填写取号号码');
        }

        if (!$orderInfo = $this->find(['clinic_id' => $this->userInfo['clinic_id'], 'print_code' => $print_code, 'status' => OrderStatus::NOPAY], 'id')) {
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

        if (!$orderInfo = $this->find(['id' => $order_id, 'clinic_id' => $this->userInfo['clinic_id']], 'id,doctor_id,enum_source,print_code,patient_name,patient_tel,patient_gender,patient_age,patient_complaint,patient_allergies,patient_diagnosis,note_dose,note_side,advice,voice,pay,discount,refund,payway,status,create_time')) {
            return error('订单不存在');
        }

        $orderInfo['pay']      = round_dollar($orderInfo['pay']);
        $orderInfo['discount'] = round_dollar($orderInfo['discount']);
        $orderInfo['refund']   = round_dollar($orderInfo['refund']);

        // 录音保存时间
        if ($orderInfo['voice']) {
            $orderInfo['voice_save'] = VipLevel::checkVoiceSaveTime($this->userInfo['clinic_id'], $orderInfo['create_time']);
            $orderInfo['is_up'] = VipLevel::isDownloadVoiceByUp($this->userInfo['clinic_id'], $orderInfo['create_time']);
            if ($orderInfo['voice_save'] <= 0) {
                $orderInfo['voice'] = 'empty';
            }
        }

        // 支付方式
        if ($orderInfo['payway'] == OrderPayWay::MULTIPAY) {
            // 多种支付
            $orderInfo['payway'] = (new PayFlowModel(['link' => $this->link, 'partition' => $this->partition]))->getOrders(OrderPayFlow::CHARGE, $this->userInfo['clinic_id'], $order_id);
        } else {
            // 单种支付
            $orderInfo['payway'] = [[
                'money'  => $orderInfo['pay'],
                'payway' => OrderPayWay::getMessage($orderInfo['payway'])
            ]];
        }

        // 获取会诊医生
        $doctorInfo = (new AdminModel())->getAdminInfo($orderInfo['doctor_id']);
        $orderInfo['doctor_name'] = $doctorInfo['nickname'];

        // 获取处方笺
        $orderInfo['notes'] = [];
        if ($orderInfo['pay']) {
            $orderInfo['notes'] = $this->getDb()->table('dayi_order_notes')->where(['order_id' => $order_id])->field('id,category,relation_id,name,package_spec,dispense_unit,dosage_unit,single_amount,total_amount,usages,frequency,drug_days,dose,remark,unit_price,back_amount')->order('id')->select();
            foreach ($orderInfo['notes'] as $k => $v) {
                $orderInfo['notes'][$k]['unit_price'] = round_dollar($v['unit_price']);
                if (NoteUsage::format($v['usages'])) {
                    $orderInfo['notes'][$k]['usages_name'] = NoteUsage::getMessage($v['usages']);
                }
                if (NoteFrequency::format($v['category'])) {
                    $orderInfo['notes'][$k]['frequency_name'] = NoteFrequency::getMessage($v['frequency']);
                }
            }
        }

        return success($orderInfo);
    }

    /**
     * 获取会诊单列表
     * @return array
     */
    public function getDoctorOrderList (array $post)
    {
        $post['page_size']    = max(6, $post['page_size']);
        $post['print_code']   = trim_space($post['print_code']);
        $post['doctor_name']  = trim_space($post['doctor_name']);
        $post['patient_name'] = trim_space($post['patient_name']);

        // 条件查询
        $condition = [
            'clinic_id' => $this->userInfo['clinic_id']
        ];

        // 搜索时间
        $post['start_time'] = strtotime($post['start_time']);
        $post['end_time']   = strtotime($post['end_time']);

        if ($post['start_time'] && $post['end_time'] && $post['start_time'] <= $post['end_time']) {
            $condition['create_time'] = ['between', [date('Y-m-d H:i:s', $post['start_time']), date('Y-m-d H:i:s', $post['end_time'])]];
        }

        // 搜索状态
        if (!is_null(OrderStatus::format($post['status']))) {
            $condition['status'] = $post['status'];
        }

        // 搜索医生
        if ($post['doctor_id']) {
            $condition['doctor_id'] = intval($post['doctor_id']);
        }

        // 搜索医生姓名
        if ($post['doctor_name']) {
            $conditionForDoctor = [
                'clinic_id' => $condition['clinic_id']
            ];
            if (preg_match('/^\d+$/', $post['doctor_name'])) {
                if (!validate_telephone($post['doctor_name'])) {
                    $conditionForDoctor['user_name'] = $post['doctor_name'];
                } else {
                    $conditionForDoctor['telephone'] = $post['doctor_name'];
                }
            } else {
                $conditionForDoctor['user_name'] = $post['doctor_name'];
            }
            if (!$doctor = (new AdminModel())->find($conditionForDoctor, 'id')) {
                return success([
                    'total_count' => 0,
                    'page_size' => $post['page_size'],
                    'list' => []
                ]);
            }
            $condition['doctor_id'] = $doctor['id'];
        }

        // 搜索患者
        if ($post['patient_name']) {
            $condition['patient_name'] = ['like', '%' . $post['patient_name'] . '%'];
        }

        // 搜索凭条号
        if ($post['print_code']) {
            $condition['print_code'] = $post['print_code'];
        }

        $count = $this->count($condition);
        if ($count > 0) {
            $pagesize = getPageParams($post['page'], $count, $post['page_size']);
            $list = $this->select($condition, 'id,enum_source,doctor_id,patient_name,patient_gender,patient_age,patient_tel,pay,discount,payway,create_time,status', 'id desc', $pagesize['limitstr']);
            $userNames = (new AdminModel())->getAdminNames(array_column($list, 'doctor_id'));
            foreach ($list as $k => $v) {
                $list[$k]['source']      = OrderSource::getMessage($v['enum_source']);
                $list[$k]['patient_age'] = Gender::showAge($v['patient_age']);
                $list[$k]['payway']      = OrderPayWay::getMessage($v['payway']);
                $list[$k]['doctor_name'] = $v['doctor_id'] ? $userNames[$v['doctor_id']] : '无';
            }
            unset($userNames);
        }

        return success([
            'total_count' => $count,
            'page_size' => $post['page_size'],
            'list' => $list ? $list : []
        ]);
    }

    /**
     * 获取今日会诊
     * @return array
     */
    public function getTodayOrderList (array $post)
    {
        // 搜索时间
        $post['start_time'] = strtotime($post['start_time']);
        $post['end_time']   = strtotime($post['end_time']);

        if ($post['start_time'] && !$post['end_time']) {
            $post['end_time'] = $post['start_time'] + 86399;
        }

        if ($post['start_time'] > $post['end_time']) {
            return error('开始时间不能大于截止时间');
        }

        if (!$post['start_time'] || !$post['end_time']) {
            $post['start_time'] = strtotime(date('Y-m-d', TIMESTAMP));
            $post['end_time']   = TIMESTAMP;
        }

        $post['start_time'] = date('Y-m-d H:i:s', $post['start_time']);
        $post['end_time']   = date('Y-m-d H:i:s', $post['end_time']);

        // 条件查询
        $condition = [
            'clinic_id'   => $this->userInfo['clinic_id'],
            'doctor_id'   => $this->userInfo['id'],
            'enum_source' => OrderSource::DOCTOR,
            'create_time' => ['between', [$post['start_time'], $post['end_time']]]
        ];
        if (!is_null(OrderStatus::format($post['status']))) {
            $condition['status'] = $post['status'];
        }

        $count = $this->count($condition);
        if ($count > 0) {
            $pagesize = getPageParams($post['page'], $count, 5);
            $list = $this->select($condition, 'id,print_code,patient_name,patient_gender,create_time,status', 'id desc', $pagesize['limitstr']);
            foreach ($list as $k => $v) {
                $list[$k]['create_time'] = substr($v['create_time'], 11, 5);
            }
        }

        return success([
            'total_count' => $count,
            'page_size' => 5,
            'list' => $list ? $list : []
        ]);
    }

    /**
     * 编辑保存会诊单
     * @return array
     */
    public function saveDoctorCard (array $post)
    {
        // 验证会诊单
        $post['advanced'] = true;
        $post['clinic_id'] = $this->userInfo['clinic_id'];
        $post = $this->validationDoctorCard($post);
        if ($post['errorcode'] !== 0) {
            return $post;
        }
        $post = $post['result'];

        if (!$this->count(['id' => $post['order_id'], 'clinic_id' => $this->userInfo['clinic_id'], 'status' => OrderStatus::NOPAY])) {
            return error('订单不存在');
        }

        // 获取处方笺
        if (false === ($notes = $this->getDb()->table('dayi_order_notes')->where(['order_id' => $post['order_id']])->field('id')->select())) {
            return error('数据异常，请重新操作');
        }

        if (!$this->getDb()->transaction(function ($db) use ($post, $notes) {
            // 编辑会诊单
            if (!$db->where(['id' => $post['order_id'], 'status' => OrderStatus::NOPAY])->update([
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
                'update_time'       => date('Y-m-d H:i:s', TIMESTAMP)
            ])) {
                return false;
            }
            // 删除之前处方笺
            if ($notes) {
                if (!$db->partition($this->partition)->table('dayi_order_notes')->where(['id' => ['in', array_column($notes, 'id')]])->delete()) {
                    return false;
                }
            }
            // 新增处方笺
            foreach ($post['notes'] as $k => $v) {
                $post['notes'][$k]['clinic_id'] = $this->userInfo['clinic_id'];
                $post['notes'][$k]['order_id']  = $post['order_id'];
            }
            return $db->partition($this->partition)->table('dayi_order_notes')->insert($post['notes']);
        })) {
            return error('保存订单失败');
        }

        unset($post);
        return success('ok');
    }

    /**
     * 创建会诊单
     * @return array
     */
    public function createDoctorCard (array $post)
    {
        // 验证会诊单
        $post['clinic_id'] = $this->userInfo['clinic_id'];
        $post = $this->validationDoctorCard($post);
        if ($post['errorcode'] !== 0) {
            return $post;
        }
        $post = $post['result'];

        // 生成会诊号
        $printCode = $post['advanced'] ? null : $this->buildPrintCode($this->userInfo['clinic_id']);

        // 生成会诊单
        if (!$orderId = $this->getDb()->transaction(function ($db) use ($post, $printCode) {
            // 新增订单
            if (!$orderId = $db->insert([
                'clinic_id'         => $this->userInfo['clinic_id'],
                'doctor_id'         => $post['doctor_id'] ? $post['doctor_id'] : $this->userInfo['id'],
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
            ], null, true)) {
                return false;
            }
            if (empty($post['notes'])) {
                return $orderId;
            }
            // 新增处方笺
            foreach ($post['notes'] as $k => $v) {
                $post['notes'][$k]['clinic_id'] = $this->userInfo['clinic_id'];
                $post['notes'][$k]['order_id']  = $orderId;
            }
            if (!$db->partition($this->partition)->table('dayi_order_notes')->insert($post['notes'])) {
                return false;
            }
            return $orderId;
        })) {
            return error('保存订单失败');
        }

        unset($post);
        return success([
            'order_id'   => $orderId,
            'print_code' => $printCode
        ]);
    }

    /**
     * 验证会诊单
     * @return array
     */
    protected function validationDoctorCard (array $post)
    {
        $post['order_id']          = intval($post['order_id']);
        $post['doctor_id']         = intval($post['doctor_id']);
        $post['patient_name']      = trim_space($post['patient_name'], 0, 20);
        $post['patient_gender']    = Gender::format($post['patient_gender']);
        $post['patient_gender']    = $post['patient_gender'] ? $post['patient_gender'] : null;
        $post['patient_age']       = Gender::validationAge($post['patient_age']);
        $post['patient_tel']       = trim_space($post['patient_tel'], 0, 11);
        $post['patient_complaint'] = trim_space($post['patient_complaint'], 0, 200);
        $post['patient_allergies'] = trim_space($post['patient_allergies'], 0, 200);
        $post['patient_diagnosis'] = trim_space($post['patient_diagnosis'], 0, 200);
        $post['note_dose']         = max(0, intval($post['note_dose']));
        $post['note_side']         = NoteSide::format($post['note_side']);
        $post['advice']            = trim_space($post['advice'], 0, 200);
        $post['voice']             = ishttp($post['voice']) ? $post['voice'] : null;
        $post['notes']             = $post['notes'] ? array_slice(json_decode(htmlspecialchars_decode($post['notes']), true), 0, 30) : [];

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
        if (!$post['notes'] = $this->arrangeNotes($post['clinic_id'], $post['notes'], $post['note_dose'])) {
            return error('药品库存不足');
        }

        // 计算总金额
        $post['total_money'] = $this->totalMoney($post['notes']);

        return success($post);
    }

    /**
     * 生成打印票据号（4位）
     * @param $clinic_id 门店ID
     * @return string
     */
    protected function buildPrintCode ($clinic_id)
    {
        // 获取诊所
        if (!$clinicInfo = GenerateCache::getClinic($clinic_id)) {
            return null;
        }
        // is_pc:是否自动打印会诊号
        if (!$clinicInfo['is_pc']) {
            return null;
        }
        $code = (rand() % 10) . (rand() % 10) . (rand() % 10) . (rand() % 10);
        // 检查重复
        if ($this->getDb()->where(['clinic_id' => $clinic_id, 'print_code' => intval($code), 'status' => OrderStatus::NOPAY])->count()) {
            return $this->buildPrintCode($clinic_id);
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
            $price = $v['unit_price'] * $v['total_amount']; // 单价 * 总量
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
                if (!isset($list[$v['relation_id']])) {
                    $list[$v['relation_id']] = [];
                }
                if ($v['category'] == NoteCategory::CHINESE) {
                    // 草药剂量
                    $list[$v['relation_id']]['amount'] += ($v['total_amount'] * $v['dose']);
                } else {
                    $list[$v['relation_id']]['amount'] += $v['total_amount'];
                }
            }
        }
        return $list;
    }

    /**
     * 整理处方笺
     * @param $clinic_id
     * @param $notes
     * @param $dose 草药计量
     * @return array
     */
    protected function arrangeNotes ($clinic_id, array $notes, &$dose)
    {
        foreach ($notes as $k => $v) {
            $notes[$k]['relation_id']   = intval($v['relation_id']);
            $notes[$k]['total_amount']  = max(0, intval($v['total_amount']));
            $notes[$k]['single_amount'] = max(0, intval($v['single_amount']));
            $notes[$k]['usages']        = NoteUsage::format($v['usages']);
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
                if (!isset($list[1][$v['relation_id']])) {
                    $list[1][$v['relation_id']] = [];
                }
                if ($v['category'] == NoteCategory::CHINESE) {
                    // 草药剂量
                    $lookChineseDrug = true;
                    $dose = region_number($dose, 1, 1, 1000, 1000);
                    $list[1][$v['relation_id']]['amount'] += ($v['total_amount'] * $dose);
                } else {
                    $list[1][$v['relation_id']]['amount'] += $v['total_amount'];
                }
            } else {
                // 诊疗
                $list[2][$v['relation_id']] = $v['relation_id'];
            }
        }
        if (!$lookChineseDrug) {
            $dose = 0;
        }

        $drugModel      = new DrugModel(null, $clinic_id);
        $treatmentModel = new TreatmentModel();

        // 获取药品
        if (isset($list[1])) {
            if (!$list[1] = $drugModel->validationAmount($clinic_id, $list[1])) {
                return false;
            }
        }

        // 获取诊疗
        if (isset($list[2])) {
            if (!$list[2] = $treatmentModel->diffTreatment($list[2])) {
                return false;
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
                    'usages'        => $v['usages'],
                    'frequency'     => $v['frequency'],
                    'drug_days'     => $v['drug_days'],
                    'unit_price'    => $list[1][$v['relation_id']]['retail_price'],
                    'remark'        => null,
                    'dose'          => $dose
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
                    'usages'        => null,
                    'frequency'     => null,
                    'drug_days'     => null,
                    'unit_price'    => $list[2][$v['relation_id']]['price'],
                    'remark'        => $v['remark'],
                    'dose'          => $dose
                ];
            }
        }

        unset($drugModel, $treatmentModel, $notes, $list);
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
