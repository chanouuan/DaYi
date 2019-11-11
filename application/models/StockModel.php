<?php

namespace app\models;

use app\common\CommonStatus;
use app\common\StockType;
use app\common\StockWay;
use app\common\DrugType;
use app\common\GenerateCache;
use Crud;

class StockModel extends Crud {

    protected $table    = 'dayi_stock';
    protected $userInfo = null;

    public function __construct ($user_id)
    {
        // 分区
        $userInfo = (new AdminModel())->checkAdminInfo($user_id);
        if ($userInfo['errorcode'] !== 0) {
            json(null, $userInfo['message'], $userInfo['errorcode']);
        }
        $this->userInfo = $userInfo['result'];
        list($this->link, $this->partition) = GenerateCache::getClinicPartition($this->userInfo['clinic_id']);
    }

    /**
     * 获取出入库列表
     * @return array
     */
    public function getStockPullOrPush (array $post)
    {
    	$post['page_size'] = max(6, $post['page_size']);

        $condition = [
            'clinic_id' => $this->userInfo['clinic_id']
        ];
        $post['start_time'] = strtotime($post['start_time']);
        $post['end_time']   = strtotime($post['end_time']);
        if ($post['start_time'] && $post['end_time'] && $post['start_time'] <= $post['end_time']) {
            $condition['stock_date'] = ['between', [date('Y-m-d', $post['start_time']), date('Y-m-d', $post['end_time'])]];
        }
        if (StockType::format($post['stock_type'])) {
            $condition['stock_type'] = $post['stock_type'];
        }
        if (!is_null(CommonStatus::format($post['status']))) {
            $condition['status'] = $post['status'];
        }
        if (!is_null(StockWay::format($post['stock_way']))) {
            $condition['stock_way'] = $post['stock_way'];
        }

        $count = $this->count($condition);
        if ($count > 0) {
            $pagesize = getPageParams($post['page'], $count, $post['page_size']);
            $list = $this->select($condition, 'id,stock_type,stock_way,supplier,employee_id,purchase_price,create_admin_id,confirm_admin_id,stock_date,status', 'id desc', $pagesize['limitstr']);
            if ($list) {
                // 获取用户姓名
                $admins = (new AdminModel())->getAdminNames(array_merge(
                    array_column($list, 'employee_id'), array_column($list, 'create_admin_id'), array_column($list, 'confirm_admin_id')
                ));
                foreach ($list as $k => $v) {
                    $list[$k]['purchase_price']     = round_dollar($v['purchase_price']);
                    $list[$k]['stock_way']          = StockWay::getMessage($v['stock_way']);
                    $list[$k]['employee_name']      = strval($admins[$v['employee_id']]);
                    $list[$k]['create_admin_name']  = strval($admins[$v['create_admin_id']]);
                    $list[$k]['confirm_admin_name'] = strval($admins[$v['confirm_admin_id']]);
                }
                unset($admins);
            }
        }

        return success([
            'total_count' => $count,
            'page_size' => $post['page_size'],
            'list' => $list ? $list : []
        ]);
    }

    /**
     * 出入库详情
     * @return array
     */
    public function stockDetail ($stock_id)
    {
        $stock_id = intval($stock_id);

        if (!$stockInfo = $this->find(['id' => $stock_id], 'id,stock_type,stock_way,supplier,invoice,employee_id,purchase_price,create_admin_id,confirm_admin_id,stock_date,purchase_price,remark,status')) {
            return error('出入库单不存在');
        }

        $stockInfo['stock_way']       = StockWay::getMessage($stockInfo['stock_way']);
        $stockInfo['purchase_price']  = round_dollar($stockInfo['purchase_price']);
        // 获取用户姓名
        $admins = (new AdminModel())->getAdminNames([
            $stockInfo['employee_id'], $stockInfo['create_admin_id'], $stockInfo['confirm_admin_id']
        ]);
        $stockInfo['employee_name']      = strval($admins[$stockInfo['employee_id']]);
        $stockInfo['create_admin_name']  = strval($admins[$stockInfo['create_admin_id']]);
        $stockInfo['confirm_admin_name'] = strval($admins[$stockInfo['confirm_admin_id']]);
        unset($admins);

        // 获取药品详情
        $stockInfo['details'] = $this->getDb()
            ->table('dayi_stock_detail')
            ->field('id,drug_type,name,package_spec,dispense_unit,retail_price,manufactor_name,amount,purchase_price,batch_number,valid_time')
            ->where(['stock_id' => $stockInfo['id']])
            ->order('id')
            ->select();
        foreach ($stockInfo['details'] as $k => $v) {
            $stockInfo['details'][$k]['drug_type']      = DrugType::getMessage($v['drug_type']);
            $stockInfo['details'][$k]['retail_price']   = round_dollar($v['retail_price']);
            $stockInfo['details'][$k]['purchase_price'] = round_dollar($v['purchase_price']);
        }

        return success($stockInfo);
    }

    /**
     * 获取进销存详情
     * @return array
     */
    public function getStockSale (array $post)
    {
        $post['page_size'] = max(6, $post['page_size']);
        $post['drug_id']   = intval($post['drug_id']);

        $condition = [
            'detail.clinic_id' => $this->userInfo['clinic_id'],
            'detail.drug_id'   => $post['drug_id'],
            'stock.status'     => 1
        ];
        $post['start_time'] = strtotime($post['start_time']);
        $post['end_time']   = strtotime($post['end_time']);
        if ($post['start_time'] && $post['end_time'] && $post['start_time'] <= $post['end_time']) {
            $condition['stock.stock_date'] = ['between', [date('Y-m-d', $post['start_time']), date('Y-m-d', $post['end_time'])]];
        }
        if (!is_null(StockWay::format($post['stock_way']))) {
            $condition['stock.stock_way'] = $post['stock_way'];
        }

        $count = $this->getDb()
            ->table('dayi_stock_detail__partition__ detail left join dayi_stock__partition__ stock on stock.id = detail.stock_id')
            ->where($condition)
            ->count();
        if ($count > 0) {
            $pagesize = getPageParams($post['page'], $count, $post['page_size']);
            $list = $this->getDb()
                ->field('detail.id,detail.amount,detail.dispense_unit,detail.retail_price,detail.purchase_price,detail.batch_number,detail.valid_time,stock.stock_way,stock.stock_date,stock.supplier')
                ->table('dayi_stock_detail__partition__ detail left join dayi_stock__partition__ stock on stock.id = detail.stock_id')
                ->where($condition)
                ->order('detail.id desc')
                ->limit($pagesize['limitstr'])
                ->select();
            if ($list) {
                foreach ($list as $k => $v) {
                    $list[$k]['retail_price']   = round_dollar($v['retail_price']);
                    $list[$k]['purchase_price'] = round_dollar($v['purchase_price']);
                    $list[$k]['stock_way']      = StockWay::getMessage($v['stock_way']);
                }
            }
        }

        return success([
            'total_count' => $count,
            'page_size' => $post['page_size'],
            'list' => $list ? $list : []
        ]);
    }

    /**
     * 批次详情
     * @return array
     */
    public function batchDetail (array $post)
    {
        $post['page_size'] = max(6, $post['page_size']);
        $post['drug_id']   = intval($post['drug_id']);

        $condition = [
            'clinic_id' => $this->userInfo['clinic_id'],
            'drug_id'   => $post['drug_id'],
            'amount'    => ['>0']
        ];

        $count = $this->getDb()
            ->table('dayi_stock_detail')
            ->where($condition)
            ->count();
        if ($count > 0) {
            $pagesize = getPageParams($post['page'], $count, $post['page_size']);
            $list = $this->getDb()
                ->table('dayi_stock_detail')
                ->field('id,name,package_spec,manufactor_name,dispense_unit,amount,purchase_price,batch_number,valid_time')
                ->where($condition)
                ->order('id desc')
                ->limit($pagesize['limitstr'])
                ->select();
            if ($list) {
                foreach ($list as $k => $v) {
                    $list[$k]['purchase_price'] = round_dollar($v['purchase_price']);
                }
            }
        }

        return success([
            'total_count' => $count,
            'page_size' => $post['page_size'],
            'list' => $list ? $list : []
        ]);
    }

    /**
     * 删除出入库
     * @return array
     */
    public function delStock (array $post)
    {
        $post['stock_id'] = intval($post['stock_id']);

        if (!$stockInfo = $this->find(['id' => $post['stock_id'], 'clinic_id' => $this->userInfo['clinic_id'], 'status' => CommonStatus::NOT], 'id')) {
            return error('出入库单不存在');
        }

        if (!$this->getDb()->transaction(function ($db) use($stockInfo) {
            // 删除出入库
            if (!$db->where(['id' => $stockInfo['id'], 'status' => CommonStatus::NOT])->delete()) {
                return false;
            }
            if (!$db->partition($this->partition)->table('dayi_stock_detail')->where(['stock_id' => $stockInfo['id']])->delete()) {
                return false;
            }
            return true;
        })) {
            return error('删除失败');
        }

        return success('ok');
    }

    /**
     * 确认出入库
     * @return array
     */
    public function confirmStock (array $post)
    {
        $post['stock_id'] = intval($post['stock_id']);

        if (!$stockInfo = $this->find(['id' => $post['stock_id'], 'clinic_id' => $this->userInfo['clinic_id'], 'status' => CommonStatus::NOT], 'id,stock_type')) {
            return error('出入库单不存在');
        }

        // 获取药品详情
        if (!$details = $this->getDb()->table('dayi_stock_detail')->where(['stock_id' => $stockInfo['id']])->field('drug_id,amount,purchase_price')->order('id')->select()) {
            return error('药品详情不存在');
        }

        $details = $this->totalAmount($details);

        $drugModel = new DrugModel();

        // 检查库存
        $validations = [];
        foreach ($details as $k => $v) {
            if ($v['amount'] < 0) {
                $validations[$k]['amount'] = $v['amount'];
            }
        }
        if ($validations) {
            if (!$drugModel->validationAmount(null, $validations, true, false)) {
                return error('库存不足，请检查');
            }
        }

        if (!$this->getDb()->transaction(function ($db) use($stockInfo, $details, $drugModel) {
            // 更新出入库
            if (!$db->where(['id' => $stockInfo['id'], 'status' => CommonStatus::NOT])->update([
                'confirm_admin_id' => $this->userInfo['id'],
                'status'           => CommonStatus::OK,
                'update_time'      => date('Y-m-d H:i:s', TIMESTAMP)
            ])) {
                return false;
            }
            // 减库存 or 加库存
            $data = [];
            foreach ($details as $k => $v) {
                $data[$k]['amount'] = $v['amount'];
                if ($v['amount'] > 0) {
                    // 更新药品进货价
                    $data[$k]['is_procure'] = 1;
                    $data[$k]['purchase_price'] = $v['purchase_price'];
                }
            }
            return $drugModel->updateAmount(null, StockType::getOp($stockInfo['stock_type']), $data);
        })) {
            return error('库存不足');
        }

        return success('ok');
    }

    /**
     * 编辑出入库
     * @return array
     */
    public function editStock (array $post)
    {
        $post['stock_id'] = intval($post['stock_id']);
        $post['invoice']  = trim_space($post['invoice']);

        if (!$stockInfo = $this->find(['id' => $post['stock_id'], 'clinic_id' => $this->userInfo['clinic_id']], 'id')) {
            return error('出入库单不存在');
        }

        if (false === $this->getDb()->where(['id' => $stockInfo['id']])->update([
            'invoice' => $post['invoice']
        ])) {
            return error('保存数据失败');
        }

        return success('ok');
    }

    /**
     * 新增出入库
     * @return array
     */
    public function addStock (array $post)
    {
        $post['clinic_id'] = $this->userInfo['clinic_id'];

        // 验证出入库表单
        $post = $this->validationStockForm($post);
        if ($post['errorcode'] !== 0) {
            return $post;
        }
        $post = $post['result'];

        if (!$stockId = $this->getDb()->transaction(function ($db) use($post) {
            // 新增出入库
            if (!$stockId = $db->insert([
                'clinic_id'       => $post['clinic_id'],
                'stock_type'      => $post['stock_type'],
                'stock_way'       => $post['stock_way'],
                'stock_date'      => $post['stock_date'],
                'supplier'        => $post['supplier'],
                'invoice'         => $post['invoice'],
                'employee_id'     => $post['employee_id'],
                'purchase_price'  => $post['purchase_price'],
                'remark'          => $post['remark'],
                'create_admin_id' => $this->userInfo['id'],
                'update_time'     => date('Y-m-d H:i:s', TIMESTAMP),
                'create_time'     => date('Y-m-d H:i:s', TIMESTAMP)
            ], null, true)) {
                return false;
            }
            // 新增出入库详情
            foreach ($post['details'] as $k => $v) {
                $post['details'][$k]['stock_id'] = $stockId;
            }
            if (!$db->partition($this->partition)->table('dayi_stock_detail')->insert($post['details'])) {
                return false;
            }
            return $stockId;
        })) {
            return error('保存数据失败');
        }

        unset($post);
        return success([
            'stock_id' => $stockId
        ]);
    }

    /**
     * 验证入库表单
     * @return array
     */
    protected function validationStockForm (array $post)
    {
        $post['stock_type']  = StockType::format($post['stock_type']);
        $post['stock_date']  = strtotime($post['stock_date']);
        $post['stock_date']  = $post['stock_date'] ? date('Y-m-d', $post['stock_date']) : null;
        $post['stock_way']   = StockWay::format($post['stock_way']);
        $post['supplier']    = trim_space($post['supplier']);
        $post['invoice']     = trim_space($post['invoice']);
        $post['remark']      = trim_space($post['remark']);
        $post['employee_id'] = $post['employee_id'] ? intval($post['employee_id']) : null;
        $post['details']     = $post['details'] ? array_slice(json_decode(htmlspecialchars_decode($post['details']), true), 0, 1000) : [];

        if (!$post['stock_type']) {
            return error('出入库类型不正确');
        }
        if (!$post['stock_date']) {
            return error('出入库日期不正确');
        }
        if (!$post['stock_way']) {
            return error('出入库方式不正确');
        }
        if (!$post['details']) {
            return error('请添加药品');
        }

        // 药品详情
        if (!$post['details'] = $this->arrangeDetails($post['details'], $post['stock_type'], $post['clinic_id'])) {
            return error('药品库存不足');
        }

        // 计算入库总金额
        $post['purchase_price'] = $this->totalMoney($post['details']);

        return success($post);
    }

    /**
     * 整理出入库药品详情
     * @return array
     */
    protected function arrangeDetails (array $details, $stock_type, $clinic_id)
    {
        foreach ($details as $k => $v) {
            $details[$k]['drug_id']        = intval($v['drug_id']);
            $details[$k]['amount']         = max(0, intval($v['amount']));
            $details[$k]['amount']         = $stock_type == StockType::PUSH ? 0 - $details[$k]['amount'] : $details[$k]['amount'];
            $details[$k]['purchase_price'] = $stock_type == StockType::PUSH ? null : max(0, intval(floatval($v['purchase_price']) * 100));
            $details[$k]['batch_number']   = trim_space($v['batch_number']);
            $details[$k]['valid_time']     = strtotime($v['valid_time']);
            $details[$k]['valid_time']     = $details[$k]['valid_time'] ? date('Y-m-d', $details[$k]['valid_time']) : null;
            $details[$k]['manufactor_name'] = trim_space($v['manufactor_name']);
            if (!$details[$k]['drug_id'] || !$details[$k]['amount']) {
                return false;
            }
        }

        // 获取药品数量
        $list = [];
        foreach ($details as $k => $v) {
            $list[$v['drug_id']] += $v['amount'];
        }

        // 检查库存
        $validations = [];
        foreach ($list as $k => $v) {
            $validations[$k]['clinic_id'] = $clinic_id;
            if ($stock_type == StockType::PUSH) {
                $validations[$k]['amount'] = abs($v);
            }
        }
        if (!$list = (new DrugModel())->validationAmount(null, $validations, true, true)) {
            return false;
        }

        $result = [];
        foreach ($details as $k => $v) {
            $result[] = [
                'clinic_id'       => $clinic_id,
                'drug_id'         => $v['drug_id'],
                'name'            => $list[$v['drug_id']]['name'],
                'drug_type'       => $list[$v['drug_id']]['drug_type'],
                'package_spec'    => $list[$v['drug_id']]['package_spec'],
                'dispense_unit'   => $list[$v['drug_id']]['dispense_unit'],
                'retail_price'    => $list[$v['drug_id']]['retail_price'],
                'manufactor_name' => $v['manufactor_name'],
                'amount'          => $v['amount'],
                'purchase_price'  => $v['purchase_price'],
                'batch_number'    => $v['batch_number'],
                'valid_time'      => $v['valid_time'],
                'create_time'     => date('Y-m-d H:i:s', TIMESTAMP)
            ];
        }

        unset($details, $list);
        return $result;
    }

    /**
     * 合计入库总金额
     * @param array $notes
     * @return int
     */
    protected function totalMoney (array $details)
    {
        $total = 0;
        foreach ($details as $k => $v) {
            if ($v['amount'] > 0) {
                $total += $v['purchase_price'] * $v['amount'];
            }  
        }
        return $total > 0 ? $total :null;
    }

    /**
     * 合计出入库药品总量
     * @param array $notes
     * @return array
     */
    protected function totalAmount (array $details)
    {
        $list = [];
        foreach ($details as $k => $v) {
            if (!isset($list[$v['drug_id']])) {
                $list[$v['drug_id']] = [];
            }
            $list[$v['drug_id']]['purchase_price'] = $v['purchase_price'];
            $list[$v['drug_id']]['amount'] += $v['amount'];
        }
        return $list;
    }

}
