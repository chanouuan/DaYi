<?php

namespace app\models;

use app\common\DrugType;
use app\common\DrugDosage;
use Crud;

class DrugDictModel extends Crud {

    protected $table = 'dayi_drug_dict';

    /**
     * 查询药品字典
     * @param $drug_type
     * @param $name
     * @param $limit
     * @return array
     */
    public function searchDict ($drug_type, $name, $limit = 5)
    {
        $condition = [
            'drug_type' => is_array($drug_type) ? ['in', $drug_type] : $drug_type
        ];
        if ($name) {
            if (preg_match('/^[0-9|a-z|A-Z]+$/', $name)) {
                $condition['py_code']      = ['like', $name . '%', 'and ('];
                $condition['wb_code']      = ['like', $name . '%', 'or'];
                $condition['approval_num'] = ['=', $name, 'or'];
                $condition['barcode']      = ['=', $name, 'or', ')'];
            } else {
                $condition['name'] = ['like', '%' . $name . '%'];
            }
        } else {
            return [];
        }
        if (!$list = $this->select($condition, 'id,drug_type,approval_num,name,package_spec,manufactor_name,dispense_unit,basic_amount,basic_unit,dosage_unit,dosage_amount,py_code,wb_code,dosage_type,barcode,goods_name,standard_code,drug_code,retail_price', null, $limit)) {
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

}
