<?php

namespace app\models;

use Crud;

class ClinicModel extends Crud {

    protected $table = 'dayi_clinic';

    /**
     * 注册诊所
     * @return array
     */
    public function regClinic (array $post)
    {
        $post['name']        = mb_substr(trim_space($post['name']), 0, 30);
        $post['address']     = mb_substr(trim_space($post['address']), 0, 200);
        $post['telephone']   = trim_space($post['telephone']);
        $post['invite_code'] = strtolower(trim_space($post['invite_code'])); // 邀请码
        $post['user_name']   = mb_substr(trim_space($post['user_name']), 0, 20); // 登录账号
        $post['password']    = trim_space($post['password']); // 登录密码

        if (!$post['name']) {
            return error('诊所名称不能为空');
        }
        if (!$post['user_name']) {
            return error('登录账号不能为空');
        } else {
            if (preg_match('/^\d+$/', $post['user_name'])) {
                return error('登录账号不能全数字');
            }
        }
        if (!$post['password']) {
            return error('登录密码不能为空');
        } else {
            if (strlen($post['password']) < 6) {
                return error('登录密码长度至少 6 位');
            }
        }
        if (!validate_telephone($post['telephone'])) {
            return error('手机号格式不正确');
        }
        if (strlen($post['invite_code']) !== 4) {
            return error('请填写邀请码');
        }

        $userModel = new UserModel();

        // 短信验证
        if (!$userModel->checkSmsCode($post['telephone'], $post['msgcode'])) {
            return error('验证码错误或已过期！');
        }

        // 邀请码验证
        if (!$this->getDb()->table('dayi_invite_code')->where(['auth_code' => $post['invite_code']])->count()) {
            return error('邀请码不存在，请重新输入');
        }

        // 邀请码只能用一次
        if (!$this->getDb()->table('dayi_invite_code')->where(['auth_code' => $post['invite_code']])->delete()) {
            return error('该邀请码已使用过');
        }

        if (!$cluster = $this->getDbCluster(MICROTIME)) {
            return error('获取分区失败');
        }

        if (!$clinicId = $this->getDb()->transaction(function ($db) use ($post, $cluster) {
            // 新增诊所
            if (!$clinicId = $db->insert([
                    'name'        => $post['name'],
                    'address'     => $post['address'],
                    'tel'         => $post['telephone'],
                    'db_instance' => $cluster['instance'],
                    'db_chunk'    => $cluster['chunk']
                ], false, true)) {
                return false;
            }
            // 新增用户
            $post['clinic_id'] = $clinicId;
            if (!(new AdminModel())->initAdmin($post)) {
                return false;
            }
            return $clinicId;
        })) {
            // 回滚邀请码
            $this->getDb()->table('dayi_invite_code')->insert(['auth_code' => $post['invite_code']]);
            return error('注册失败');
        }

        // 重置短信验证码
        $userModel->resetSmsCode($post['telephone']);
        
        return success([
            'clinic_id' => $clinicId
        ]);
    }

    /**
     * 获取集群参数
     * @return array
     */
    private function getDbCluster ($key)
    {
        if (!$cluster = getSysConfig('db', 'cluster')) {
            return null;
        }

        $key = crc32($key);

        $index = $key % count($cluster);

        if (!isset($cluster[$index])) {
            return null;
        }
        $cluster = $cluster[$index];

        $index = $key % count($cluster['chunk']);

        if (empty($cluster['chunk'][$index])) {
            return null;
        }

        return [
            'instance' => $cluster['instance'],
            'chunk' => $cluster['chunk'][$index]
        ];
    }

}
