<?php

namespace app\models;

use app\common\Gender;
use Crud;

class PatientModel extends Crud {

    protected $table = 'dayi_patient';

    /**
     * 搜索患者
     * @param $name 患者姓名
     * @param $limit
     * @return array
     */
    public function search ($name, $limit = 5) 
    {
        if (!$list = $this->select(['name' => ['like', $name . '%'], 'status' => 1], 'id,name,telephone,birthday,gender', 'id desc', $limit)) {
            return [];
        }
        foreach ($list as $k => $v) {
            // 年龄
            list($list[$k]['age_year'], $list[$k]['age_month']) = array_values(Gender::getAgeByBirthDay($v['birthday']));
            $list[$k]['age'] = Gender::showAge($list[$k]['age_year'] + $list[$k]['age_month'] / 100);
            // 性别
            $list[$k]['sex'] = Gender::getMessage($v['gender']);
            unset($list[$k]['birthday']);
        }
        return $list;
    }

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

        if ($patientInfo = $this->find($data, 'id')) {
            return $patientInfo['id'];
        }

        $data['create_time'] = date('Y-m-d H:i:s', TIMESTAMP);
        return $this->getDb()->insert($data, null, true);
    }

}
