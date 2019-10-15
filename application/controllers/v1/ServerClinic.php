<?php

namespace app\controllers;

use ActionPDO;
use app\models\AdminModel;
use app\models\DoctorOrderModel;

/**
 * 诊所服务端接口
 * @Date 2019-10-01
 */
class ServerClinic extends ActionPDO {

    public function __ratelimit ()
    {
        return [
            'login'                => ['interval' => 1000],
            'logout'               => ['interval' => 1000],
            'getUserProfile'       => ['interval' => 1000],
            'doctorCreateCard'     => ['interval' => 1000],
            'getDoctorOrderList'   => ['interval' => 1000],
            'getDoctorOrderDetail' => ['interval' => 1000],
            'unionConsultation'    => ['interval' => 1000],
            'saveDoctorCard'       => ['interval' => 1000],
            'buyDrug'              => ['interval' => 1000],
            'localCharge'          => ['interval' => 1000],
            'getMessageCount'      => ['interval' => 1000]
        ];
    }

    public function __init()
    {
        if ($this->_G['user']) {
            // 获取权限
            $this->_G['token'][5] = json_decode($this->_G['token'][5], true);
            $ignoreAccess = [
                'getUserProfile',
                'getDoctorList'
            ];
            // 权限验证
            if (!in_array($this->_action, $ignoreAccess)) {
                if (empty(array_intersect(['ANY', $this->_action], $this->_G['token'][5]))) {
                    json(null,'权限不足', 100);
                }
            }
        }
    }

    /**
     * 登录
     * @param *username 手机号/账号
     * @param *password 密码
     * @return array
     * {
     * "errorcode":0, // 错误码 0成功 -1失败
     * "message":"", //错误消息
     * "result":{
     *     "user_id":1,
     *     "avatar":"", //头像
     *     "telephone":"", //手机号
     *     "nickname":"", //昵称
     *     "token":"", //登录凭证
     *     "role":"", //角色
     *     "permission":"" //权限
     * }}
     */
    public function login ()
    {
        return (new AdminModel())->login([
            'username' => $_POST['username'],
            'password' => $_POST['password']
        ]);
    }

    /**
     * 退出登录
     * @login
     * @return array
     * {
     * "errorcode":0, // 错误码 0成功 -1失败
     * "message":"", //错误消息
     * "result":[]
     * }
     */
    public function logout ()
    {
        return (new AdminModel())->logout($this->_G['user']['user_id'], $this->_G['user']['clienttype']);
    }

    /**
     * 获取登录用户信息
     * @login
     * @return array
     * {
     * "errorcode":0, // 错误码 0成功 -1失败
     * "message":"", //错误消息
     * "result":{
     *     "id":1, 
     *     "avatar":"", //头像
     *     "telephone":"", //手机号
     *     "nickname":"", //昵称
     *     "unread_count":0, //未读消息数
     *     "store_info":{
     *          "id":1, 
     *          "name":"", // 诊所
     *          "status":1 // 状态
     *     }
     * }}
     */
    public function getUserProfile ()
    {
        $result = (new DoctorOrderModel())->getUserProfile($this->_G['user']['user_id']);
        if ($result['errorcode'] !== 0) {
            return $result;
        }
        $result['result']['role'] = json_decode($this->_G['token'][4], true);
        $result['result']['permission'] = $this->_G['token'][5];
        return $result;
    }

    /**
     * 医生接诊
     * @login
     * @param advanced 高级模式
     * @param patient_name 患者姓名
     * @param patient_gender 患者性别
     * @param patient_age 患者年龄
     * @param patient_tel 患者手机
     * @param patient_complaint 主诉
     * @param patient_allergies 过敏史
     * @param patient_diagnosis 诊断
     * @param note_dose 草药剂量
     * @param note_side 草药内服或外用（1内服2外用）
     * @param advice 医嘱
     * @param voice 录音地址
     * @param notes string 处方笺 [{category:处方类别,relation_id:药品ID/诊疗项目ID,single_amount:单量,total_amount:总量,usage:用法,frequency:频率,drug_days:用药天数,remark:备注}]
     * @return array
     * {
     * "errorcode":0, //错误码 0成功 -1失败
     * "message":"",
     * "result":{
     *      "order_id":1, //订单号
     *      "print_code":1, //票据号
     * }}
     */
    public function doctorCreateCard ()
    {
        return (new DoctorOrderModel())->doctorCreateCard($this->_G['user']['user_id'], $_POST);
    }

    /**
     * 获取会诊单列表
     * @login
     * @param page 当前页
     * @param start_time 开始时间
     * @param end_time 结束时间
     * @return array
     * {
     * "errorcode":0, //错误码 0成功 -1失败
     * "message":"",
     * "result":{
     *     "total":1, //总条数
     *     "list":[{
     *         "id":1,
     *         "patient_name":1, //患者姓名
     *         "patient_gender":1, //患者性别
     *         "patient_age":1, //患者年龄
     *         "create_time":1, //会诊时间
     *         "status":1, //状态
     *     }]
     * }}
     */
    public function getDoctorOrderList ()
    {
        return (new DoctorOrderModel())->getDoctorOrderList($this->_G['user']['user_id'], $_POST);
    }

    /**
     * 查看会诊单详情
     * @login
     * @param *order_id 订单ID
     * @return array
     * {
     * "errorcode":0, //错误码 0成功 -1失败
     * "message":"",
     * "result":{
     *     "id":"",
     *     "doctor_id":"", //医生ID
     *     "doctor_name":"", //医生姓名
     *     "enum_source":"", //来源
     *     "patient_name":"", //患者姓名
     *     "patient_tel":"", //患者电话
     *     "patient_gender":"", //患者性别
     *     "patient_age":"", //患者年龄
     *     "patient_complaint":"", //主诉
     *     "patient_allergies":"", //过敏史
     *     "patient_diagnosis":"", //诊断
     *     "note_side":"", //草药外服或内服
     *     "advice":"", //医嘱
     *     "voice":"", //录音
     *     "pay":"", //应付
     *     "discount":"", //优惠
     *     "payway":"", //付款方式
     *     "status":"", //状态
     *     "create_time":"", //时间
     *     "notes":[{
     *         "id":"",
     *         "enum_category":"", //处方类型
     *         "relation_id":"", //药品/诊疗ID
     *         "name":"", //药品/诊疗
     *         "package_spec":"", //规格
     *         "dispense_unit":"", //库存单位
     *         "dosage_unit":"", //剂量单位
     *         "single_amount":"", //单量
     *         "total_amount":"", //总量
     *         "enum_usage":"", //用法
     *         "enum_frequency":"", //频率
     *         "drug_days":"", //天数
     *         "dose":1, //草药剂量
     *         "remark":"" //备注
     *     }]
     * }}
     */
    public function getDoctorOrderDetail ()
    {
        return (new DoctorOrderModel())->getDoctorOrderDetail(getgpc('order_id'));
    }

    /**
     * 联诊
     * @login
     * @param *print_code 取号号码
     * @return array
     * {
     * "errorcode":0, //错误码 0成功 -1失败
     * "message":"",
     * "result":[] //同详情
     * }
     */
    public function unionConsultation ()
    {
        return (new DoctorOrderModel())->unionConsultation($this->_G['user']['user_id'], getgpc('print_code'));
    }

    /**
     * 编辑保存会诊单
     * @login
     * @param *order_id 订单ID
     * @param *patient_name 患者姓名
     * @param patient_gender 患者性别
     * @param patient_age 患者年龄
     * @param patient_tel 患者手机
     * @param patient_complaint 主诉
     * @param patient_allergies 过敏史
     * @param patient_diagnosis 诊断
     * @param note_dose 草药剂量
     * @param note_side 草药内服或外用（1内服2外用）
     * @param advice 医嘱
     * @param *notes string 处方笺 [{category:处方类别,relation_id:药品ID/诊疗项目ID,single_amount:单量,total_amount:总量,usage:用法,frequency:频率,drug_days:用药天数,remark:备注}]
     * @return array
     * {
     * "errorcode":0, //错误码 0成功 -1失败
     * "message":"",
     * "result":[]
     * }
     */
    public function saveDoctorCard ()
    {
        return (new DoctorOrderModel())->saveDoctorCard($_POST);
    }

    /**
     * 购药
     * @login
     * @param patient_name 患者姓名
     * @param patient_gender 患者性别
     * @param patient_age 患者年龄
     * @param patient_tel 患者手机
     * @param *notes string 药品 [{category:处方类别,relation_id:药品ID,total_amount:总量}]
     * @return array
     * {
     * "errorcode":0, //错误码 0成功 -1失败
     * "message":"",
     * "result":{
     *     "order_id":1 //订单ID
     * }}
     */
    public function buyDrug ()
    {
        return (new DoctorOrderModel())->buyDrug($this->_G['user']['user_id'], $_POST);
    }

    /**
     * 线下收费
     * @login
     * @param *order_id 订单ID
     * @param *payway 付款方式
     * @param *money 付款金额（元）
     * @param second_payway 其他付款方式
     * @param second_money 其他付款金额（元）
     * @param discount_type 优惠类型
     * @param discount_val 优惠变量值
     * @param remark 备注
     * @return array
     * {
     * "errorcode":0, //错误码 0成功 -1失败
     * "message":"",
     * "result":[]
     * }
     */
    public function localCharge ()
    {
        return (new DoctorOrderModel())->localCharge($this->_G['user']['user_id'], $_POST);
    }

    /**
     * 搜索患者
     * @param *name 患者姓名
     * @return array
     * {
     * "errorcode":0, //错误码 0成功 -1失败
     * "message":"",
     * "result":[{
     *     "id":0,
     *     "name":"",
     *     "telephone":"",
     *     "age_year":0,
     *     "age_month":0,
     *     "gender":0
     * }]
     * }
     */
    public function searchPatient ()
    {
        return (new DoctorOrderModel())->searchPatient($_POST);
    }

    /**
     * 获取过敏史
     * @return array
     * {
     * "errorcode":0, //错误码 0成功 -1失败
     * "message":"",
     * "result":[]
     * }
     */
    public function getAllergyEnum ()
    {
        return (new DoctorOrderModel())->getAllergyEnum();
    }

    /**
     * 获取药品用法
     * @param category 药品分类
     * @return array
     * {
     * "errorcode":0, //错误码 0成功 -1失败
     * "message":"",
     * "result":[]
     * }
     */
    public function getUsageEnum ()
    {
        return (new DoctorOrderModel())->getUsageEnum(getgpc('category'));
    }

    /**
     * 获取药品频率
     * @return array
     * {
     * "errorcode":0, //错误码 0成功 -1失败
     * "message":"",
     * "result":[]
     * }
     */
    public function getNoteFrequencyEnum ()
    {
        return (new DoctorOrderModel())->getNoteFrequencyEnum();
    }

    /**
     * 获取医生列表
     * @login
     * @return array
     * {
     * "errorcode":0, //错误码 0成功 -1失败
     * "message":"",
     * "result":[]
     * }
     */
    public function getDoctorList ()
    {
        return (new DoctorOrderModel())->getDoctorList($this->_G['user']['user_id']);
    }

    /**
     * 版本号检查
     * @param *version 版本号
     * @return array
     * {
     * "errorcode":0, //错误码 0成功 -1失败
     * "message":"",
     * "result":{
     *     "upgrade_mode":"", //升级方式（1询问2强制3静默）
     *     "version":"", //版本号
     *     "note":"", //版本描述
     *     "url":"", //下载地址
     *     "mb":"" //安装包大小 (mb)
     * }}
     */
    public function versionCheck ()
    {
        return (new DoctorOrderModel())->versionCheck(getgpc('version'));
    }

}
