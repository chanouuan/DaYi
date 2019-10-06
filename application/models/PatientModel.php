<?php

namespace app\models;

use app\common\Gender;
use Crud;

class PatientModel extends Crud {

    protected $table = 'dayi_patient';

    /**
     * 更新患者信息
     * @param $name 姓名
     * @param $telephone 手机
     * @param $age 年龄
     * @param $gender 性别
     * @return bool
     */
    public function insertUpdate ($name, $telephone = null, $age = null, $gender = null)
    {
        $data = [
            'name'      => $name,
            'telephone' => $telephone,
            'birthday'  => Gender::getBirthDay($age),
            'gender'    => $gender
        ];

        if ($patientInfo = $this->getDb()->table($this->table)->field('id')->where($data)->limit(1)->find()) {
            return $patientInfo['id'];
        }

        $data['create_time'] = date('Y-m-d H:i:s', TIMESTAMP);
        return $this->getDb()->insert($this->table, $data, null, null, true);
    }

}
