<?php

namespace app\models;

use Crud;

class VersionModel extends Crud {

    protected $table = 'pro_version';

    /**
     * 版本号检查
     * @param $os 系统平台
     * @param $version 版本号
     * @result array
     */
    public function check ($os, $version)
    {
        // 验证客户端版本号
        if (!$versionInfo = $this->find(['os' => $os, 'version' => $version], 'id')) {
            return error('无效版本');
        }

        // 客户端版本与最新版本相差多个版本的，要先判断当中是否有整包升级的版本，有则先升级该版本
        if (!$libaray = $this->find(['os' => $os, 'id' => ['>', $versionInfo['id']], 'status' => 1], 'id,install_mode,upgrade_mode,version,note,url,mb', 'install_mode asc, id desc')) {
            return error('当前已是最新版本');
        }

        // 更新下载量
        $this->getDb()->update($this->table, ['download' => ['download+1']], ['id' => $libaray['id']]);

        return success($libaray);
    }

}
