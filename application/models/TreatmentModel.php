<?php

namespace app\models;

use app\common\CommonStatus;
use Crud;

class TreatmentModel extends Crud {

    protected $table = 'dayi_treatment_sheet';

    /**
     * 搜索
     * @param $store_id 门店
     * @param $name 名称
     * @param $limit
     * @return array
     */
    public function search ($store_id, $name, $limit = 10)
    {
        $condition = [
            'store_id' => $store_id,
            'status'   => CommonStatus::OK
        ];
        $condition[''] = ['(name like "' . $name . '%" or ident like "' . $name . '%" or py_code like "' . $name . '%" or wb_code like "' . $name . '%")'];
        if (!$list = $this->select($condition, 'id,name,unit,price', 'id desc', $limit)) {
            return [];
        }
        foreach ($list as $k => $v) {
            $list[$k]['price'] = round_dollar($v['price']);
        }
        return $list;
    }

}
