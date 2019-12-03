<?php

namespace app\models;

use Crud;
use app\common\VipLevel;
use app\common\GenerateCache;

class ClinicModel extends Crud {

    protected $table = 'dayi_clinic';

    /**
     * 生成收款码
     * @return array
     */
    public function createPayed (array $post)
    {
        $post['clinic_id'] = intval($post['clinic_id']);
        $post['payway']    = trim_space($post['payway']);
        $post['sale_id']   = intval($post['sale_id']);

        if (!$post['sale_id']) {
            return error('请选择购买时长');
        }
        if (!$post['payway']) {
            return error('请选择支付方式');
        } 

        if (!$clinicInfo = GenerateCache::getClinic($post['clinic_id'])) {
            return error('当前诊所未找到');
        }

        // 获取剩余天数
        $clinicInfo['use_days'] = VipLevel::getUseDays($clinicInfo['expire_date']);

        // 获取购买时长和售价
        if (!$saleInfo = $this->getDb()
            ->field('month,vip_level,price')
            ->table('dayi_vip_sale')
            ->where(['id' => $post['sale_id']])
            ->find()) {
            return error('未找到售价配置');
        }

        // 判断升配或降配 0不变 1升配 2降配
        if ($clinicInfo['vip_level']) {
            if ($clinicInfo['vip_level'] == $saleInfo['vip_level']) {
                $changeLevel = 0;
            } else {
                $changeLevel = $saleInfo['vip_level'] > $clinicInfo['vip_level'] ? 1 : 2;
            }
        } else {
            $changeLevel = 0;
        }

        // 授权有效期内不允许降配
        if ($changeLevel === 2 && $clinicInfo['use_days'] > 0) {
            return error('产品在有效授权期内，不允许降配！');
        }

        // 升配补差价
        $diff = 0;
        if ($changeLevel === 1 && $clinicInfo['use_days'] > 0) {
            $diff = $clinicInfo['use_days'] * $clinicInfo['daily_cost'];
        }
        $price = $saleInfo['price'] > $diff ? $saleInfo['price'] - $diff : 0; // 应付金额

        $mark = [
            'clinic_id'   => $clinicInfo['id'],
            'vip_level'   => $saleInfo['vip_level'],
            'expire_date' => '',
            'daily_cost'  => 0
        ];

        // 计算有效期截止日期
        if ($changeLevel === 0) {
            $beginTime = $clinicInfo['vip_level'] ? strtotime($clinicInfo['expire_date']) : strtotime(date('Y-m-d', TIMESTAMP));
        } else {
            $beginTime = strtotime(date('Y-m-d', TIMESTAMP));
        }
        $mark['expire_date'] = date('Y-m-d', mktime(0, 0, 0, date('m', $beginTime) + $saleInfo['month'], date('d', $beginTime), date('Y', $beginTime)));
        
        // 计算每日费用
        $mark['daily_cost'] = bcdiv($saleInfo['price'], ceil(bcdiv(strtotime($mark['expire_date']) - $beginTime, 86400, 6)));

        // 生成交易单
        if (!$tradeId = $this->getDb()->table('__tablepre__trades')->insert([
            'source'      => 'vip',
            'uses'        => '签约缴费',
            'pay'         => $price,
            'money'       => $price,
            'payway'      => $post['payway'],
            'mark'        => json_encode($mark),
            'order_code'  => $this->generateOrderCode($clinicInfo['id']),
            'create_time' => date('Y-m-d H:i:s', TIMESTAMP)
        ], false ,true)) {
            return error('交易单保存失败');
        }

        if ($price === 0) {
            // 免支付
            $res = $this->handleTradeSuc($tradeId);
            if ($res['errorcode'] !== 0) {
                return $res;
            }
        }

        return success([
            'trade_id'    => $tradeId,
            'pay'         => round_dollar($price),
            'pay_status'  => $price === 0 ? 1 : 0,
            'vip_msg'     => VipLevel::getMessage($mark['vip_level']),
            'expire_date' => $mark['expire_date']
        ]);
    }

    /**
     * 交易成功的后续处理
     * @return array
     */
    public function handleTradeSuc ($tradeId, array $tradeParam = [])
    {
        if (!$tradeInfo = $this->getDb()
            ->table('__tablepre__trades')
            ->where(['id' => $tradeId])
            ->limit(1)
            ->find()) {
            return error('交易单不存在');
        }

        $tradeParam = array_merge($tradeParam, [
            'status' => 1, 'pay_time' => date('Y-m-d H:i:s', TIMESTAMP)
        ]);

        // 更新交易状态
        if (!$this->getDb()->transaction(function ($db) use ($tradeInfo, $tradeParam) {
            if (!$db->table('__tablepre__trades')->where([
                'id' => $tradeInfo['id'],
                'status' => 0
            ])->update($tradeParam)) {
                return false;
            }
            $mark = json_decode($tradeInfo['mark'], true);
            if (!$db->table($this->table)->where(['id' => $mark['clinic_id']])->update([
                'vip_level'   => $mark['vip_level'],
                'expire_date' => $mark['expire_date'],
                'daily_cost'  => $mark['daily_cost'],
                'update_time' => date('Y-m-d H:i:s', TIMESTAMP)
            ])) {
                return false;
            }
            GenerateCache::removeClinic($mark['clinic_id']);
            return true;
        })) {
            return error('更新交易失败');
        }

        return success('ok');
    }

    /**
     * 获取 vip 售价
     * @return array
     */
    public function getVipSale (array $post)
    {
        $post['clinic_id'] = intval($post['clinic_id']);
        $post['level']     = VipLevel::format($post['level']);

        if (!$post['level']) {
            return error('请选择套餐');
        }

        if (!$clinicInfo = GenerateCache::getClinic($post['clinic_id'])) {
            return error('当前诊所未找到');
        }

        // 获取剩余天数
        $clinicInfo['next_msg']   = VipLevel::getMessage($post['level']);
        $clinicInfo['vip_msg']    = VipLevel::getMessage($clinicInfo['vip_level']);
        $clinicInfo['use_date']   = VipLevel::getUseDate($clinicInfo['expire_date']);
        $clinicInfo['use_days']   = VipLevel::getUseDays($clinicInfo['expire_date']);
        $clinicInfo['daily_cost'] = round_dollar($clinicInfo['daily_cost']);

        // 判断升配或降配 0不变 1升配 2降配
        if ($clinicInfo['vip_level']) {
            if ($clinicInfo['vip_level'] == $post['level']) {
                $clinicInfo['change_level'] = 0;
            } else {
                $clinicInfo['change_level'] = $post['level'] > $clinicInfo['vip_level'] ? 1 : 2;
            }
        } else {
            $clinicInfo['change_level'] = 0;
        }

        // 获取购买时长和售价
        if (!$clinicInfo['sales'] = $this->getDb()
            ->field('id,month,price,old_price,remark')
            ->table('dayi_vip_sale')
            ->where(['vip_level' => $post['level']])
            ->order('month')
            ->select()) {
            return error('该套餐未设置售价');
        }

        $show = [
            6  => '半年',
            12 => '1 年',
            24 => '2 年',
            36 => '3 年',
        ];
        foreach ($clinicInfo['sales'] as $k => $v) {
            $clinicInfo['sales'][$k]['price']     = round_dollar($v['price']);
            $clinicInfo['sales'][$k]['old_price'] = round_dollar($v['old_price']);
            $clinicInfo['sales'][$k]['show']      = isset($show[$v['month']]) ? $show[$v['month']] : ($v['month'] . ' 个月');
        }

        return success($clinicInfo);
    }

    /**
     * 检查 vip
     * @return array
     */
    public function checkVipState ($clinic_id)
    {
        $clinic_id = intval($clinic_id);

        if (!$clinicInfo = GenerateCache::getClinic($clinic_id)) {
            return error('当前诊所未找到');
        }

        $clinicInfo['vip_msg']  = VipLevel::getMessage($clinicInfo['vip_level']);
        $clinicInfo['use_date'] = VipLevel::getUseDate($clinicInfo['expire_date']);
        $clinicInfo['use_days'] = VipLevel::getUseDays($clinicInfo['expire_date']);

        return success($clinicInfo);
    }

    /**
     * 保存诊所配置
     * @return array
     */
    public function saveClinicConfig ($user_id, array $post)
    {
        $data = [];
        $data['name']    = mb_substr(trim_space($post['name']), 0, 20);
        $data['tel']     = trim_space($post['tel']);
        $data['address'] = mb_substr(trim_space($post['address']), 0, 80);

        if ($data['tel'] && !validate_telephone($data['tel'])) {
            return error('手机号格式不正确');
        }

        $userInfo = (new AdminModel())->checkAdminInfo($user_id);

        if (!$clinicInfo = GenerateCache::getClinic($userInfo['clinic_id'])) {
            return error('当前诊所未找到');
        }

        // vip等级0、1不提供库存功能，所以不能修改库存相关配置
        if ($clinicInfo['vip_level'] > VipLevel::SIMPLE) {
            $data['is_ds'] = $post['is_ds'] ? 1 : 0;
            $data['is_cp'] = $post['is_cp'] ? 1 : 0;
            $data['is_rp'] = $post['is_rp'] ? 1 : 0;
        }

        if (false === ($result = $this->getDb()->where(['id' => $userInfo['clinic_id']])->update($data))) {
            return error('保存配置失败');
        }

        if ($result) {
            GenerateCache::removeClinic($userInfo['clinic_id']);
        }

        return success('ok');
    }

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
                    'db_chunk'    => $cluster['chunk'],
                    'expire_date' => date('Y-m-d', TIMESTAMP + (86400 * 30)) // 试用期 30 天
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

    /**
     * 生成单号(16位)
     * @return string
     */
    private function generateOrderCode ($uid)
    {
        $code[] = date('Ymd', TIMESTAMP);
        $code[] = (rand() % 10) . (rand() % 10) . (rand() % 10) . (rand() % 10);
        $code[] = str_pad(substr($uid, -4),4,'0',STR_PAD_LEFT);
        return implode('', $code);
    }

}
