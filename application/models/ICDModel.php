<?php

namespace app\models;

use app\common\DrugType;
use app\common\DrugDosage;
use Crud;

class ICDModel extends Crud {

    protected $table = 'dayi_icd';

    /**
     * 查询疾病诊断
     * @param $name
     * @param $limit
     * @return array
     */
    public function searchICD ($name, $limit = 5)
    {
        $condition = [];
        if ($name) {
            if (preg_match('/^[0-9|a-z|A-Z]+$/', $name)) {
                $condition['icd_code'] = ['like', '%' . $name . '%'];
                $condition['py_code']  = ['like', $name . '%', 'or'];
            } else {
                $condition['name'] = ['like', '%' . $name . '%'];
            }
        } else {
            return [];
        }
        return $this->select($condition, 'id,icd_code,name', null, $limit);
    }

}
