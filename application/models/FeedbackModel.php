<?php

namespace app\models;

use Crud;

class FeedbackModel extends Crud {

    protected $table = 'dayi_feedback';

    /**
     * 意见反馈
     * @return array
     */
    public function feedback ($user_id, $post)
    {
        $userInfo = (new AdminModel())->checkAdminInfo($user_id);

        $post['content'] = trim_space($post['content'], 0, 200);

        if (!$post['content']) {
            return error('请输入内容');
        }

        // 保存截图
        $screen_capture = null;
        if ($post['screen_capture']) {
            $pos = strpos($post['screen_capture'], 'base64');
            if ($pos) {
                $data_img = substr($post['screen_capture'], $pos + 7);
                $upload_path = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'upload';
                $file_path = uniqid() . '.png';
                mkdirm($upload_path . DIRECTORY_SEPARATOR);
                file_put_contents($upload_path . DIRECTORY_SEPARATOR . $file_path, base64_decode($data_img));
                $screen_capture = str_replace('\\', '/', 'upload/' . $file_path);
                unset($data_img, $post['screen_capture']);
            }
        }
        
        if (!$this->getDb()->insert([
                'user_id'        => $user_id,
                'clinic_id'      => $userInfo['clinic_id'],
                'content'        => $post['content'],
                'screen_capture' => $screen_capture,
                'create_time'    => date('Y-m-d H:i:s', TIMESTAMP)
            ])) {
            return error('保存失败');
        }

        return success('ok');
    }

}
