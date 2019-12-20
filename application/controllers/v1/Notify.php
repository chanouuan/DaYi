<?php

namespace app\controllers;

use ActionPDO;
use app\library\DB;
use app\models\ClinicModel;

class Notify extends ActionPDO {

    public function payed ()
    {
        $trade_id = intval($_GET['trade_id']);
        $result = (new ClinicModel())->handleTradeSuc($trade_id);
        if ($result['errorcode'] !== 0) {
            $this->error($result['message']);
        }
        $this->success($result['message'], gurl('notify/demo'));
    }

    public function getSign ()
    {
        $list = DB::getInstance()->table('dayi_invite_code')->select();
        return compact('list');
    }

    public function createSign ()
    {
        $data = [];
        for ($i=0; $i < 100; $i++) {
            $code = substr(str_shuffle('023456789abcdefghijkmnopqrstuvwxyz'), 8, 4);
            if (!isset($data[$code])) {
                $data[$code] = ['auth_code' => $code];
            }
        }
        $data = array_values($data);
        DB::getInstance()->table('dayi_invite_code')->insert($data);
        echo 'ok';
    }

    public function demo ()
    {
        $condition = [];
        if ($_GET['pay']) {
            $condition[] = 'pay = ' . intval($_GET['pay'] * 100);
        }
        $count = DB::getInstance()->table('__tablepre__trades')->where($condition)->count();
        $pagesize = getPageParams($_GET['page'], $count, 15);
        $list = DB::getInstance()->table('__tablepre__trades')->field('*')->where($condition)->order('id desc')->limit($pagesize['limitstr'])->select();
        if ($list) {
            $clinics = [];
            foreach ($list as $k => $v) {
                $list[$k]['mark'] = json_decode($v['mark'], true);
                $clinics[] = $list[$k]['mark']['clinic_id'];
            }
            $clinics = DB::getInstance()->table('dayi_clinic')->field('id,name')->where(['id' => ['in', $clinics]])->select();
            $clinics = array_column($clinics, 'name', 'id');
            foreach ($list as $k => $v) {
                $list[$k]['clinic_name'] = $clinics[$v['mark']['clinic_id']];
            }
        }

        return [
            'pagesize' => $pagesize,
            'list' => $list
        ];
    }

}
