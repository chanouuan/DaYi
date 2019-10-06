<?php

namespace app\models;

use Crud;

class UserModel extends Crud {

    protected $table = 'pro_user';

    /**
     * 登录状态设置
     * @return array
     */
    public function setloginstatus ($user_id, $scode, array $opt = [], array $extra = [], $expire = 0)
    {
        if (!$user_id) {
            return error(null);
        }
        $update = [
            'user_id'     => $user_id,
            'scode'       => $scode,
            'clienttype'  => CLIENT_TYPE,
            'clientinfo'  => null,
            'loginip'     => get_ip(),
            'online'      => 1,
            'update_time' => date('Y-m-d H:i:s', TIMESTAMP)
        ];
        !empty($opt) && $update = array_merge($update, $opt);
        if (!$this->getDb()->norepeat('__tablepre__session', $update)) {
            return error(null);
        }
        $token = rawurlencode(authcode(implode("\t", array_merge([$user_id, $scode, $update['clienttype'], $_SERVER['REMOTE_ADDR']], $extra)), 'ENCODE'));
        set_cookie('token', $token, $expire);
        return success([
            'token' => $token
        ]);
    }

    /**
     * 登出
     * @return bool
     */
    public function logout ($user_id, $clienttype = null)
    {
        if (!$this->getDb()->update('__tablepre__session', [
            'scode'       => 0,
            'online'      => 0,
            'update_time' => date('Y-m-d H:i:s', TIMESTAMP)
        ], [
            'user_id'    => $user_id,
            'clienttype' => get_real_val($clienttype, CLIENT_TYPE)
        ])) {
            return false;
        }
        set_cookie('token', null);
        return true;
    }

    /**
     * hash密码
     * @param $pwd
     * @return string
     */
    public function hashPassword ($pwd)
    {
        return password_hash($pwd, PASSWORD_BCRYPT);
    }

    /**
     * 密码hash验证
     * @param $pwd 密码明文
     * @param $hash hash密码
     * @return bool
     */
    public function passwordVerify ($pwd, $hash)
    {
        return password_verify($pwd, $hash);
    }

}
