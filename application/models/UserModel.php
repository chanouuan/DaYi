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
            'loginip'     => get_ip(),
            'online'      => 1,
            'update_time' => date('Y-m-d H:i:s', TIMESTAMP)
        ];
        !empty($opt) && $update = array_merge($update, $opt);
        if (!$this->getDb()->table('__tablepre__session')->norepeat($update)) {
            return error(null);
        }
        $token = rawurlencode(authcode(implode("\t", array_merge([$user_id, $scode, $update['clienttype'], $_SERVER['REMOTE_ADDR'] !== '::1' ? $_SERVER['REMOTE_ADDR'] : ''], $extra)), 'ENCODE'));
        // set_cookie('token', $token, $expire);
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
        if (!$this->getDb()->table('__tablepre__session')->where([
            'user_id'    => $user_id,
            'clienttype' => get_real_val($clienttype, CLIENT_TYPE)
        ])->update([
            'scode'       => 0,
            'online'      => 0,
            'update_time' => date('Y-m-d H:i:s', TIMESTAMP)
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

    /**
     * 验证图片验证码
     * @return bool
     */
    public function checkImgCode ($code, $keep = true)
    {
        if (!preg_match("/^[0-9]{4,6}$/", $code)) {
            return false;
        }
        $name = sprintf("%u", ip2long(get_ip()));
        if ($keep) {
            return $this->getDb()->table('__tablepre__smscode')->where(['tel' => $name, 'code' => $code])->count();
        }
        return $this->getDb()->table('__tablepre__smscode')->where(['tel' => $name, 'code' => $code])->delete();
    }

    /**
     * 验证短信验证码
     * @return bool
     */
    public function checkSmsCode ($telephone, $code)
    {
        if (!preg_match("/^1[0-9]{10}$/", $telephone) || !preg_match("/^[0-9]{4,6}$/", $code)) {
            return false;
        }
        // 处理逻辑为同一个验证码5分钟内可以验证通过10次
        if (!$result = $this->getDb()
            ->field('id, code, errorcount, sendtime')
            ->table('__tablepre__smscode')
            ->where('tel = ?')
            ->bindValue($telephone)
            ->find()) {
            return false;
        }
        if ($result['errorcount'] <= 10) {
            // 累计次数
            if (!$this->getDb()
                ->table('__tablepre__smscode')
                ->where(['id' => $result['id'], 'errorcount' => $result['errorcount']])->update(['errorcount' => ['errorcount+1']])) {
                return false;
            }
        }
        return $result['code'] == $code
            && $result['errorcount'] <= 10
            && $result['sendtime'] > (TIMESTAMP - 300);
    }

    /**
     * 重置短信验证码
     * @return bool
     */
    public function resetSmsCode ($telephone)
    {
        return $this->getDb()->table('__tablepre__smscode')->where(['tel' => $telephone])->delete();
    }

    /**
     * 保存图片验证码
     * @return array
     */
    public function saveImgCode ($code)
    {
        $name = sprintf("%u", ip2long(get_ip()));
        $result = $this->getDb()->table('__tablepre__smscode')->field('id')->where(['tel' => $name])->find();
        if (!$result) {
            if (!$this->getDb()->table('__tablepre__smscode')->insert([
                'tel' => $name, 'code' => $code
            ])) {
                return error('error');
            }
        } else {
            if (false === $this->getDb()->table('__tablepre__smscode')->where(['tel' => $name])->update([
                'code' => $code
            ])) {
                return error('error');
            }
        }
        return success('ok');
    }

    /**
     * 发送短信验证码
     * @return array
     */
    public function sendSmsCode ($post)
    {
        if (!validate_telephone($post['telephone'])) {
            return error('手机号为空或格式错误');
        }

        // 验证码长度
        $len = isset($post['len']) && $post['len'] ? $post['len'] : 6;
        $len = $len >= 4 && $len <= 6 ? $len : 6;
        $arr = range(1, $len);
        foreach ($arr as $k => $v) {
            $arr[$k] = (rand() % 10);
        }
        $code = implode('', $arr);

        $resultSms = $this->getDb()
            ->table('__tablepre__smscode')
            ->field('id,sendtime,hour_fc,day_fc')
            ->where('tel = ?')
            ->bindValue($post['telephone'])
            ->find();

        if (!$resultSms) {
            $resultSms = [
                'tel' => $post['telephone']
            ];
            if (!$resultSms['id'] = $this->getDb()->table('__tablepre__smscode')->insert(['tel' => $post['telephone']], null, true)) {
                return error('发送失败');
            }
        }

        $params = [
            'code' => $code,
            'errorcount' => 0,
            'sendtime' => TIMESTAMP,
            'hour_fc' => 1,
            'day_fc' => 1
        ];

        if ($resultSms['sendtime']) {
            // 限制发送频率
            if ($resultSms['sendtime'] + 10 > TIMESTAMP) {
                return error('验证码已发送,请稍后再试');
            }
            if (date('YmdH', $resultSms['sendtime']) == date('YmdH', TIMESTAMP)) {
                // 触发时级流控
                if ($resultSms['hour_fc'] >= getSysConfig('hour_fc')) {
                    return error('本时段发送次数已达上限');
                }
                $params['hour_fc'] = ['hour_fc+1'];
            }
            if (date('Ymd', $resultSms['sendtime']) == date('Ymd', TIMESTAMP)) {
                // 触发天级流控
                if ($resultSms['day_fc'] >= getSysConfig('day_fc')) {
                    return error('今日发送次数已达上限');
                }
                $params['day_fc'] = ['day_fc+1'];
            }
        }

        if (!$this->getDb()->table('__tablepre__smscode')->where(['id = ' . $resultSms['id'], 'hour_fc <= ' . getSysConfig('hour_fc'), 'day_fc <= ' . getSysConfig('day_fc')])->update($params)) {
            return error('发送失败');
        }

        // 发送短信
        return (new \app\library\AliSmsHelper())->sendSms('扶桑云医', 'SMS_133971610', $post['telephone'], ['code' => $code]);
    }

}
