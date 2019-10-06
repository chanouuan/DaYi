<?php

namespace app\models;

use app\common\OrderPayFlow;
use app\common\OrderPayWay;
use Crud;

class PayFlowModel extends Crud {

    protected $table = 'dayi_pay_flow';

    /**
     * 添加资金流水
     * @param $flow_type 操作类型
     * @param $order_id 订单id
     * @param $payway 付款方式
     * @param $money 付款金额/退款金额
     * @param $second_payway 第二种付款方式
     * @param $second_money 第二种付款金额
     * @param $remark 备注
     * @return bool
     */
    public function insert ($flow_type, $order_id, $payway, $money, $second_payway = null, $second_money = null, $remark = null)
    {
        if ($flow_type == OrderPayFlow::REFUND) {
            $second_payway = null;
            $second_money  = null;
        }

        $data = [
            'order_id'    => $order_id,
            'flow_type'   => $flow_type,
            'payway'      => $payway,
            'money'       => $money,
            'remark'      => $remark,
            'create_time' => date('Y-m-d H:i:s', TIMESTAMP)
        ];
        if (OrderPayWay::isLocalPayWay($second_payway)) {
            $data = [
                'order_id'    => [$order_id, $order_id],
                'flow_type'   => [$flow_type, $flow_type],
                'payway'      => [$payway, $second_payway],
                'money'       => [$money, $second_money],
                'remark'      => [$remark, $remark],
                'create_time' => [date('Y-m-d H:i:s', TIMESTAMP), date('Y-m-d H:i:s', TIMESTAMP)]
            ];
        }

        return $this->getDb()->insert($this->table, $data);
    }

}
