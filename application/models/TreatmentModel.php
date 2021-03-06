<?php

namespace app\models;

use app\common\CommonStatus;
use app\common\Royalty;
use Crud;

class TreatmentModel extends Crud {

    protected $table = 'dayi_treatment_sheet';

    /**
     * 添加诊疗项目
     * @return array
     */
    public function saveTreatment ($user_id, $post)
    {
        $userInfo = (new AdminModel())->checkAdminInfo($user_id);

        $post['id']     = intval($post['id']);
        $post['status'] = intval($post['status']);

        $data = [];
        $data['clinic_id']     = $userInfo['clinic_id'];
        $data['name']          = trim_space($post['name'], 0, 30);
        $data['ident']         = trim_space($post['ident'], 0, 30);
        $data['price']         = max(0, intval(floatval($post['price']) * 100)); // 分
        $data['unit']          = trim_space($post['unit'], 0, 5);
        $data['py_code']       = trim_space($post['py_code'], 0, 20);
        $data['wb_code']       = trim_space($post['wb_code'], 0, 20);
        $data['royalty']       = Royalty::format($post['royalty']);
        $data['royalty_ratio'] = Royalty::checkRoyaltyRatio($data['royalty'], $post['royalty_ratio'], $data['price']);
        $data['is_special']    = $post['is_special'] ? 1 : 0;

        if (!$data['clinic_id']) {
            return error('门诊不能为空');
        }
        if (!$data['name']) {
            return error('项目名称不能为空');
        }
        if (!$data['price']) {
            return error('项目单价不能为空');
        }
        if (!$data['unit']) {
            return error('项目不能为空');
        }

        // 去掉 null
        foreach ($data as $k => $v) {
            if (is_null($v)) {
                unset($data[$k]);
            }
        }
        
        // 新增 or 编辑
        if ($post['id']) {
            if (!is_null(CommonStatus::format($post['status']))) {
                $data['status'] = $post['status'];
            }
            $data['update_time'] = date('Y-m-d H:i:s', TIMESTAMP);
            if (!$this->getDb()->where(['id' => $post['id'], 'clinic_id' => $data['clinic_id']])->update($data)) {
                return error('该项目已存在！');
            }
        } else {
            $data['create_time'] = date('Y-m-d H:i:s', TIMESTAMP);
            if (!$this->getDb()->insert($data)) {
                return error('请勿添加重复的项目！');
            }
        }
        
        return success('ok');
    }

    /**
     * 项目是否存在
     * @return bool
     */
    public function treatmentExists (array $condition)
    {
        $info = $this->find($condition, 'id');
        return $info ? $info['id'] : false;
    }

    /**
     * 搜索
     * @param $clinic_id 门店
     * @param $name 名称
     * @param $limit
     * @return array
     */
    public function search ($clinic_id, $name, $limit = 5)
    {
        $condition = [
            'clinic_id' => $clinic_id,
            'status'   => CommonStatus::OK
        ];
        if ($name) {
            $condition['name']    = ['like', '%' . $name . '%', 'and ('];
            $condition['ident']   = ['=', $name, 'or'];
            $condition['py_code'] = ['like', $name . '%', 'or'];
            $condition['wb_code'] = ['like', $name . '%', 'or', ')'];
        }
        if (!$list = $this->select($condition, 'id,ident,name,unit,price', null, $limit)) {
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
    public function getTreatmentInfo ($id)
    {
        $id = intval($id);
        if (!$info = $this->find(['id' => $id], 'id,ident,name,price,unit,royalty,royalty_ratio,wb_code,py_code,is_special,status')) {
            return [];
        }
        $info['price'] = round_dollar($info['price']);
        $info['royalty_ratio'] = Royalty::showRoyaltyRatio($info['royalty'], $info['royalty_ratio']);
        return $info;
    }

    /**
     * 获取列表
     * @return array
     */
    public function getTreatmentList ($user_id, array $post)
    {
        $post['page_size'] = max(6, $post['page_size']);
        $post['name']      = trim_space($post['name']);

        // 用户获取
        $userInfo = (new AdminModel())->checkAdminInfo($user_id);

        $condition = [
            'clinic_id' => $userInfo['clinic_id']
        ];
        if (!is_null(CommonStatus::format($post['status']))) {
            $condition['status'] = $post['status'];
        }
        if ($post['name']) {
            $condition['name']    = ['like', $post['name'] . '%', 'and ('];
            $condition['ident']   = ['=', $post['name'], 'or'];
            $condition['py_code'] = ['like', $post['name'] . '%', 'or'];
            $condition['wb_code'] = ['like', $post['name'] . '%', 'or', ')'];
        }

        $count = $this->count($condition);
        if ($count > 0) {
            $pagesize = getPageParams($post['page'], $count, $post['page_size']);
            $list = $this->select($condition, 'id,ident,name,price,unit,royalty,royalty_ratio,status', 'id desc', $pagesize['limitstr']);
            if ($list) {
                foreach ($list as $k => $v) {
                    $list[$k]['price']   = round_dollar($v['price']);
                    $list[$k]['royalty'] = Royalty::showRoyaltyRatio($v['royalty'], $v['royalty_ratio'], true);
                    unset($list[$k]['royalty_ratio']);
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
