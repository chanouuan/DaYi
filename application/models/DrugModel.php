<?php

namespace app\models;

use app\common\CommonStatus;
use app\common\DrugType;
use app\common\DrugDosage;
use app\common\NoteUsage;
use app\common\NoteFrequency;
use Crud;

class DrugModel extends Crud {

    protected $table = 'dayi_drug';

    /**
     * 添加药品
     * @return array
     */
    public function saveDrug ($user_id, $post)
    {
        $userInfo = (new AdminModel())->checkAdminInfo($user_id);
        if ($userInfo['errorcode'] !== 0) {
            return $userInfo;
        }
        $userInfo = $userInfo['result'];

        $post['id']     = intval($post['id']);
        $post['status'] = intval($post['status']);

        $data = [];
        $data['clinic_id']       = $userInfo['clinic_id'];
        $data['name']            = $post['name'] ? trim_space($post['name']) : null;
        $data['drug_type']       = DrugType::format($post['drug_type']);
        $data['approval_num']    = $post['approval_num'] ? trim_space($post['approval_num']) : null;
        $data['package_spec']    = $post['package_spec'] ? trim_space($post['package_spec']) : null;
        $data['manufactor_name'] = $post['manufactor_name'] ? trim_space($post['manufactor_name']) : null;
        $data['basic_amount']    = $post['basic_amount'] ? max(0, intval($post['basic_amount'])) : null;
        $data['dosage_amount']   = $post['dosage_amount'] ? max(0, floatval($post['dosage_amount'])) : null;
        $data['py_code']         = $post['py_code'] ? trim_space($post['py_code']) : null;
        $data['wb_code']         = $post['wb_code'] ? trim_space($post['wb_code']) : null;
        $data['dosage_type']     = DrugDosage::format($post['dosage_type']);
        $data['barcode']         = $post['barcode'] ? trim_space($post['barcode']) : null;
        $data['goods_name']      = $post['goods_name'] ? trim_space($post['goods_name']) : null;
        $data['standard_code']   = $post['standard_code'] ? trim_space($post['standard_code']) : null;
        $data['drug_code']       = $post['drug_code'] ? trim_space($post['drug_code']): null;
        $data['retail_price']    = $post['retail_price'] ? max(0, intval(floatval($post['retail_price']) * 100)) : null;
        $data['is_antibiotic']   = DrugType::isWestNeutralDrug($data['drug_type']) ? ($post['is_antibiotic'] ? 1 : 0) : null;
        $data['usages']          = NoteUsage::format($post['usages']);
        $data['frequency']       = NoteFrequency::format($post['frequency']);
        $data['basic_unit']      = $post['basic_unit'] ? trim_space($post['basic_unit']) : null;
        $data['dosage_unit']     = $post['dosage_unit'] ? trim_space($post['dosage_unit']) : null;
        $data['dispense_unit']   = $post['dispense_unit'] ? trim_space($post['dispense_unit']) : null;

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
            if (!$this->getDb()->update($this->table, $data, ['id' => $post['id'], 'clinic_id' => $post['clinic_id']])) {
                return error('该药品/材料已存在！');
            }
        } else {
            $data['create_time'] = date('Y-m-d H:i:s', TIMESTAMP);
            if (!$this->getDb()->insert($this->table, $data)) {
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
    public function search ($clinic_id, $drug_type, $name, $limit = 5)
    {
        $condition = [
            'clinic_id' => $clinic_id,
            'status'   => CommonStatus::OK
        ];
        if ($drug_type) {
            $condition['drug_type'] = is_array($drug_type) ? ['in', $drug_type] : $drug_type;
        }
        if ($name) {
            $condition['name']    = ['like', $name . '%', 'and ('];
            $condition['py_code'] = ['like', $name . '%', 'or'];
            $condition['wb_code'] = ['like', $name . '%', 'or', ')'];
        }
        if (!$list = $this->select($condition, 'id,drug_type,name,package_spec,dispense_unit,dosage_unit,dosage_amount,retail_price as price,amount,usages,frequency', null, $limit)) {
            return [];
        }
        foreach ($list as $k => $v) {
            $list[$k]['price'] = round_dollar($v['price']);
            $list[$k]['reserve'] = $v['amount'] . $v['dispense_unit'];
        }
        return $list;
    }

    /**
     * 查询药品字典
     * @param $drug_type
     * @param $name
     * @param $limit
     * @return array
     */
    public function searchDict ($drug_type, $name, $limit = 5)
    {
        $condition = [];
        if ($drug_type) {
            $condition['drug_type'] = is_array($drug_type) ? ['in', $drug_type] : $drug_type;
        }
        if ($name) {
            $condition['name']    = ['like', $name . '%', 'and ('];
            $condition['py_code'] = ['like', $name . '%', 'or'];
            $condition['wb_code'] = ['like', $name . '%', 'or'];
            $condition['approval_num'] = ['=', $name, 'or'];
            $condition['barcode'] = ['=', $name, 'or', ')'];
        }
        if (!$list = $this->getDb()->table('dayi_drug_dict')->field('id,drug_type,approval_num,name,package_spec,manufactor_name,dispense_unit,basic_amount,basic_unit,dosage_unit,dosage_amount,py_code,wb_code,dosage_type,barcode,goods_name,standard_code,drug_code,retail_price')->where($condition)->limit($limit)->select()) {
            return [];
        }
        $drugTypeEnum   = array_flip(DrugType::$message);
        $drugDosageEnum = array_flip(DrugDosage::$message);
        foreach ($list as $k => $v) {
            $list[$k]['drug_type']   = strval($drugTypeEnum[$v['drug_type']]);
            $list[$k]['dosage_type'] = intval($drugDosageEnum[$v['dosage_type']]);
        }
        unset($drugTypeEnum, $drugDosageEnum);
        return $list;
    }

    /**
     * 获取信息
     * @return array
     */
    public function getDrugInfo ($id)
    {
        $id = intval($id);
        if (!$info = $this->getDb()->table($this->table)->field('id,drug_type,approval_num,name,package_spec,manufactor_name,dispense_unit,basic_amount,basic_unit,dosage_unit,dosage_amount,py_code,wb_code,dosage_type,barcode,goods_name,standard_code,drug_code,retail_price,is_antibiotic,usages,frequency,status')->where(['id' => $id])->limit(1)->find()) {
            return [];
        }
        $info['drug_type']     = strval($info['drug_type']);
        $info['retail_price']  = round_dollar($info['retail_price']);
        $info['is_antibiotic'] = $info['is_antibiotic'] ? '1' : '';
        $info['status']        = $info['status'] ? '1' : '';
        return $info;
    }

    /**
     * 获取列表
     * @return array
     */
    public function getDrugList ($user_id, array $post)
    {
        $post['page_size'] = max(6, $post['page_size']);
        $post['name']      = trim_space($post['name']);

        // 用户获取
        $userInfo = (new AdminModel())->checkAdminInfo($user_id);
        if ($userInfo['errorcode'] !== 0) {
            return $userInfo;
        }
        $userInfo = $userInfo['result'];

        $condition = [
            'clinic_id' => $userInfo['clinic_id']
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

        $count = $this->getDb()->table($this->table)->where($condition)->count();
        if ($count > 0) {
            $pagesize = getPageParams($post['page'], $count, $post['page_size']);
            $list = $this->select($condition, 'id,drug_type,name,package_spec,dispense_unit,purchase_price,retail_price,amount,manufactor_name,status', 'id desc', $pagesize['limitstr']);
            if ($list) {
                foreach ($list as $k => $v) {
                    $list[$k]['purchase_price'] = round_dollar($v['purchase_price']);
                    $list[$k]['retail_price']   = round_dollar($v['retail_price']);
                    $list[$k]['type_name']      = DrugType::getMessage($v['drug_type']);
                }
            }
        }

        return success([
            'total_count' => $count,
            'page_size' => $post['page_size'],
            'list' => $list ? $list : []
        ]);
    }

}
