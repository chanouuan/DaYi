<?php

namespace app\models;

use app\common\CommonStatus;
use app\common\DrugType;
use app\common\DrugDosage;
use app\common\NoteUsage;
use app\common\NoteFrequency;
use app\common\GenerateCache;
use app\common\StockType;
use Crud;

class DrugModel extends Crud {

    protected $table    = 'dayi_drug';
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
     * 药品库存效验
     * @return bool
     */
    public function validationAmount ($clinic_id, array $data, $force = true, $collect = true)
    {
        if ($clinic_id) {
            // 获取诊所
            if (!$clinicInfo = GenerateCache::getClinic($clinic_id)) {
                return false;
            }
            // is_ds:库存效验
            $force = $clinicInfo['is_ds'];
        }
        $list = [];
        foreach ($data as $k => $v) {
            if (isset($v['amount'])) {
                if ($force) {
                    $v['amount'] = ['>=' . abs($v['amount'])];
                } else {
                    unset($v['amount']);
                }
            }
            $v['id'] = $k;
            $v['status'] = CommonStatus::OK;
            if ($collect) {
                if (!$list[$k] = $this->find($v, 'drug_type,name,package_spec,dispense_unit,dosage_unit,retail_price')) {
                    return false;
                }
            } else {
                if (!$this->count($v)) {
                    return false;
                }
            }
        }
        unset($data);
        return $collect ? $list : true;
    }

    /**
     * 更新药品库存 + / -
     * @return bool
     */
    public function updateAmount ($clinic_id, $stock_type, array $data)
    {
        if (empty($data)) {
            return true;
        }
        if (!StockType::format($stock_type)) {
            return false;
        }
        if ($clinic_id) {
            // 获取诊所
            if (!$clinicInfo = GenerateCache::getClinic($clinic_id)) {
                return false;
            }
            // is_cp:收费即发药 is_rp:退费即退药
            if ($stock_type == StockType::PUSH) {
                // 收费
                if (!$clinicInfo['is_cp']) {
                    return true;
                }
            } elseif ($stock_type == StockType::PULL) {
                // 退费
                if (!$clinicInfo['is_rp']) {
                    return true;
                }
            } else {
                return false;
            }
        }
        foreach ($data as $k => $v) {
            $v['amount'] = ['amount' . StockType::getOp($stock_type) . abs($v['amount'])];
            if (!$this->getDb()->where(['id' => $k])->update($v)) {
                return false;
            }
        }
        return true;  
    }

    /**
     * 添加药品
     * @return array
     */
    public function saveDrug (array $post)
    {
        $post['id']     = intval($post['id']);
        $post['status'] = intval($post['status']);

        $data = [];
        $data['clinic_id']       = $this->userInfo['clinic_id'];
        $data['name']            = trim_space($post['name'], 0, 30);
        $data['drug_type']       = DrugType::format($post['drug_type']);
        $data['approval_num']    = trim_space($post['approval_num'], 0, 30);
        $data['package_spec']    = trim_space($post['package_spec'], 0, 30);
        $data['manufactor_name'] = trim_space($post['manufactor_name'], 0, 30);
        $data['basic_amount']    = $post['basic_amount'] ? max(0, intval($post['basic_amount'])) : null;
        $data['dosage_amount']   = $post['dosage_amount'] ? max(0, floatval($post['dosage_amount'])) : null;
        $data['py_code']         = trim_space($post['py_code'], 0, 20);
        $data['wb_code']         = trim_space($post['wb_code'], 0, 20);
        $data['dosage_type']     = DrugDosage::format($post['dosage_type']);
        $data['barcode']         = trim_space($post['barcode'], 0, 20);
        $data['goods_name']      = trim_space($post['goods_name'], 0, 30);
        $data['standard_code']   = trim_space($post['standard_code'], 0, 30);
        $data['drug_code']       = trim_space($post['drug_code'], 0, 32);
        $data['retail_price']    = $post['retail_price'] ? max(0, intval(floatval($post['retail_price']) * 100)) : null;
        $data['is_antibiotic']   = DrugType::isWestNeutralDrug($data['drug_type']) ? ($post['is_antibiotic'] ? 1 : 0) : null;
        $data['usages']          = NoteUsage::format($post['usages']);
        $data['frequency']       = NoteFrequency::format($post['frequency']);
        $data['basic_unit']      = trim_space($post['basic_unit'], 0, 5);
        $data['dosage_unit']     = trim_space($post['dosage_unit'], 0, 5);
        $data['dispense_unit']   = trim_space($post['dispense_unit'], 0, 5);

        if (!$data['clinic_id']) {
            return error('门诊不能为空');
        }
        if (!$data['name']) {
            return error('药品名称不能为空');
        }
        if (!$data['drug_type']) {
            return error('药品类型不能为空');
        }
        if (!$data['package_spec']) {
            return error('药品规格不能为空');
        }
        if (!$data['dispense_unit']) {
            return error('库存单位不能为空');
        }
        if (!$data['retail_price']) {
            return error('零售价格不能为空');
        }
        if (DrugType::isWestNeutralDrug($data['drug_type'])) {
            if (!$data['dosage_type']) {
                return error('药品剂型不能为空');
            }
            if (!$data['basic_amount']) {
                return error('制剂数量不能为空');
            }
            if (!$data['basic_unit']) {
                return error('制剂单位不能为空');
            }
            if (!$data['dosage_amount']) {
                return error('剂量不能为空');
            }
            if (!$data['dosage_unit']) {
                return error('剂量单位不能为空');
            }
        }
        
        // 新增 or 编辑
        if ($post['id']) {
            if (!is_null(CommonStatus::format($post['status']))) {
                $data['status'] = $post['status'];
            }
            $data['update_time'] = date('Y-m-d H:i:s', TIMESTAMP);
            if (!$this->getDb()->where(['id' => $post['id'], 'clinic_id' => $data['clinic_id']])->update($data)) {
                return error('该药品/材料已存在！');
            }
        } else {
            $data['create_time'] = date('Y-m-d H:i:s', TIMESTAMP);
            if (!$this->getDb()->insert($data)) {
                return error('请勿添加重复的药品/材料！');
            }
        }
        
        return success('ok');
    }

    /**
     * 搜索
     * @param $clinic_id 门店
     * @param $drug_type 类型
     * @param $name 名称
     * @param $limit
     * @return array
     */
    public function search (array $post, $field = null, $limit = 5)
    {
        $condition = [
            'clinic_id' => $post['clinic_id'],
            'status'    => CommonStatus::OK
        ];
        if ($post['drug_type']) {
            $condition['drug_type'] = is_array($post['drug_type']) ? ['in', $post['drug_type']] : $post['drug_type'];
        }
        if ($post['name']) {
            $condition['name']    = ['like', '%' . $post['name'] . '%', 'and ('];
            $condition['barcode'] = ['=', $post['name'], 'or'];
            $condition['py_code'] = ['like', $post['name'] . '%', 'or'];
            $condition['wb_code'] = ['like', $post['name'] . '%', 'or', ')'];
        }
        if ($post['barcode']) {
            $condition['barcode'] = $post['barcode'];
        }
        if ($post['is_procure']) {
            $condition['is_procure'] = 1;
        }
        $field = $field ? $field : 'id,drug_type,name,package_spec,dispense_unit,dosage_unit,dosage_amount,retail_price as price,amount,manufactor_name,usages,frequency';
        if (!$list = $this->select($condition, $field, null, $limit)) {
            return [];
        }
        foreach ($list as $k => $v) {
            $list[$k]['price'] = round_dollar($v['price']);
        }
        return $list;
    }

    /**
     * 获取信息
     * @return array
     */
    public function getDrugInfo ($id)
    {
        $id = intval($id);
        if (!$info = $this->find(['id' => $id], 'id,drug_type,approval_num,name,package_spec,manufactor_name,dispense_unit,basic_amount,basic_unit,dosage_unit,dosage_amount,py_code,wb_code,dosage_type,barcode,goods_name,standard_code,drug_code,retail_price,is_antibiotic,usages,frequency,status')) {
            return [];
        }
        $info['drug_type']    = strval($info['drug_type']);
        $info['retail_price'] = round_dollar($info['retail_price']);
        return $info;
    }

    /**
     * 获取列表
     * @return array
     */
    public function getDrugList (array $post)
    {
        $post['page_size'] = max(6, $post['page_size']);
        $post['name']      = trim_space($post['name']);

        $condition = [
            'clinic_id' => $this->userInfo['clinic_id']
        ];
        if (!is_null(CommonStatus::format($post['status']))) {
            $condition['status'] = $post['status'];
        }
        if (!is_null(CommonStatus::format($post['is_procure']))) {
            $condition['is_procure'] = $post['is_procure'];
        }
        if (DrugType::format($post['drug_type'])) {
            $condition['drug_type'] = $post['drug_type'];
        }
        if ($post['name']) {
            $condition['name']    = ['like', $post['name'] . '%', 'and ('];
            $condition['py_code'] = ['like', $post['name'] . '%', 'or'];
            $condition['wb_code'] = ['like', $post['name'] . '%', 'or', ')'];
        }

        $count = $this->count($condition);
        $list  = [];
        if ($count > 0) {
            if (!$post['export']) {
                $pagesize = getPageParams($post['page'], $count, $post['page_size']);
            }
            $list = $this->select($condition, 'id,drug_type,name,package_spec,dispense_unit,purchase_price,retail_price,amount,manufactor_name,status', 'id desc', $pagesize['limitstr']);
            foreach ($list as $k => $v) {
                $list[$k]['purchase_price'] = round_dollar($v['purchase_price']);
                $list[$k]['retail_price']   = round_dollar($v['retail_price']);
                $list[$k]['type_name']      = DrugType::getMessage($v['drug_type']);
            }
        }

        // 导出
        if ($post['export']) {
            $input = [];
            foreach ($list as $k => $v) {
                $input[] = [
                    $v['id'], 
                    $v['name'], 
                    $v['type_name'], 
                    $v['package_spec'], 
                    $v['manufactor_name'], 
                    $v['retail_price'], 
                    $v['purchase_price'], 
                    $v['amount'] . $v['dispense_unit']
                ];
            }
            $this->exportCsv('库存列表', '编号,名称,类型,规格,厂家,零售价,进货价,库存', $input);
        }

        return success([
            'total_count' => $count,
            'page_size' => $post['page_size'],
            'list' => $list ? $list : []
        ]);
    }

    /**
     * 导出为 csv
     * @return fixed
     */
    private function exportCsv ($fileName, $header, array $list)
    {
        $fileName = $fileName . '_' . date('Ymd', TIMESTAMP);
        $fileName = preg_match('/(Chrome|Firefox)/i', $_SERVER['HTTP_USER_AGENT']) && !preg_match('/edge/i', $_SERVER['HTTP_USER_AGENT']) ? $fileName : urlencode($fileName);

        header('cache-control:public');
        header('content-type:application/octet-stream');
        header('content-disposition:attachment; filename=' . $fileName . '.csv');

        $input = [$header];
        foreach ($list as $k => $v) {
            foreach ($v as $kk => $vv) {
                if (false !== strpos($vv, ',')) {
                    $v[$kk] = '"' . $vv . '"';
                }
            }
            $input[] = implode(',', $v);
        }
        unset($list);

        echo mb_convert_encoding(implode("\n", $input), 'GB2312', 'UTF-8');
        exit(0);
    }

}
