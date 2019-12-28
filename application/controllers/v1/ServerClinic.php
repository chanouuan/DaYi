<?php

namespace app\controllers;

use ActionPDO;
use app\models\AdminModel;
use app\models\UserModel;
use app\models\DoctorOrderModel;
use app\models\TreatmentModel;
use app\models\DrugModel;
use app\models\StockModel;
use app\models\ClinicModel;
use app\models\FeedbackModel;
use app\models\ServerClinicModel;
use app\common\GenerateCache;

/**
 * 诊所服务端接口
 * @Date 2019-10-01
 */
class ServerClinic extends ActionPDO {

    public function _ratelimit ()
    {
        return [
            'login'                => ['interval' => 1000],
            'sendSms'              => ['interval' => 1000, 'rule' => '5|10|20'],
            'regClinic'            => ['interval' => 1000, 'rule' => '5|10|20'],
            'getClinicDoctors'     => ['interval' => 1000],
            'microLogin'           => ['interval' => 1000],
            'logout'               => ['interval' => 1000],
            'getUserProfile'       => ['interval' => 1000],
            'createDoctorCard'     => ['interval' => 1000],
            'getTodayOrderList'    => ['interval' => 200],
            'getDoctorOrderList'   => ['interval' => 200],
            'getDoctorOrderDetail' => ['interval' => 1000],
            'saveDoctorCard'       => ['interval' => 1000],
            'printTemplete'        => ['interval' => 1000],
            'buyDrug'              => ['interval' => 1000],
            'localRefund'          => ['interval' => 2000, 'url' => $_POST['order_id']],
            'localCharge'          => ['interval' => 2000, 'url' => $_POST['order_id']],
            'getMessageCount'      => ['interval' => 1000],
            'getDrugList'          => ['interval' => 200],
            'saveDrug'             => ['interval' => 1000],
            'getTreatmentList'     => ['interval' => 200],
            'saveTreatment'        => ['interval' => 1000],
            'getEmployeeList'      => ['interval' => 200],
            'saveEmployee'         => ['interval' => 1000],
            'getRoleList'          => ['interval' => 200],
            'saveRole'             => ['interval' => 1000],
            'viewRole'             => ['interval' => 1000],
            'viewPermissions'      => ['interval' => 1000],
            'addStock'             => ['interval' => 1000],
            'editStock'            => ['interval' => 1000],
            'getStockPullOrPush'   => ['interval' => 200],
            'getStockSale'         => ['interval' => 200],
            'confirmStock'         => ['interval' => 1000],
            'delStock'             => ['interval' => 1000],
            'batchDetail'          => ['interval' => 1000],
            'stockDetail'          => ['interval' => 1000],
            'saveClinicConfig'     => ['interval' => 1000],
            'checkVipState'        => ['interval' => 1000],
            'getVipSale'           => ['interval' => 1000],
            'createPayed'          => ['interval' => 1000],
            'notifyPayed'          => ['interval' => 1000],
            'indexCount'           => ['interval' => 1000],
            'feedback'             => ['interval' => 1000],
            'importCsv'            => ['interval' => 2000]
        ];
    }

    public function _init()
    {
        if ($this->_G['user']) {
            // 获取权限
            $permissions = isset($this->_G['token'][4]) ? explode('^', $this->_G['token'][4]) : [];
            $permissions = GenerateCache::mapPermissions($permissions);
            // 忽略列表
            $ignoreAccess = [
                'logout',
                'getUserProfile',
                'getDoctorList',
                'printTemplete',
                'getDoctorOrderDetail',
                'getDrugInfo',
                'feedback'
            ];
            // 重命名
            $map = [
                'getTodayOrderList'  => 'createDoctorCard',
                'localRefund'        => 'localCharge',
                'getDoctorOrderList' => 'saveDoctorCard',
                'getDrugList'        => 'saveDrug',
                'getTreatmentList'   => 'saveDrug',
                'saveTreatment'      => 'saveDrug',
                'importCsv'          => 'saveDrug',
                'getEmployeeList'    => 'saveEmployee',
                'getEmployeeRole'    => 'saveEmployee',
                'getRoleList'        => 'saveEmployee',
                'saveRole'           => 'saveEmployee',
                'getStockList'       => 'addStock',
                'getStockPullOrPush' => 'addStock',
                'getStockSale'       => 'addStock',
                'confirmStock'       => 'addStock',
                'delStock'           => 'addStock',
                'batchDetail'        => 'addStock',
                'stockDetail'        => 'addStock',
                'editStock'          => 'addStock',
                'saveClinicConfig'   => 'baseConfig'
            ];
            // 权限值
            $action = isset($map[$this->_action]) ? $map[$this->_action] : $this->_action;
            // 权限验证
            if (!in_array($action, $ignoreAccess)) {
                if (empty(array_intersect(['ANY', $action], $permissions))) {
                    json(null,'权限不足', 100);
                }
            }
        }
    }

    /**
     * 注册诊所
     * @param *name 名称
     * @param address 地址
     * @param *user_name 登录账号
     * @param *password 登录密码
     * @param *invite_code 邀请码
     * @param *telephone 手机号
     * @param *msgcode 短信验证码
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{
     *   "clinic_id": 1
     * }}
     */
    public function regClinic ()
    {
        return (new ClinicModel())->regClinic($_POST);
    }

    /**
     * 获取诊所医生
     * @param *clinic_id 诊所
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function getClinicDoctors ()
    {
        return success((new AdminModel())->getUserByDoctor(intval(getgpc('clinic_id'))));
    }

    /**
     * 登录 (签名验证)
     * @param *clinic_id 诊所
     * @param *username 登录账号
     * @param *time 当前时间戳
     * @param *nonce_str 自定义随机数
     * @param *sig 签名
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{
     *     "user_id":1,
     *     "token":"", //登录凭证
     * }}
     */
    public function microLogin ()
    {
        // 校验sign
        $authResult = checkSignPass($_POST);
        if($authResult['errorcode'] !== 0) {
            return $authResult;
        }

        return (new AdminModel())->login([
            'clinic_id' => $_POST['clinic_id'],
            'username'  => $_POST['username'],
            'password'  => true
        ]);
    }

    /**
     * 登录
     * @param *clinic_id 诊所
     * @param *username 手机号/账号
     * @param *password 密码
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{
     *     "user_id":1,
     *     "avatar":"", //头像
     *     "telephone":"", //手机号
     *     "nickname":"", //昵称
     *     "token":"", //登录凭证
     *     "permission":"" //权限
     * }}
     */
    public function login ()
    {
        return (new AdminModel())->login([
            'clinic_id' => $_POST['clinic_id'],
            'username'  => $_POST['username'],
            'password'  => strval($_POST['password'])
        ]);
    }

    /**
     * 发送短信验证码
     * @param *telephone 手机号
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function sendSms () 
    {
        return (new UserModel())->sendSmsCode($_POST);
    }

    /**
     * 退出登录
     * @login
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
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
     * "errorcode":0,
     * "message":"",
     * "result":{
     *     "id":1,
     *     "avatar":"", //头像
     *     "telephone":"", //手机号
     *     "nickname":"", //昵称
     *     "unread_count":0, //未读消息数
     *     "clinic_info":{}
     * }}
     */
    public function getUserProfile ()
    {
        $result = (new ServerClinicModel())->getUserProfile($this->_G['user']['user_id']);
        if ($result['errorcode'] === 0) {
            $permissions = isset($this->_G['token'][4]) ? explode('^', $this->_G['token'][4]) : [];
            $permissions = GenerateCache::mapPermissions($permissions);
            $result['result']['permission'] = $permissions;
            $s = strlen(implode('', $permissions));
            $result['result']['s'] = $s * 2 + $s % 10 + 127;
        }
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
     * @param notes string 处方笺 [{category:处方类别,relation_id:药品ID/诊疗项目ID,single_amount:单量,total_amount:总量,usages:用法,frequency:频率,drug_days:用药天数,remark:备注}]
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{
     *      "order_id":1, //订单号
     *      "print_code":1, //票据号
     * }}
     */
    public function createDoctorCard ()
    {
        return (new DoctorOrderModel($this->_G['user']['user_id']))->createDoctorCard($_POST);
    }

    /**
     * 获取会诊列表
     * @login
     * @param page 当前页
     * @param start_time 开始时间
     * @param end_time 结束时间
     * @param status 状态
     * @param doctor_id 医生
     * @param patient_name 患者
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{
     *     "total":1, //总条数
     *     "list":[{
     *         "id":1,
     *         "enum_source":1, //来源
     *         "doctor_name":1, //医生
     *         "patient_name":1, //患者姓名
     *         "patient_gender":1, //患者性别
     *         "create_time":1, //会诊时间
     *         "pay":0, //付款
     *         "discount":0, //优惠
     *         "payway":"", //支付方式
     *         "voice":"", //录音
     *         "status":1, //状态
     *     }]
     * }}
     */
    public function getDoctorOrderList ()
    {
        return (new DoctorOrderModel($this->_G['user']['user_id']))->getDoctorOrderList($_POST);
    }

    /**
     * 获取今日会诊
     * @login
     * @param page 当前页
     * @param start_time 开始时间
     * @param end_time 结束时间
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{
     *     "total":1, //总条数
     *     "list":[{
     *         "id":1,
     *         "patient_name":1, //患者姓名
     *         "patient_gender":1, //患者性别
     *         "create_time":1, //会诊时间
     *         "status":1, //状态
     *     }]
     * }}
     */
    public function getTodayOrderList ()
    {
        return (new DoctorOrderModel($this->_G['user']['user_id']))->getTodayOrderList($_POST);
    }

    /**
     * 查看会诊单详情
     * @login
     * @param *order_id 订单ID
     * @return array
     * {
     * "errorcode":0,
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
     *         "category":"", //处方类型
     *         "relation_id":"", //药品/诊疗ID
     *         "name":"", //药品/诊疗
     *         "package_spec":"", //规格
     *         "dispense_unit":"", //库存单位
     *         "dosage_unit":"", //剂量单位
     *         "single_amount":"", //单量
     *         "total_amount":"", //总量
     *         "usages":"", //用法
     *         "frequency":"", //频率
     *         "drug_days":"", //天数
     *         "dose":1, //草药剂量
     *         "remark":"" //备注
     *     }]
     * }}
     */
    public function getDoctorOrderDetail ()
    {
        return (new DoctorOrderModel($this->_G['user']['user_id']))->getDoctorOrderDetail(getgpc('order_id'));
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
     * @param *notes string 处方笺 [{category:处方类别,relation_id:药品ID/诊疗项目ID,single_amount:单量,total_amount:总量,usages:用法,frequency:频率,drug_days:用药天数,remark:备注}]
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function saveDoctorCard ()
    {
        return (new DoctorOrderModel($this->_G['user']['user_id']))->saveDoctorCard($_POST);
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
     * "errorcode":0,
     * "message":"",
     * "result":{
     *     "order_id":1 //订单ID
     * }}
     */
    public function buyDrug ()
    {
        return (new DoctorOrderModel($this->_G['user']['user_id']))->buyDrug($_POST);
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
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function localCharge ()
    {
        return (new DoctorOrderModel($this->_G['user']['user_id']))->localCharge($_POST);
    }

    /**
     * 线下退费
     * @login
     * @param *order_id 订单ID
     * @param payway 退费方式
     * @param notes 退费单项
     * @param remark 备注
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function localRefund ()
    {
        return (new DoctorOrderModel($this->_G['user']['user_id']))->localRefund($_POST);
    }

    /**
     * 搜索患者
     * @param *name 患者姓名
     * @return array
     * {
     * "errorcode":0,
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
        return (new ServerClinicModel())->searchPatient($_POST);
    }

    /**
     * 搜索药品
     * @param *clinic_id 诊所
     * @param *drug_type 药品类型
     * @param *name 药品名称
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function searchDrug ()
    {
        return (new ServerClinicModel())->searchDrug($_POST);
    }

    /**
     * 搜索药品条形码
     * @param *clinic_id 诊所
     * @param *barcode 条形码
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function searchBarcode ()
    {
        return (new ServerClinicModel())->searchBarcode($_POST);
    }

    /**
     * 搜索批次
     * @param *clinic_id 诊所
     * @param *name 药品名称
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function searchBatch ()
    {
        return (new ServerClinicModel())->searchBatch($_POST);
    }

    /**
     * 药品查询
     * @param *drug_type 药品类型
     * @param *name
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function searchDrugDict ()
    {
        return (new ServerClinicModel())->searchDrugDict($_POST);
    }

    /**
     * 搜索诊疗项目
     * @param *clinic_id 门店ID
     * @param *name 名称
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function searchTreatmentSheet ()
    {
        return (new ServerClinicModel())->searchTreatmentSheet($_POST);
    }

    /**
     * 疾病诊断查询
     * @param *name
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function searchICD ()
    {
        return (new ServerClinicModel())->searchICD($_POST);
    }

    /**
     * 获取过敏史
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function getAllergyEnum ()
    {
        return (new ServerClinicModel())->getAllergyEnum();
    }

    /**
     * 获取药品用法
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function getUsageEnum ()
    {
        return (new ServerClinicModel())->getUsageEnum();
    }

    /**
     * 获取药品频率
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function getNoteFrequencyEnum ()
    {
        return (new ServerClinicModel())->getNoteFrequencyEnum();
    }

    /**
     * 获取药品剂型
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function getDosageEnum ()
    {
        return (new ServerClinicModel())->getDosageEnum();
    }

    /**
     * 获取药品单位
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function getDrugUnitEnum ()
    {
        return (new ServerClinicModel())->getDrugUnitEnum();
    }

    /**
     * 获取诊疗项目单位
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function getTreatmentUnitEnum ()
    {
        return (new ServerClinicModel())->getTreatmentUnitEnum();
    }

    /**
     * 获取支付方式
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function getLocalPayWay ()
    {
        return (new ServerClinicModel())->getLocalPayWay();
    }

    /**
     * 获取医生列表
     * @login
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function getDoctorList ()
    {
        return (new ServerClinicModel())->getDoctorList($this->_G['user']['user_id'], getgpc('all'));
    }

    /**
     * 版本号检查
     * @param *version 版本号
     * @return array
     * {
     * "errorcode":0,
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
        return (new ServerClinicModel())->versionCheck(getgpc('version'));
    }

    /**
     * 获取安装地址
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function getInstallAddr ()
    {
        return (new ServerClinicModel())->getInstallAddr();
    }

    /**
     * 打印模板
     * @login
     * @param *order_id 订单ID
     * @param *type 类型
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function printTemplete ()
    {
        return (new DoctorOrderModel($this->_G['user']['user_id']))->printTemplete(getgpc('type'), getgpc('order_id'));
    }

    /**
     * 录音回调
     * @param *clinic_id 诊所
     * @param *order_id 订单
     * @param *url 地址
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function notifyVoice ()
    {
        return (new DoctorOrderModel(null, getgpc('clinic_id')))->notifyVoice(getgpc('order_id'), getgpc('url'));
    }

    /**
     * 获取药品列表
     * @login
     * @param page 当前页
     * @param name 名称
     * @param status 状态
     * @param drug_type 类型
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{
     *     "total":1, //总条数
     *     "list":[]
     * }}
     */
    public function getDrugList ()
    {
        return (new DrugModel($this->_G['user']['user_id']))->getDrugList($_POST);
    }

    /**
     * 添加药品
     * @login
     * @param drug_type 药品类型
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function saveDrug ()
    {
        return (new DrugModel($this->_G['user']['user_id']))->saveDrug($_POST);
    }

    /**
     * 获取药品信息
     * @login
     * @param id 药品ID
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function getDrugInfo ()
    {
        return success((new DrugModel($this->_G['user']['user_id']))->getDrugInfo(getgpc('id')));
    }

    /**
     * 获取诊疗项目列表
     * @login
     * @param page 当前页
     * @param name 名称
     * @param status 状态
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{
     *     "total":1, //总条数
     *     "list":[]
     * }}
     */
    public function getTreatmentList ()
    {
        return (new TreatmentModel())->getTreatmentList($this->_G['user']['user_id'], $_POST);
    }

    /**
     * 添加诊疗项目
     * @login
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function saveTreatment ()
    {
        return (new TreatmentModel())->saveTreatment($this->_G['user']['user_id'], $_POST);
    }

    /**
     * 获取诊疗项目信息
     * @param id 诊疗项目ID
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function getTreatmentInfo ()
    {
        return success((new TreatmentModel())->getTreatmentInfo(getgpc('id')));
    }

    /**
     * 获取员工列表
     * @login
     * @param page 当前页
     * @param name 名称
     * @param status 状态
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{
     *     "total":1, //总条数
     *     "list":[]
     * }}
     */
    public function getEmployeeList ()
    {
        return (new AdminModel())->getEmployeeList($this->_G['user']['user_id'], $_POST);
    }

    /**
     * 添加员工
     * @login
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function saveEmployee ()
    {
        return (new AdminModel())->saveEmployee($this->_G['user']['user_id'], $_POST);
    }

    /**
     * 获取员工信息
     * @param id
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function getEmployeeInfo ()
    {
        return success((new AdminModel())->getEmployeeInfo(getgpc('id')));
    }

    /**
     * 获取员工职位
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function getEmployeeTitle ()
    {
        return (new ServerClinicModel())->getEmployeeTitle();
    }

    /**
     * 获取员工角色
     * @login
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function getEmployeeRole ()
    {
        return success((new AdminModel())->getEmployeeRole($this->_G['user']['user_id']));
    }

    /**
     * 获取角色列表
     * @login
     * @param page 当前页
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{
     *     "total":1, //总条数
     *     "list":[]
     * }}
     */
    public function getRoleList ()
    {
        return (new AdminModel())->getRoleList($this->_G['user']['user_id'], $_POST);
    }

    /**
     * 添加角色
     * @login
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function saveRole ()
    {
        return (new AdminModel())->saveRole($this->_G['user']['user_id'], $_POST);
    }

    /**
     * 查看角色
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function viewRole ()
    {
        return (new AdminModel())->viewRole(getgpc('id'));
    }

    /**
     * 查看权限
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function viewPermissions ()
    {
        return (new AdminModel())->viewPermissions();
    }

    /**
     * 获取库存列表
     * @login
     * @param page 当前页
     * @param name 名称
     * @param drug_type 类型
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{
     *     "total":1, //总条数
     *     "list":[]
     * }}
     */
    public function getStockList ()
    {
        $_POST['is_procure'] = 1; // 已采购
        return (new DrugModel($this->_G['user']['user_id']))->getDrugList($_POST);
    }

    /**
     * 获取出入库列表
     * @login
     * @param page 当前页
     * @param stock_type 出入库类型
     * @param stock_way 出入库方式
     * @param start_time 开始时间
     * @param end_time 结束时间
     * @param status 状态
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{
     *     "total":1, //总条数
     *     "list":[]
     * }}
     */
    public function getStockPullOrPush ()
    {
        return (new StockModel($this->_G['user']['user_id']))->getStockPullOrPush($_POST);
    }

    /**
     * 获取进销存详情
     * @login
     * @param page 当前页
     * @param stock_way 出入库方式
     * @param start_time 开始时间
     * @param end_time 结束时间
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{
     *     "total":1, //总条数
     *     "list":[]
     * }}
     */
    public function getStockSale ()
    {
        return (new StockModel($this->_G['user']['user_id']))->getStockSale($_POST);
    }

    /**
     * 获取出入库方式
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function getStockWayEnum ()
    {
        return (new ServerClinicModel())->getStockWayEnum(getgpc('all'));
    }

    /**
     * 添加出入库
     * @login
     * @param stock_type 出入库类型
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{
     *      "stock_id":1
     * }}
     */
    public function addStock ()
    {
        return (new StockModel($this->_G['user']['user_id']))->addStock($_POST);
    }

    /**
     * 编辑出入库
     * @login
     * @param stock_id 出入库ID
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{
     *      "stock_id":1
     * }}
     */
    public function editStock ()
    {
        return (new StockModel($this->_G['user']['user_id']))->editStock($_POST);
    }

    /**
     * 确认出入库
     * @login
     * @param stock_id 出入库ID
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{
     *      "stock_id":1
     * }}
     */
    public function confirmStock ()
    {
        return (new StockModel($this->_G['user']['user_id']))->confirmStock($_POST);
    }

    /**
     * 删除出入库
     * @login
     * @param stock_id 出入库ID
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{}
     * }
     */
    public function delStock ()
    {
        return (new StockModel($this->_G['user']['user_id']))->delStock($_POST);
    }

    /**
     * 批次详情
     * @login
     * @param drug_id 药品ID
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{}
     * }
     */
    public function batchDetail ()
    {
        return (new StockModel($this->_G['user']['user_id']))->batchDetail($_POST);
    }

    /**
     * 出入库详情
     * @login
     * @param stock_id 出入库ID
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{}
     * }
     */
    public function stockDetail ()
    {
        return (new StockModel($this->_G['user']['user_id']))->stockDetail($_POST);
    }

    /**
     * 保存诊所配置
     * @login
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{}
     * }
     */
    public function saveClinicConfig ()
    {
        return (new ClinicModel())->saveClinicConfig($this->_G['user']['user_id'], $_POST);
    }

    /**
     * 检查vip
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{}
     * }
     */
    public function checkVipState ()
    {
        return (new ClinicModel())->checkVipState(getgpc('clinic_id'));
    }

    /**
     * 获取vip售价
     * @param level 等级
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{}
     * }
     */
    public function getVipSale ()
    {
        return (new ClinicModel())->getVipSale($_POST);
    }

    /**
     * 生成收款码
     * @param sale_id sale_id
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{}
     * }
     */
    public function createPayed ()
    {
        return (new ClinicModel())->createPayed($_POST);
    }

    /**
     * 生成收款码
     * @param trade_id trade_id
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{}
     * }
     */
    public function notifyPayed ()
    {
        return (new ClinicModel())->notifyPayed($_POST);
    }

    /**
     * 首页统计
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":{}
     * }
     */
    public function indexCount ()
    {
        return (new ServerClinicModel())->indexCount(getgpc('clinic_id'));
    }

    /**
     * 意见反馈
     * @login
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function feedback ()
    {
        return (new FeedbackModel())->feedback($this->_G['user']['user_id'], $_POST);
    }

    /**
     * 下载模板
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function downloadCsvTemplate ()
    {
        return (new ServerClinicModel())->downloadCsvTemplate(getgpc('type'));
    }

    /**
     * 导入数据
     * @login
     * @return array
     * {
     * "errorcode":0,
     * "message":"",
     * "result":[]
     * }
     */
    public function importCsv ()
    {
        return (new ServerClinicModel())->importCsv($this->_G['user']['user_id'], getgpc('type'));
    }

}
