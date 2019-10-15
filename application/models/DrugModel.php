<?php

namespace app\models;

use app\common\DrugStatus;
use Crud;

class DrugModel extends Crud {

    protected $table = 'dayi_drug';

    /**
     * 搜索
     * @param $store_id 门店
     * @param $drug_type 类型
     * @param $name 名称
     * @param $limit
     * @return array
     */
    public function search ($store_id, $drug_type, $name, $limit = 10)
    {
        $condition = [
            'store_id' => $store_id,
            'status'   => ['>', DrugStatus::NOSALES]
        ];
        if ($drug_type) {
            $condition['drug_type'] = is_array($drug_type) ? ['in', $drug_type] : $drug_type;
        }
        $condition[''] = ['(name like "' . $name . '%" or py_code like "' . $name . '%" or wb_code like "' . $name . '%")'];
        if (!$list = $this->select($condition, 'id,drug_type,name,package_spec,dispense_unit,retail_price,amount', 'id desc', $limit)) {
            return [];
        }
        foreach ($list as $k => $v) {
            $list[$k]['retail_price'] = round_dollar($v['retail_price']);
            $list[$k]['reserve'] = $v['amount'] . $v['dispense_unit'];
        }
        return $list;
    }

}
