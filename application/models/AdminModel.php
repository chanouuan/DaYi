<?php

namespace app\models;

use Crud;

class AdminModel extends Crud {

    protected $table = 'admin_user';

    /**
     * 管理员登录
     * @param username 用户名
     * @param password 密码登录
     * @return array
     */
    public function login ($post)
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
            json_encode($userInfo['role']), json_encode($userInfo['permission'])
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
    public function userLogin ($post)
    {
        $condition = [
            'status' => 1
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
        if (!$userInfo = $this->getDb()->table($this->table)->field('id,avatar,user_name,full_name,telephone,password')->where($condition)->limit(1)->find()) {
            return error('用户名或密码错误');
        }

        // 密码验证
        if (!$userModel->passwordVerify($post['password'], $userInfo['password'])) {
            $count = $this->loginFail($post['username']);
            return error($count > 0 ? ('用户名或密码错误，您还可以登录 ' . $count . ' 次！') : '密码错误次数过多，15分钟后重新登录！');
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
    public function getAdminInfo ($adminid)
    {
        if (!$adminInfo = $this->getDb()->table($this->table)->field('id,store_id,avatar,user_name,full_name,telephone,status')->where(['id' => $adminid])->limit(1)->find()) {
            return [];
        }
        $adminInfo['avatar'] = httpurl($adminInfo['avatar']);
        $adminInfo['nickname'] = get_real_val($adminInfo['full_name'], $adminInfo['user_name'], $adminInfo['telephone']);
        return $adminInfo;
    }

    /**
     * 获取用户所有权限
     * @param $uid 用户ID
     * @return array
     */
    public function getUserPermissions ($uid)
    {
        // 获取用户角色
        $roles = $this->getDb()->table('admin_role_user')->field('role_id')->where(['user_id' => $uid])->select();
        if (empty($roles)) {
            return [];
        }
        $roles = array_column($roles, 'role_id');

        // 获取权限
        $permissions = $this->getDb()
            ->table('admin_permission_role permission_role inner join admin_permissions permissions on permissions.id = permission_role.permission_id')
            ->field('permissions.name')
            ->where(['permission_role.role_id' => ['IN', $roles]])
            ->select();
        if (empty($permissions)) {
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
    public function getUserByRole ($store_id, $role_id)
    {
        $userList = $this->getDb()
            ->table('admin_role_user role inner join admin_user user on user.id = role.user_id')
            ->field('user.id,user.avatar,user.user_name,user.full_name,user.telephone')
            ->where(['role.role_id' => $role_id, 'user.store_id' => $store_id, 'user.status' => 1])
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
     * 添加管理员
     * @param $username 用户名
     * @param $password 密码
     * @param $role_id 角色
     * @param $ext
     * @return array
     */
    public function addAdmin ($username, $password, $role_id, array $ext = [])
    {
        $username = trim_space($username);
        $role_id  = intval($role_id);
        if (!$username) {
            return error('账号不能为空');
        }
        if (!$password) {
            return error('密码不能为空');
        }
        if (!$role_id) {
            return error('角色不能为空');
        }

        $condition = [];
        if (preg_match('/^\d+$/', $username)) {
            if (!validate_telephone($username)) {
                return error('手机号不正确');
            }
            $condition['telephone'] = $username;
        } else {
            $condition['user_name'] = $username;
        }

        // 获取用户
        if (!$userInfo = $this->getDb()->table($this->table)->field('id')->where($condition)->limit(1)->find()) {
            // 新增用户
            $condition['password']    = $password;
            $condition['create_time'] = date('Y-m-d H:i:s', TIMESTAMP);
            $condition['update_time'] = date('Y-m-d H:i:s', TIMESTAMP);
            $condition = array_merge($condition, $ext);
            if (!$id = $this->getDb()->insert($this->table, $condition, null, null, true)) {
                return error('添加用户失败');
            }
            $userInfo['id'] = $id;
        } else {
            $data = ['password' => $password, 'update_time' => date('Y-m-d H:i:s', TIMESTAMP)];
            $data = array_merge($data, $ext);
            if (!$this->getDb()->update($this->table, $data, $condition)) {
                return error('更新用户失败');
            }
        }

        // 添加权限
        if (!$this->getDb()->table('admin_role_user')->where(['user_id' => $userInfo['id'], 'role_id' => $role_id])->count()) {
            $this->getDb()->insert('admin_role_user', [
                'user_id' => $userInfo['id'],
                'role_id' => $role_id
            ]);
        }

        return success('ok');
    }

    /**
     * 删除管理员
     * @param $username 用户名
     * @return array
     */
    public function delAdmin ($username)
    {
        $username = trim_space($username);
        if (!$username) {
            return error('账号不能为空');
        }

        $condition = [];
        if (preg_match('/^\d+$/', $username)) {
            if (!validate_telephone($username)) {
                return error('手机号不正确');
            }
            $condition['telephone'] = $username;
        } else {
            $condition['user_name'] = $username;
        }

        // 获取用户
        if (!$userInfo = $this->getDb()->table($this->table)->field('id')->where($condition)->limit(1)->find()) {
            return error('该用户不存在');
        }

        if (!$this->getDb()->transaction(function ($db) use($userInfo) {
            if (!$db->delete($this->table, ['id' => $userInfo['id']])) {
                return false;
            }
            if (!$db->delete('admin_role_user', ['user_id' => $userInfo['id']])) {
                return false;
            }
            return true;
        })) {
            return error('删除失败');
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
            $this->getDb()->update('admin_failedlogin', [
                'login_count' => $count,
                'update_time' => TIMESTAMP
            ], ['id' => $faileInfo['id'], 'update_time' => $faileInfo['update_time']]);
        } else {
            $this->getDb()->insert('admin_failedlogin', [
                'login_count' => 1,
                'update_time' => TIMESTAMP,
                'account' => $account
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