<?php

namespace app\library;

/**
 * 阿里云短信签名助手 2017/11/19
 *
 */
class AliSmsHelper {
    
    private $accessKeyId = null;
    
    private $accessKeySecret = null;
    
    public function __construct($serviceName = 'ali') 
    {
        $smsConfig = getSysConfig('sms');
        $this->accessKeyId     = $smsConfig[$serviceName]['id'];
        $this->accessKeySecret = $smsConfig[$serviceName]['secret'];
    }
    
    /**
     * 批量发送短信
     * @param string $signName 短信签名
     * @param string $templateCode 短信模板Code
     * @param array $telephone_arr 短信接收号码[]
     * @param array $templateParam_arr 模板参数[[]]
     * @return bool
     */
    public function sendBatchSms($signName, $templateCode, $telephone_arr, $templateParam_arr)
    {
        $params = array ();
        
        // fixme 必填: 待发送手机号。支持JSON格式的批量调用，批量上限为100个手机号码,批量调用相对于单条调用及时性稍有延迟,验证码类型的短信推荐使用单条调用的方式
        $params["PhoneNumberJson"] = $telephone_arr;
        // fixme 必填: 短信签名，支持不同的号码发送不同的短信签名，每个签名都应严格按"签名名称"填写，请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/sign
        $params["SignNameJson"] = array_fill(0, count($telephone_arr), $signName);
        // fixme 必填: 短信模板Code，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
        $params["TemplateCode"] = $templateCode;
        // fixme 必填: 模板中的变量替换JSON串,如模板内容为"亲爱的${name},您的验证码为${code}"时,此处的值为
        // 友情提示:如果JSON中需要带换行符,请参照标准的JSON协议对换行符的要求,比如短信内容中包含\r\n的情况在JSON中需要表示成\\r\\n,否则会导致JSON在服务端解析失败
        $params["TemplateParamJson"] = $templateParam_arr;
    
        // *** 需用户填写部分结束, 以下代码若无必要无需更改 ***
        $params["TemplateParamJson"]  = json_encode($params["TemplateParamJson"], JSON_UNESCAPED_UNICODE);
        $params["SignNameJson"] = json_encode($params["SignNameJson"], JSON_UNESCAPED_UNICODE);
        $params["PhoneNumberJson"] = json_encode($params["PhoneNumberJson"], JSON_UNESCAPED_UNICODE);
        if(!empty($params["SmsUpExtendCodeJson"] && is_array($params["SmsUpExtendCodeJson"]))) {
            $params["SmsUpExtendCodeJson"] = json_encode($params["SmsUpExtendCodeJson"], JSON_UNESCAPED_UNICODE);
        }
    
        // 此处可能会抛出异常，注意catch
        $content = $this->request(
                $this->accessKeyId,
                $this->accessKeySecret,
                "dysmsapi.aliyuncs.com",
                array_merge($params, array(
                        "RegionId" => "cn-hangzhou",
                        "Action" => "SendBatchSms",
                        "Version" => "2017-05-25",
                ))
        );
    
        // 返回信息
        if ($content && $content->Code == 'OK') {
            return success('OK');
        }
        return error(get_real_val($content->Message, '未知错误'));
    }
    
    /**
     * 发送阿里云短信
     * @param string $signName 短信签名
     * @param string $templateCode 短信模板Code
     * @param string $telephone 短信接收号码
     * @param array $templateParam 模板参数
     * @return array
     */
    public function sendSms ($signName, $templateCode, $telephone, $templateParam)
    {
        $params = array();
        
        // fixme 必填: 短信接收号码
        $params["PhoneNumbers"] = $telephone;
        // fixme 必填: 短信签名，应严格按"签名名称"填写，请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/sign
        $params["SignName"] = $signName;
        // fixme 必填: 短信模板Code，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
        $params["TemplateCode"] = $templateCode;
        // fixme 可选: 设置模板参数, 假如模板中存在变量需要替换则为必填项
        $params['TemplateParam'] = $templateParam;
    
        // *** 需用户填写部分结束, 以下代码若无必要无需更改 ***
        if (!empty($params["TemplateParam"]) && is_array($params["TemplateParam"])) {
            $params["TemplateParam"] = json_encode($params["TemplateParam"], JSON_UNESCAPED_UNICODE);
        }
    
        // 此处可能会抛出异常，注意catch
        $content = $this->request($this->accessKeyId, $this->accessKeySecret, "dysmsapi.aliyuncs.com", array_merge($params, array(
                "RegionId" => "cn-hangzhou",
                "Action" => "SendSms",
                "Version" => "2017-05-25"
        )));
        
        // 返回信息
        if ($content && $content->Code == 'OK') {
            return success('OK');
        }
        return error(get_real_val($content->Message, '未知错误'));
    }

    /**
     * 生成签名并发起请求
     *
     * @param $accessKeyId string AccessKeyId (https://ak-console.aliyun.com/)
     * @param $accessKeySecret string AccessKeySecret
     * @param $domain string API接口所在域名
     * @param $params array API具体参数
     * @param $security boolean 使用https
     * @return bool|\stdClass 返回API接口调用结果，当发生错误时返回false
     */
    public function request($accessKeyId, $accessKeySecret, $domain, $params, $security=false)
    {
        $apiParams = array_merge(array (
            "SignatureMethod" => "HMAC-SHA1",
            "SignatureNonce" => uniqid(mt_rand(0,0xffff), true),
            "SignatureVersion" => "1.0",
            "AccessKeyId" => $accessKeyId,
            "Timestamp" => gmdate("Y-m-d\TH:i:s\Z"),
            "Format" => "JSON",
        ), $params);
        ksort($apiParams);

        $sortedQueryStringTmp = "";
        foreach ($apiParams as $key => $value) {
            $sortedQueryStringTmp .= "&" . $this->encode($key) . "=" . $this->encode($value);
        }

        $stringToSign = "GET&%2F&" . $this->encode(substr($sortedQueryStringTmp, 1));

        $sign = base64_encode(hash_hmac("sha1", $stringToSign, $accessKeySecret . "&",true));

        $signature = $this->encode($sign);

        $url = ($security ? 'https' : 'http')."://{$domain}/?Signature={$signature}{$sortedQueryStringTmp}";

        try {
            $result = https_request([
                    'url' => $url, 
                    'headers' => ['x-sdk-client' => 'php/2.0.0'], 
                    'encode' => 'object'
                ]);
            return $result['data'];
        } catch(\Exception $e) {
            return false;
        }
    }

    private function encode($str)
    {
        $res = urlencode($str);
        $res = preg_replace("/\+/", "%20", $res);
        $res = preg_replace("/\*/", "%2A", $res);
        $res = preg_replace("/%7E/", "~", $res);
        return $res;
    }

}