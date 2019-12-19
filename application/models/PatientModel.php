<?php

namespace app\models;

use app\common\Gender;
use app\library\PinYin;
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
        if (!$name) {
            return [];
        }
        if (!$list = $this->select([
            'status' => 1,
            'name' => ['like', $name . '%', 'and ('],
            'py' => ['like', $name . '%', 'or', ')'],   
            ], 'id,name,telephone,birthday,gender', 'id desc', $limit)) {
            return [];
        }
        foreach ($list as $k => $v) {
            $list[$k]['birthday'] = Gender::getAgeByBirthDay($v['birthday']);
            $list[$k]['age']      = Gender::showAge($list[$k]['birthday']);
            $list[$k]['sex']      = Gender::getMessage($v['gender']);
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
        if (!$name) {
            return false;
        }

        $data = [
            'name'      => $name,
            'telephone' => $telephone,
            'birthday'  => Gender::getBirthDay($age),
            'gender'    => $gender
        ];

        if ($patientInfo = $this->find($data, 'id')) {
            return $patientInfo['id'];
        }

        $data['py'] = (new PinYin())->get_first_py($data['name']);
        $data['create_time'] = date('Y-m-d H:i:s', TIMESTAMP);
        return $this->getDb()->insert($data, null, true);
    }

}
