<?php

namespace app\models;

use app\common\CommonStatus;
use app\common\Gender;
use Crud;

class AdminModel extends Crud {

    protected $table = 'admin_user';

    /**
     * 管理员登录
     * @param username 用户名
     * @param password 密码登录
     * @return array
     */
    public function login (array $post)
    {
        $post['username'] = trim_space($post['username']);
        if (!$post['username']) {
            return error('账号不能为空');
        }
        if (!$post['password']) {
            return error('密码不能为空');
        }

        // 检查错误登录次数
        if (!$this->checkLoginFail($post['username'])) {
            return error('密码错误次数过多，请稍后重新登录！');
        }

        // 登录不同方式
        $userInfo = $this->userLogin($post);
        if ($userInfo['errorcode'] !== 0) {
            return $userInfo;
        }
        $userInfo = $userInfo['result'];

        // 获取管理权限
        $permission = $this->getUserPermissions($userInfo['user_id']);

        // login 权限验证
        if ($post['role'] && empty(array_intersect($post['role'], $permission['role']))) {
            return error('职位权限不足');
        }
        if (empty(array_intersect($post['permission'] ? $post['permission'] : ['ANY', 'login'], $permission['permission']))) {
            return error('操作权限不足');
        }
        $userInfo['role'] = $permission['role'];
        $userInfo['permission'] = $permission['permission'];

        $opt = [];
        if (isset($post['clienttype'])) {
            $opt['clienttype'] = $post['clienttype'];
        }
        if (isset($post['clientapp'])) {
            $opt['clientapp'] = $post['clientapp'];
        }

        // 登录状态
        $result = (new UserModel())->setloginstatus($userInfo['user_id'], uniqid(), $opt, [
            implode('^', $userInfo['permission'])
        ]);
        if ($result['errorcode'] !== 0) {
            return $result;
        }
        $userInfo['token'] = $result['result']['token'];

        return success($userInfo);
    }

    /**
     * 管理员退出登录
     * @param $user_id
     * @return array
     */
    public function logout ($user_id, $clienttype = null)
    {
        (new UserModel())->logout($user_id, $clienttype);
        return success('ok');
    }

    /**
     * 管理员用户登录
     * @param $post
     * @return array
     */
    public function userLogin (array $post)
    {
        $condition = [
            'status' => 1,
            'clinic_id' => intval($post['clinic_id'])
        ];
        if (preg_match('/^\d+$/', $post['username'])) {
            if (!validate_telephone($post['username'])) {
                return error('手机号不正确');
            }
            $condition['telephone'] = $post['username'];
        } else {
            $condition['user_name'] = $post['username'];
        }

        $userModel = new UserModel();

        // 获取用户
        if (!$userInfo = $this->find($condition, 'id,avatar,user_name,full_name,telephone,password')) {
            return error('用户名或密码错误');
        }

        // 密码验证
        if ($post['password'] !== true) {
            if (!$userModel->passwordVerify($post['password'], $userInfo['password'])) {
                $count = $this->loginFail($post['username']);
                return error($count > 0 ? ('用户名或密码错误，您还可以登录 ' . $count . ' 次！') : '密码错误次数过多，15分钟后重新登录！');
            }
        }

        return success([
            'user_id'   => $userInfo['id'],
            'avatar'    => httpurl($userInfo['avatar']),
            'nickname'  => get_real_val($userInfo['full_name'], $userInfo['user_name'], $userInfo['telephone']),
            'telephone' => $userInfo['telephone']
        ]);
    }

    /**
     * 获取管理员信息
     * @param $adminid
     * @return array
     */
    public function getAdminInfo ($user_id)
    {
        if (!$user_id) {
            return [];
        }
        if (!$adminInfo = $this->find(['id' => $user_id], 'id,clinic_id,avatar,user_name,full_name,telephone,status')) {
            return [];
        }
        $adminInfo['avatar']   = httpurl($adminInfo['avatar']);
        $adminInfo['nickname'] = get_real_val($adminInfo['full_name'], $adminInfo['user_name'], $adminInfo['telephone']);
        return $adminInfo;
    }

    /**
     * 检查用户信息
     * @param $user_id
     * @return array
     */
    public function checkAdminInfo ($user_id)
    {
        if (!$userInfo = $this->getAdminInfo($user_id)) {
            return error('用户不存在');
        }
        if ($userInfo['status'] != CommonStatus::OK) {
            return error('你已被禁用');
        }
        if (!$userInfo['clinic_id']) {
            return error('你未绑定诊所');
        }
        return success($userInfo);
    }

    /**
     * 获取员工角色
     * @return array
     */
    public function getEmployeeRole ($user_id)
    {
        if (!$info = $this->getAdminInfo($user_id)) {
            return [];
        }
        if (!$info['clinic_id']) {
            return [];
        }
        return $this->getDb()->table('admin_roles')->field('id,name')->where(['status' => 1, 'clinic_id' => $info['clinic_id']])->select();
    }

    /**
     * 获取员工信息
     * @return array
     */
    public function getEmployeeInfo ($id)
    {
        $id = intval($id);
        if (!$info = $this->getDb()->field('id,avatar,user_name,full_name,telephone,gender,title,status')->where(['id' => $id])->limit(1)->find()) {
            return [];
        }
        // 获取角色
        $roles = $this->getDb()->table('admin_role_user')->field('role_id')->where(['user_id' => $id])->select();
        $roles = $roles ? array_column($roles, 'role_id') :[];
        $info['role_id']  = $roles;
        $info['avatar']   = httpurl($info['avatar']);
        $info['status']   = $info['status'] ? '1' : '';
        return $info;
    }

    /**
     * 获取员工列表
     * @return array
     */
    public function getEmployeeList ($user_id, array $post)
    {
        $post['page_size'] = max(6, $post['page_size']);
        $post['name']      = trim_space($post['name']);

        // 用户获取
        $userInfo = $this->checkAdminInfo($user_id);
        if ($userInfo['errorcode'] !== 0) {
            return $userInfo;
        }
        $userInfo = $userInfo['result'];

        $condition = [
            'clinic_id' => $userInfo['clinic_id']
        ];
        if (!is_null(CommonStatus::format($post['status']))) {
            $condition['status'] = $post['status'];
        }
        if ($post['title']) {
            $condition['title'] = $post['title'];
        }
        if ($post['name']) {
           if (preg_match('/^\d+$/', $post['name'])) {
                if (!validate_telephone($post['name'])) {
                    $condition['user_name'] = $post['name'];
                } else {
                    $condition['telephone'] = $post['name'];
                }
            } else {
                $condition['user_name'] = $post['name'];
            }
        }

        $count = $this->count($condition);
        if ($count > 0) {
            $pagesize = getPageParams($post['page'], $count, $post['page_size']);
            $list = $this->select($condition, 'id,user_name,telephone,full_name,gender,title,status', 'id desc', $pagesize['limitstr']);
            if ($list) {
                $roles = $this->getRoleByUser(array_column($list, 'id'));
                foreach ($list as $k => $v) {
                    // 角色
                    $list[$k]['roles'] = isset($roles[$v['id']]) ? implode(',', $roles[$v['id']]) : '无';
                }
                unset($roles);
            }
        }

        return success([
            'total_count' => $count,
            'page_size' => $post['page_size'],
            'list' => $list ? $list : []
        ]);
    }

    /**
     * 获取用户姓名
     * @param $id
     * @return array
     */
    public function getAdminNames (array $id)
    {
        $id = array_filter(array_unique($id));
        if (!$id) {
            return [];
        }
        if (!$admins = $this->select(['id' => ['in', $id]], 'id,user_name,full_name,telephone')) {
            return [];
        }
        foreach ($admins as $k => $v) {
            $admins[$k]['nickname'] = get_real_val($v['full_name'], $v['user_name'], $v['telephone']);
        }
        return array_column($admins, 'nickname', 'id');
    }

    /**
     * 根据用户获取角色
     * @param $user_id 用户ID
     * @return array
     */
    public function getRoleByUser (array $user_id)
    {
        if (empty($user_id)) {
            return [];
        }

        if (empty($roles = $this->getDb()
            ->table('admin_role_user role_user inner join admin_roles role on role.id = role_user.role_id')
            ->field('role_user.user_id,role_user.role_id,role.name')
            ->where(['role_user.user_id' => ['in', $user_id]])
            ->select())) {
            return [];
        }

        $list = [];
        foreach ($roles as $k => $v) {
            $list[$v['user_id']][$v['role_id']] = $v['name'];
        }

        unset($roles);
        return $list;
    }

    /**
     * 获取用户所有权限
     * @param $user_id 用户ID
     * @return array
     */
    public function getUserPermissions ($user_id)
    {
        // 获取用户角色
        $roles = $this->getDb()->table('admin_role_user')->field('role_id')->where(['user_id' => $user_id])->select();
        if (empty($roles)) {
            return [];
        }
        $roles = array_column($roles, 'role_id');

        // 获取权限
        if (empty($permissions = $this->getDb()
            ->table('admin_permission_role permission_role inner join admin_permissions permissions on permissions.id = permission_role.permission_id')
            ->field('permissions.name')
            ->where(['permission_role.role_id' => ['IN', $roles]])
            ->select())) {
            return [];
        }

        return [
            'role' => $roles,
            'permission' => array_column($permissions, 'name')
        ];
    }

    /**
     * 根据角色获取用户
     * @return array
     */
    public function getUserByRole ($clinic_id, $role_id)
    {
        $userList = $this->getDb()
            ->table('admin_role_user role inner join admin_user user on user.id = role.user_id')
            ->field('user.id,user.avatar,user.user_name,user.full_name,user.telephone')
            ->where(['role.role_id' => $role_id, 'user.clinic_id' => $clinic_id, 'user.status' => 1])
            ->select();
        if (empty($userList)) {
            return [];
        }
        foreach ($userList as $k => $v) {
            $userList[$k]['avatar'] = httpurl($v['avatar']);
            $userList[$k]['nickname'] = get_real_val($v['full_name'], $v['user_name'], $v['telephone']);
        }
        return $userList;
    }

    /**
     * 获取职位是医师的用户
     * @return array
     */
    public function getUserByDoctor ($clinic_id, $title = '医师')
    {
        if (!$clinic_id) {
            return [];
        }
        $condition = [
            'clinic_id' => $clinic_id, 
            'status'    => CommonStatus::OK
        ];
        if ($title) {
            $condition['title'] = is_array($title) ? ['in', $title] : $title;
        }
        $userList = $this->getDb()
            ->field('id,user_name,full_name,telephone')
            ->where($condition)
            ->select();
        if (empty($userList)) {
            return [];
        }
        foreach ($userList as $k => $v) {
            $userList[$k]['nickname'] = get_real_val($v['full_name'], $v['user_name'], $v['telephone']);
        }
        return $userList;
    }

    /**
     * 添加员工
     * @return array
     */
    public function saveEmployee ($user_id, array $post)
    {
        $userInfo = $this->checkAdminInfo($user_id);
        if ($userInfo['errorcode'] !== 0) {
            return $userInfo;
        }
        $userInfo = $userInfo['result'];

        $post['id']     = intval($post['id']);
        $post['status'] = intval($post['status']);
        $post['role_id']   = get_short_array($post['role_id']);

        $data = [];
        $data['clinic_id'] = $userInfo['clinic_id'];
        $data['user_name'] = trim_space($post['user_name']);
        $data['password']  = trim_space($post['password']);
        $data['gender']    = Gender::format($post['gender']);
        $data['telephone'] = $post['telephone'] ? trim_space($post['telephone']) : null;
        $data['full_name'] = $post['full_name'] ? trim_space($post['full_name']) : null;
        $data['title']     = $post['title'] ? trim_space($post['title']) : null;

        if (!$data['clinic_id']) {
            return error('诊所不能为空');
        }
        if (!$data['user_name']) {
            return error('登录账号不能为空');
        } else {
            if (preg_match('/^\d+$/', $post['user_name'])) {
                return error('登录账号不能全数字');
            }
        }
        if (!$post['id'] && !$data['password']) {
            return error('登录密码不能为空');
        }
        if (!validate_telephone($data['telephone'])) {
            return error('手机号格式不正确');
        }
        if (!$post['role_id']) {
            return error('角色不能为空');
        }

        // 密码 hash
        if ($data['password']) {
            if (strlen($data['password']) < 6) {
                return error('密码长度至少 6 位');
            }
            $data['password'] = (new UserModel())->hashPassword(md5($data['password']));
        } else {
            unset($data['password']);
        }

        // 重复效验
        $condition = [
            'clinic_id' => $data['clinic_id'],
            'user_name' => $data['user_name']
        ];
        if ($post['id']) {
             $condition['id'] = ['<>', $post['id']];
        }
        if ($this->count($condition)) {
            return error('该登录账号已存在');
        }
        if ($data['telephone']) {
            $condition = [
                'clinic_id' => $data['clinic_id'],
                'telephone' => $data['telephone']
            ];
            if ($post['id']) {
                 $condition['id'] = ['<>', $post['id']];
            }
            if ($this->count($condition)) {
                return error('该手机号已存在');
            }
        }

        // 角色效验
        $roles = $this->getDb()->table('admin_roles')->where(['clinic_id' => $data['clinic_id'], 'status' => 1, 'id' => ['in', $post['role_id']]])->count();
        if (count($post['role_id']) !== $roles) {
            return error('角色效验失败');
        }

        // 新增 or 编辑
        if ($post['id']) {
            if (!is_null(CommonStatus::format($post['status']))) {
                $data['status'] = $post['status'];
            }
            $data['update_time'] = date('Y-m-d H:i:s', TIMESTAMP);
            if (!$this->getDb()->where(['id' => $post['id'], 'clinic_id' => $data['clinic_id']])->update($data)) {
                return error('该用户已存在！');
            }
        } else {
            $data['create_time'] = date('Y-m-d H:i:s', TIMESTAMP);
            if (!$post['id'] = $this->getDb()->insert($data, false, true)) {
                return error('请勿添加重复的用户！');
            }
        }

        // 添加权限
        $roles = $this->getDb()->table('admin_role_user')->field('role_id')->where(['user_id' => $post['id']])->select();
        $roles = $roles ? array_column($roles, 'role_id') :[];
        $curd  = array_curd($roles, $post['role_id']);
        if ($curd['add']) {
            $this->getDb()->table('admin_role_user')->insert([
                'user_id' => array_fill(0, count($curd['add']), $post['id']),
                'role_id' => $curd['add']
            ]);
        }
        if ($curd['delete']) {
            $this->getDb()->table('admin_role_user')->where([
                'user_id' => $post['id'],
                'role_id' => ['in', $curd['delete']]
            ])->delete();
        }
        
        return success('ok');
    }

    /**
     * 记录登录错误次数
     * @param $account
     * @return int
     */
    public function loginFail ($account)
    {
        $faileInfo = $this->getDb()
            ->table('admin_failedlogin')
            ->field('id,login_count,update_time')
            ->where(['account' => $account])
            ->limit(1)
            ->find();
        $count = 1;
        if ($faileInfo) {
            $count = ($faileInfo['update_time'] + 900 > TIMESTAMP) ? $faileInfo['login_count'] + 1 : 1;
            $this->getDb()
                ->table('admin_failedlogin')
                ->where(['id' => $faileInfo['id'], 'update_time' => $faileInfo['update_time']])
                ->update([
                    'login_count' => $count,
                    'update_time' => TIMESTAMP
                ]);
        } else {
            $this->getDb()
                ->table('admin_failedlogin')
                ->insert([
                    'login_count' => 1,
                    'update_time' => TIMESTAMP,
                    'account'     => $account
                ]);
        }
        $count = 10 - $count;
        return $count < 0 ? 0 : $count;
    }

    /**
     * 检查错误登录次数
     * @param $account
     * @return bool
     */
    public function checkLoginFail ($account)
    {
        return ($account && $this->getDb()
            ->table('admin_failedlogin')
            ->field('id')
            ->where(['account' => $account, 'login_count' => ['>', 9], 'update_time' => ['>', TIMESTAMP - 900]])
            ->limit(1)
            ->count() ? false : true);
    }

}
