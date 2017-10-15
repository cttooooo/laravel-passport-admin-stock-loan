<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\SmsRepository;
use App\User;
use Illuminate\Http\Request;
use App\Repositories\RegisterRepository;

/**
 * 注册
 * Class RegisterController
 * @package App\Http\Controllers\Api
 */
class RegisterController extends Controller
{
    private $register = null;
    private $sms = null;

    public function __construct(RegisterRepository $register, SmsRepository $sms)
    {
        $this->register = $register;
        $this->sms = $sms;
    }

    /**
     * 注册
     * @param Request $request
     * @return mixed
     */
    public function register(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            "cellphone" => ["required", "regex:/^1[0-9]{10}$/", "unique:u_customer,cellphone"],
            "nick_name" => "between:0,20",
            "password" => "required|between:6,20",
            "phoneCode" => "required",
        ], [
            "cellphone.required" => "手机号码不能为空",
            "password.required" => "密码不能为空",
            "cellphone.regex" => "请填写正确的手机号码",
            "cellphone.unique" => "手机号码已经被注册",
            "nick_name.between" => "昵称格式应该为1-20字符",
            "password.between" => "密码长度应为6-20位",
            "phoneCode.required" => "手机验证码不能为空",
        ]);

        if ($validator->fails()) {
            return parent::jsonReturn([], parent::CODE_FAIL, $validator->errors()->first());
        }

        $checkPhoneCode = $this->sms->checkVerify($request->get("cellphone"), $request->get("phoneCode"));
        if (!$checkPhoneCode) {
            return parent::jsonReturn([], parent::CODE_FAIL, "短信验证码错误");
        }

        $data = $request->only(["cellphone", "nick_name", "password", "recCode"]);
        $data = array_merge($data, [
            "reg_source" => 0,
            "reg_ip" => $request->ip(),
        ]);
        $ipInfo = getIpInfo($data["reg_ip"]);
        if ($ipInfo) {
            $data["ip_location"] = $ipInfo["country"] . $ipInfo["region"] . $ipInfo["city"] . $ipInfo["isp"];
        }

        $ret = $this->register->register($data);
        return $ret ? parent::jsonReturn([], parent::CODE_SUCCESS, "注册成功") :
            parent::jsonReturn([], parent::CODE_FAIL, "注册失败");
    }

    /**
     * 找回密码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBackPassword(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            "cellphone" => ["required", "regex:/^1[0-9]{10}$/"],
            "password" => "required|between:6,20",
            "phoneCode" => "required",
        ], [
            "cellphone.required" => "手机号码不能为空",
            "password.required" => "密码不能为空",
            "cellphone.regex" => "请填写正确的手机号码",
            "password.between" => "密码长度应为6-20位",
            "phoneCode.required" => "手机验证码不能为空",
        ]);

        if ($validator->fails()) {
            return parent::jsonReturn([], parent::CODE_FAIL, $validator->errors()->first());
        }

        $checkPhoneCode = $this->sms->checkVerify($request->get("cellphone"), $request->get("phoneCode"));
        if (!$checkPhoneCode) {
            return parent::jsonReturn([], parent::CODE_FAIL, "短信验证码错误");
        }

        $ret = $this->register->getBackPassword($request->get("cellphone"), $request->get("password"));
        return $ret ? parent::jsonReturn([], parent::CODE_SUCCESS, "提交成功") :
            parent::jsonReturn([], parent::CODE_FAIL, "提交失败");
    }

    /**
     * 发送短信（注册）
     * @param Request $request
     * @param bool $isCheckUnique
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendSms(Request $request, $isCheckUnique = true)
    {
        $validates = ["cellphone" => ["required", "regex:/^1[0-9]{10}$/"]];
        $contents = ["cellphone.required" => "手机号不能为空", "cellphone.regex" => "请填写正确的手机号码",];
        if ($isCheckUnique) {
            $validates["cellphone"][] = "unique:u_customer,cellphone";
            $contents["cellphone.unique"] = "该手机号已被注册";
        }
        $validator = \Validator::make($request->all(), $validates, $contents);

        if ($validator->fails()) {
            return parent::jsonReturn([], parent::CODE_FAIL, $validator->errors()->first());
        }

        $agentInfo = getAgent();
        $ret = $this->sms->sendVerify($request->get("cellphone"), $agentInfo, "注册验证码、找回密码验证码");
        return $ret ? parent::jsonReturn([], parent::CODE_SUCCESS, "发送成功") :
            parent::jsonReturn([], parent::CODE_FAIL, $this->sms->getErrorMsg() ?: "发送错误");
    }

    /**
     * 发送短信（找回密码）
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendGetBackSms(Request $request)
    {
        $user = User::where("cellphone", $request->get("cellphone"))->first();
        if (!$user) return parent::jsonReturn([], parent::CODE_FAIL, "该手机号用户不存在");

        return $this->sendSms($request, false);
    }
}