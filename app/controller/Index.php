<?php

namespace app\controller;

use app\BaseController;
use app\common\WxApplet;
use app\Request;

class Index extends BaseController
{
    public function index()
    {
        return '<style type="text/css">*{ padding: 0; margin: 0; } div{ padding: 4px 48px;} a{color:#2E5CD5;cursor: pointer;text-decoration: none} a:hover{text-decoration:underline; } body{ background: #fff; font-family: "Century Gothic","Microsoft yahei"; color: #333;font-size:18px;} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.6em; font-size: 42px }</style><div style="padding: 24px 48px;"> <h1>:) </h1><p> ThinkPHP V' . \think\facade\App::version() . '<br/><span style="font-size:30px;">16载初心不改 - 你值得信赖的PHP框架</span></p><span style="font-size:25px;">[ V6.0 版本由 <a href="https://www.yisu.com/" target="yisu">亿速云</a> 独家赞助发布 ]</span></div><script type="text/javascript" src="https://e.topthink.com/Public/static/client.js"></script><think id="ee9b1aa918103c4fc"></think>';
    }

    public function files(){
        $path = $this->request->get("path");
        $url = "https://api.jinkex.com/files?path=".$path;
        header("HTTP/1.1 302 Found");
        header("Location: ".$url);
        exit();
    }
    /**
    * 获取广告数据
    */
    public function getAd(){
    	$data = [
    	    "swiperList"=>[
    	        [
    	            "image"=>"https://chaomingfuzhu.oss-cn-shanghai.aliyuncs.com/first.png",
    	        ],
    	        [
    	            "image"=>"https://chaomingfuzhu.oss-cn-shanghai.aliyuncs.com/ad.png",
    	            "weburl"=>"https://chaoming.96xy.cn/ad.html",
    	        ],
    	    ],
    	    "brushPopupAd"=>[
    	        "image"=>"https://chaomingfuzhu.oss-cn-shanghai.aliyuncs.com/xin1.png",
    	         "weburl"=>"https://chaoming.96xy.cn/xin.html",
    	    ],
    	    "loginPopupAd"=>[
    	        "image"=>"https://chaomingfuzhu.oss-cn-shanghai.aliyuncs.com/xin1.png",
    	         "weburl"=>"https://chaoming.96xy.cn/xin.html",
    	    ],
    	];
    	return $this->success("成功",$data);
    }

    /**
     * 访问程序
     */
    public function access(){
        $loginCode = $this->request->param("loginCode");
        $systemInfo = $this->request->param("systemInfo",[]);
        $locationInfo = $this->request->param("locationInfo",[]);

        $WxApplet = new WxApplet();
        $auth = $WxApplet->codeAuth($loginCode);
        if(isset($auth['openid'])){
            $openid = $auth['openid'];

            $user_id = null;
            if($this->user){
                $user_id = $this->user['id'];
            }
            $data = [
                "user_id"=>$user_id,
                "openid"=>$openid,
                "saveSystemInfo"=>$WxApplet->extractSaveSystemInfo($systemInfo),
                "locationInfo"=>$WxApplet->extractSaveLocationInfo($locationInfo),
            ];
            CMLog("access_applet",$data);

            $data['openid'] = maskString($openid,4,20);  //openid马赛克处理
            return $this->success("成功",$data);
        }else{
            //没有openid
            return $this->error("非正常渠道打开程序");
        }

    }
     /**
     * 获取指定课程作业id的答案
     */
     public function taskAnswer(){
        $course_id = intval($this->request->param("course_id",0));
        $open_id = intval($this->request->param("open_id",0));
        $id = intval($this->request->param("id",0));

        if(empty($course_id) || empty($open_id) || empty($id)){
            return $this->error("请正确传值");
        }
        $token = $this->user['token'];
        $school_host = $this->user['school_host'];


        //初次获取答案
        if($this->getTaskAnswer($course_id,$open_id,$id,$token,$school_host,$is_not_submit,$msg,$answer)){
            return $this->success($answer);
        }else{
            return $this->error($answer);
        }
      }

    /**
     * 获取“我的考试”
     */
    public function getMeTestList(){
        $token = $this->user['token'];
        $school_host = $this->user['school_host'];
        $api = "https://api.jinkex.com/exam/v1/user/test?token={$token}&school_host={$school_host}";

        $response = $this->curl_get($api);
        if ($response !== false) {
            if ($parsed_response = json_decode($response, true)) {
                if (isset($parsed_response['code']) && intval($parsed_response['code']) === 0) {
                    $list = [];
                    $map = config("testIdMapList");
                    foreach ($parsed_response['data'] as $item){
                        //获取对应课程ID信息
                        $testId = $item['test_id'];
                        $orderIndex = $item['test_order'];

                        $get_score = 0;
                        $status = "not";  //未考试
                        $error_title = "";
                        $error_directions = "";
                        if(isset($item['proInfo'])) {
                            $status = "proceed";  //正在考试中
                            if(!empty($item['proInfo']['end_time'])){
                                $get_score = $item['proInfo']['get_score'];  //得分
                                $status = "finish";  //已结束
                            }
                        }else{
                            if($item['status'] == 1){
                                $status = "wait";  //待考试
                            }
                        }


                        //课程所有作业
                        if(isset($map[$testId][$orderIndex]) && $status != "finish"){
                            //支持解密的课程 && 考试未结束
                            $course_id = $map[$testId][$orderIndex]['course_id'];
                            $open_id = $map[$testId][$orderIndex]['open_id'];
                            $answer_list = $this->getTaskAnswerList($course_id, $open_id, $token, $school_host);
                            if(!is_array($answer_list)){
                                $status = "error";  //异常
                                $error_title = "异常，有作业未完成";
                                $error_directions = $answer_list;
                            }
                        }

                        $list[] = [
                            "id"=>$testId,
                            "order"=>$orderIndex,
                            "title"=>$item['testInfo']['name'],  //考试名称
                            "open_start"=>$item['testInfo']['open_start'],  //考试开始时间
                            "open_end"=>$item['testInfo']['open_end'],  //考试结束时间
                            "time_length"=>$item['testInfo']['time_length'],  //考试限时（分钟）
                            "total_score"=>$item['testInfo']['total_score'],  //考试总分
                            "is_support"=>isset($map[$testId][$orderIndex]),  //是否支持解密
                            "get_score"=>$get_score,
                            "status"=>$status,
                            "error_directions"=>$error_directions,
                            "error_title"=>$error_title,
                        ];
                    }
                    return $this->success("成功",$list);
                } else {
                    return $this->error(isset($parsed_response['msg']) ? $parsed_response['msg'] : '未知错误');
                }
            } else {
                return $this->error("返回数据解析失败");
            }
        } else {
            return $this->error("请求失败");
        }
    }
    /**
     * 查看试卷答案
     * @return string
     */
    public function viewTestAnswer()
    {
    	
        if(!$this->user['is_use']){
        	return $this->error("请联系程序开发者进行赞助后可使用！开发者微信号：_xiaoxing1617");
        }
        //申请开始考试：https://api.jinkex.com/exam/v1/user/enjoin
        //{"school_host":"","token":"","testId":""}

        $testId = intval($this->request->param("test_id", 0));   //考试ID
        $orderIndex = intval($this->request->param("index", 0));  //试卷索引
        $map = config("testIdMapList");

        $token = $this->user['token'];
        $school_host = $this->user['school_host'];

        //获取对应课程ID信息
        if (isset($map[$testId][$orderIndex])) {
            $course_id = $map[$testId][$orderIndex]['course_id'];
            $open_id = $map[$testId][$orderIndex]['open_id'];
        } else {
            return $this->error("该试卷暂不支持解密，请联系网站管理更新");
        }

        //课程所有作业
        $answer_list = $this->getTaskAnswerList($course_id, $open_id, $token, $school_host);
        if(!is_array($answer_list)){
            return $this->error($answer_list);
        }

        $api = "https://api.jinkex.com/exam/v1/user/testinfo?token={$token}&school_host={$school_host}&testId={$testId}&orderIndex={$orderIndex}";

        $response = $this->curl_get($api);
        if ($response !== false) {
            if ($parsed_response = json_decode($response, true)) {
                if (isset($parsed_response['code']) && intval($parsed_response['code']) === 0) {
                    $data = [];
                    foreach ($parsed_response['data']['praxiseData'] as $arr) {
                        foreach ($arr['data'] as $item) {
                            $data[md5($item['title'])] = array_merge($item, ["type" => $arr['type']]);
                        }
                    }
                    if (count($data) > count($answer_list)) {
                        //题数 大于 答案数
                        return $this->error("获取到的答案对应题数大于试卷提数：" . count($data) . "/" . count($answer_list));
                    } else {
                        $list = [];
                        foreach ($data as $md5 => $item) {
                            if (isset($answer_list[$md5])) {
                                $answer = "";
                                $op = "";
                                if ($item['type'] === "charge") {
                                    //判断题
                                    $answer = intval($answer_list[$md5]['value']) === 1 ? "正确" : "错误";
                                    $op = $answer;
                                } elseif ($item['type'] === "single") {
                                    //单选题
                                    $options = json_decode($item['options'], true);
                                    $op = $this->getOption($answer_list[$md5]['value']);
                                    $answer = $options[intval($answer_list[$md5]['value']) - 1];
                                } elseif ($item['type'] === "muti") {
                                    //多选题
                                    $options = json_decode($item['options'], true);
                                    $answers = $answer_list[$md5]['value'];
                                    $answers = ltrim($answers, "[");
                                    $answers = rtrim($answers, "]");
                                    $answers = explode(",", $answers);

                                    $answer = "";
                                    foreach ($answers as $i) {
                                        $op .= $this->getOption($i);
                                        $answer .= "【" . $options[$i - 1] . "】";
                                    }
                                }
                                $list[] = [
                                    "title" => $item['title'],
//                                    "type" => $item['type'],
//                                    "value" => $answer_list[$md5]['value'],
//                                    "answer" => $answer,
                                    "op" => $op,
                                ];
                            } else {
                                $list[] = [
                                    "title" => $item['title'],
//                                    "type" => $item['type'],
//                                    "value" => "",
//                                    "answer" => "",
                                    "op" => "【解密失败，无答案】",
                                ];
                            }
                        }
                        CMLog("view_test_answer",[
                            "user_id"=>$this->user['id'],  //user表ID
                            "testId"=>$testId,  //考试ID
                            "orderIndex"=>$orderIndex,  //试卷索引ID
                        ]);
                        return $this->success("成功", $list);
                    }
                } else {
                    return $this->error(isset($parsed_response['msg']) ? $parsed_response['msg'] : '未知错误');
                }
            } else {
                return $this->error("返回数据解析失败");
            }
        } else {
            return $this->error("请求失败");
        }
    }

    /**
     * 获取指定课程下所有作业的答案集合列表
     * @param $course_id
     * @param $open_id
     * @param $token
     * @param $school_host
     * @return array|string
     */
    protected function getTaskAnswerList($course_id, $open_id, $token, $school_host)
    {
        $answer_list = [];
        $res = $this->getClassDetailsRequest($course_id, $open_id, $token, $school_host, $dataClassList);
        if ($res) {
            foreach ($dataClassList['chapter'] as $item) {
                $children = $item['children'];
                foreach ($children as $arr) {
                    if (intval($arr['praxise_count']) != 0) {
                        //如果是资料文件的话，praxise_count是0
                        if (isset($arr['videoid']) && empty($arr['videoid'])) {
                            //获取答案
                            if ($this->getTaskAnswer($course_id, $open_id, $arr['id'], $token, $school_host, $is_not_submit, $msg, $answers)) {
                                //没有报错
                                if ($is_not_submit) {
                                    //没有提交过作业
                                    return "该试卷对应课程中有未完成的作业：" . $arr['name']."。请先及时刷课！";
                                } else {
                                    foreach ($answers as $answer) {
                                        $answer_list[md5($answer['title'])] = $answer;
                                    }
                                }
                            }

                        }
                    }
                }
            }
            return $answer_list;
        } else if ($dataClassList === "CODE_403") {
            return "请重新登录";
        } else {
            return $dataClassList;
        }
    }


    protected function getClassDetailsRequest($course_id, $open_id, $token, $school_host, &$data)
    {
        $url = "https://api.jinkex.com/edu/v1/classroom/{$course_id}/{$open_id}?token={$token}&school_host={$school_host}";
        $response = $this->curl_get($url);
        // 处理响应
        if ($response !== false) {
            $parsed_response = json_decode($response, true);
            if ($parsed_response !== null) {
                if (intval($parsed_response['code']) === 0) {
                    $data = $parsed_response['data'];
                    return true;
                } else if (intval($parsed_response['code']) === 403) {
                    //朝明在线的token失效，尝试重新获取
                    $data = "CODE_403";
                    return false;
                } else {
                    $data = isset($parsed_response['msg'])?$parsed_response['msg']:'接口没有错误信息';
                    return false;
                }
            } else {
            	 if($response == "404 Not Found!"){
            	 	$data = "课程暂未开放或不存在！";
            	 }else{
            	 	$data = "朝明在线接口返回异常:".$response;
            	 }
                return false;
            }
        } else {
            $data = "朝明在线接口请求异常";
            return false;
        }
    }

    /**
     * 获取作业答案
     */
    protected function getTaskAnswer($course_id, $open_id, $id, $token, $school_host, &$is_not_submit, &$msg, &$submit = [])
    {
        $apiUrl = 'https://api.jinkex.com/edu/v1/classroom/' . $course_id . '/' . $open_id . "/" . $id;
        $device = 'wx';

        $url = $apiUrl . '?token=' . $token . '&school_host=' . $school_host . '&device=' . $device;


        // 发起 GET 请求
        $response = file_get_contents($url);

        // 检查响应是否成功
        if ($response !== false) {
            // 处理响应数据
            $data = json_decode($response, true);
            if ($data !== null) {
                // 响应数据解析成功
                // 检查响应是否正确
                if ($data['code'] === 0) {
                    $answer = [];
                    $answer['list_str'] = "";
                    $answer['list'] = [];
                    foreach ($data['data']['praxise']['praxiseList'] as $item) {
                        $type = $item['type'];
                        switch ($type) {
                            case "single":
                                //单选题
                                $is_get = true;
                                if (!isset($item['answer'])) {
                                    //没有答案
                                    $is_get = false;
                                    $choose_str = "【无】";
                                    $submit[] = [
                                        "title" => $item['title'],
                                        "praxiseId" => $item['id'],
                                        "value" => 1,
                                    ];
                                } else {
                                    $id = intval($item['answer']);
                                    $choose_str = $this->getOption($id);
                                    $submit[] = [
                                        "title" => $item['title'],
                                        "praxiseId" => $item['id'],
                                        "value" => $id,
                                    ];
                                }
                                $answer['list'][] = [
                                    "type" => $type,
                                    "value" => $id,
                                    "choose_str" => $choose_str,
                                    "is_get" => $is_get,
                                ];
                                $answer['list_str'] .= $choose_str . "、";
                                break;
                            case "charge":
                                //判断题
                                $is = isset($item['answer']) ? intval($item['answer']) : false;
                                $is_get = true;
                                if ($is === 1) {
                                    $is_str = "对";
                                    $submit[] = [
                                        "title" => $item['title'],
                                        "praxiseId" => $item['id'],
                                        "value" => 1,
                                    ];
                                } else if ($is === -1) {
                                    $is_str = "错";
                                    $submit[] = [
                                        "title" => $item['title'],
                                        "praxiseId" => $item['id'],
                                        "value" => -1,
                                    ];
                                } else {
                                    $is_get = false;
                                    $is_str = "【无】";
                                    $submit[] = [
                                        "title" => $item['title'],
                                        "praxiseId" => $item['id'],
                                        "value" => 1,
                                    ];
                                }
                                $answer['list'][] = [
                                    "type" => $type,
                                    "value" => $is,
                                    "choose_str" => $is_str,
                                    "is_get" => $is_get,
                                ];
                                $answer['list_str'] .= $is_str . "、";
                                break;
                            case "muti":
                                //多选题
                                if (!isset($item['answer']) || empty($item['answer'])) {
                                    $is_get = false;
                                    $choose_str = "【无】";
                                    $answers = "[1]";
                                    $submit[] = [
                                        "title" => $item['title'],
                                        "praxiseId" => $item['id'],
                                        "value" => $answers,
                                    ];
                                } else {
                                    $answers = $item['answer'];
                                    $answers = ltrim($answers, "[");
                                    $answers = rtrim($answers, "]");
                                    $answers = explode(",", $answers);

                                    $tmp_choose_str = "";
                                    foreach ($answers as $i) {
                                        $tmp_choose_str .= $this->getOption($i);
                                    }
                                    $choose_str = $tmp_choose_str;
                                    $is_get = true;
                                    $answers = $item['answer'];
                                    $submit[] = [
                                        "title" => $item['title'],
                                        "praxiseId" => $item['id'],
                                        "value" => $answers,
                                    ];
                                }
                                $answer['list'][] = [
                                    "type" => $type,
                                    "value" => $answers,
                                    "choose_str" => $choose_str,
                                    "is_get" => $is_get,
                                ];
                                $answer['list_str'] .= $choose_str . "、";
                                break;
                            default:
                                //未知的题型
                                $answer['list'][] = [
                                    "type" => $type,
                                ];
                                $answer['list_str'] .= "【未知题型】、";
                                $submit = [];
                                $msg = "含有未知的题型，暂时无法刷作业，请联系开发者解决！！！";
                                return false;
                        }
                    }
                    $answer['list_str'] = rtrim($answer['list_str'], "、");
                    if (intval($data['data']['chapter']['praxise_submit_count']) <= 0) {
                        //没有提交过
                        $is_not_submit = true;
                    }
                    return $answer;
                } else {
                    // 响应错误
                    $msg = $data['msg'];
                    return false;
                }
            } else {
                // 响应数据解析失败
                $msg = "朝明在线接口响应数据解析失败";
                return false;
            }
        } else {
            // 请求失败
            $msg = "朝明在线接口请求失败";
            return false;
        }
    }

    /**
     * 选项转换
     */
    protected function getOption($num)
    {
        if ($num <= 26) {
            return chr($num + 64); // A is ASCII 65, so we add 64 to the number
        } else {
            return $num; // Return the number directly if it exceeds 26
        }
    }

    /**
     * 刷作业
     */
    public function brushTask()
    {
    	   if(!$this->user['is_use']){
        	return $this->error("请联系程序开发者进行赞助后可使用！开发者微信号：_xiaoxing1617");
        }
        $course_id = intval($this->request->param("course_id", 0));
        $open_id = intval($this->request->param("open_id", 0));
        $id = intval($this->request->param("id", 0));

        if (empty($course_id) || empty($open_id) || empty($id)) {
            return $this->error("请正确传值");
        }
        $token = $this->user['token'];
        $school_host = $this->user['school_host'];


        //初次获取答案
        if ($this->getTaskAnswer($course_id, $open_id, $id, $token, $school_host, $is_not_submit, $msg, $answer)) {
            //没有报错
            if ($is_not_submit) {
                //没有提交过作业

                if ($this->reportWatchTaskAnswer($course_id, $open_id, $id, $token, $school_host, $answer, $data)) {
                    if ($arr = json_decode($data, 1)) {
                        if (isset($arr['code']) && $arr['code'] == 0) {
                            if (!$this->getTaskAnswer($course_id, $open_id, $id, $token, $school_host, $is_not_submit, $msg, $answer)) {
                                return $this->error($msg);
                            }
                            if ($this->reportWatchTaskAnswer($course_id, $open_id, $id, $token, $school_host, $answer, $data)) {
                                if ($arr = json_decode($data, 1)) {
                                    if (isset($arr['code']) && $arr['code'] == 0) {
                                        CMLog("brush_task",[
                                            "user_id"=>$this->user['id'],  //user表ID
                                            "course_id"=>$course_id,  //学期ID
                                            "open_id"=>$open_id,  //学科ID
                                            "id"=>$id,  //作业ID
                                        ]);
                                        return $this->success('刷作业成功');
                                    } else {
                                        return $this->error("[2]" . $arr['msg'] ?: '[2]未知错误');
                                    }
                                } else {
                                    return $this->error("[2]朝明在线接口返回异常");
                                }
                            } else {
                                return $this->error("[2]朝明在线接口请求异常");
                            }
                        } else {
                            return $this->error("[1]" . $arr['msg'] ?: '[1]未知错误');
                        }
                    } else {
                        return $this->error("[1]朝明在线接口返回异常");
                    }
                } else {
                    return $this->error("[1]朝明在线接口请求异常");
                }

            } else {
                return $this->error("该作业您已经做过一次了，无法使用刷作业功能了！");
            }
        } else {
            return $this->error($msg);
        }
    }

    /**
     * @date 2023/05/12
     * 请求上报作业答案
     */
    protected function reportWatchTaskAnswer($course_id, $open_id, $id, $token, $school_host, $answer = [], &$msg)
    {
        $api = "https://api.jinkex.com/edu/v1/classroom/" . $course_id . "/" . $open_id . "/submit/" . $id;

        $curl = curl_init();


        $data = [
            "school_host" => $school_host,
            "token" => $token,
            "items" => json_encode($answer)
        ];
        curl_setopt_array($curl, array(
            CURLOPT_URL => $api,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Origin: https://pt.jinkex.com',
                'Host: api.jinkex.com',
                'Referer: https://pt.jinkex.com/',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36',
                'Accept: application/json, text/plain, */*',
                'Accept-Language: zh-CN,zh;q=0.9',
                'Authorization: ' . $school_host,
                'Content-Type: application/json'
            ),
            CURLOPT_SSL_VERIFYPEER => false
        ));

        $response = curl_exec($curl);
        // 检查是否有错误发生
        if ($response === false) {
            // 获取错误信息
            $error = curl_error($curl);
            curl_close($curl);
            $msg = $error;
            return false;
        }
        $msg = $response;
        return true;
    }

    /**
     * 刷课时
     */
    public function brushClass()
    {
    	   if(!$this->user['is_use']){
        	return $this->error("请联系程序开发者进行赞助后可使用！开发者微信号：_xiaoxing1617");
        }
        $course_id = intval($this->request->param("course_id", 0));
        $open_id = intval($this->request->param("open_id", 0));
        $id = intval($this->request->param("id", 0));
        $video_time = intval($this->request->param("video_time", 0));

        if (empty($course_id) || empty($open_id) || empty($id)) {
            return $this->error("请正确传值");
        }
        $token = $this->user['token'];
        $school_host = $this->user['school_host'];

        if ($this->reportWatchLesson($course_id, $open_id, $id, $token, $school_host, $video_time, $data)) {
            if ($arr = json_decode($data, 1)) {
                if (isset($arr['code']) && $arr['code'] == 0) {
                    CMLog("brush_class",[
                        "user_id"=>$this->user['id'],  //user表ID
                        "course_id"=>$course_id,  //学期ID
                        "open_id"=>$open_id,  //学科ID
                        "id"=>$id,  //课时ID
                    ]);
                    return $this->success('刷课成功');
                } else {
                    return $this->error($arr['msg'] ?: '未知错误');
                }
            } else {
                return $this->error("朝明在线接口返回异常");
            }
        } else {
            return $this->error("朝明在线接口请求异常");
        }
    }

    //获取课程列表

    /**
     * @date 2023/05/12
     * 请求上报课时
     */
    protected function reportWatchLesson($course_id, $open_id, $id, $token, $school_host, $time, &$data)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.jinkex.com/edu/v1/classroom/' . $course_id . '/' . $open_id . '/rec/' . $id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
        "school_host": "' . $school_host . '",
        "token": "' . $token . '",
        "lastTime": ' . $time . ',
        "hasTime": ' . ($time + rand(0, 15)) . '
        }',
            CURLOPT_HTTPHEADER => array(
                'Origin: https://pt.jinkex.com',
                'Host: api.jinkex.com',
                'Referer: https://pt.jinkex.com/',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36',
                'Accept: application/json, text/plain, */*',
                'Accept-Language: zh-CN,zh;q=0.9',
                'Authorization: ' . $school_host,
                'Content-Type: application/json'
            ),
            CURLOPT_SSL_VERIFYPEER => false
        ));

        $response = curl_exec($curl);
        // 检查是否有错误发生
        if ($response === false) {
            // 获取错误信息
            $error = curl_error($curl);
            curl_close($curl);
            $data = $error;
            return false;
        }
        $data = $response;
        return true;
    }

    //获取学校列表

    /**
     * 获取学校列表
     */
    public function getSchoolList(Request $request)
    {
        $key = $this::CONF_KEY_PREFIX . "school_list";
        $school_list = $this->redis->get($key);
        $is_use_cache = intval($request->param("is_use_cache", 1));  //是否使用缓存
        if ($is_use_cache && $school_list && !empty($school_list) && $school_list_arr = json_decode($school_list, true)) {
            return $this->success("(缓存)获取成功", $school_list_arr);
        }

        $res = $this->getSchoolListRequest(100, $data);
        if ($res) {
            $school_list_arr = [];
            foreach ($data as $item) {
                $school_list_arr[] = [
                    "id" => $item['id'],
                    "as" => $item['num'],
                    "name" => $item['name'],
                    "domain" => $item['domain'],
                    "initials" => $item['initials'],
                    "studentCount" => $item['studentCount'],
                ];
            }
            $s = 60 * 60 * 24 * 2;  //至少每3天重新获取一次学校列表数据（60秒 * 60分钟 * 24小时 * 3天）
            $this->redis->tag($this::CONF_KEY_PREFIX)->set($key, json_encode($school_list_arr), $s);
            return $this->success("获取成功", $school_list_arr);
        } else {
            return $this->error($data);
        }
    }

    protected function getSchoolListRequest($limit = 100, &$data = [])
    {
        $post_url = "https://api.jinkex.com/sadmin/v1/school?page=1&limit={$limit}";
        $response = $this->curl_get($post_url);

        // 处理响应
        if ($response !== false) {
            // 这里可以对响应进行处理，例如解析 JSON 数据
            $parsed_response = json_decode($response, true);
            if ($parsed_response !== null) {
                if (intval($parsed_response['code']) !== 0) {
                    $data = $parsed_response['msg'];
                    return false;
                }
                if (intval($parsed_response['count']) > $limit) {
                    return $this->getSchoolListRequest($limit + 30, $data);
                } else {
                    $data = $parsed_response['data'];
                    return true;
                }
            } else {
                $data = "朝明在线接口返回异常";
                return false;
            }
        } else {
            $data = "朝明在线接口请求异常";
            return false;
        }
    }

    /**
     * 获取课程列表
     */
    public function getClassList()
    {
        $token = $this->user['token'];
        $school_host = $this->user['school_host'];

        $res = $this->getClassListRequest($token, $school_host, $dataClassList);
        if ($res) {
            return $this->success("获取成功", $dataClassList);
        } else if ($dataClassList === "CODE_403") {
            if (empty($this->user['num'])) {
                $type = "account";
                $account = $this->user['account'];
            } else {
                $type = "num";
                $account = $this->user['num'];
            }
            $password = $this->user['password'];
            $school_host = $this->user['school_host'];
            $res = $this->loginFun($type, $account, $password, $school_host, $data);
            if ($res) {
                $school_host = $data['school_host'];
                $token = $data['token'];
                if ($this->getClassListRequest($token, $school_host, $dataClassListNew)) {
                	
                    return $this->success("获取成功", $dataClassListNew);
                } else {
                    return $this->error("朝明在线账号或密码错误，请重新登录");
                }
            } else {
                return $this->error($data);
            }
        } else {
            return $this->error($dataClassList);
        }
    }

    protected function getClassListRequest($token, $school_host, &$data)
    {
        $url = "https://api.jinkex.com/edu/v1/studentcourse/getstuentcourse?token={$token}&school_host={$school_host}";
        $response = $this->curl_get($url);
        // 处理响应
        if ($response !== false) {
            $parsed_response = json_decode($response, true);
            if ($parsed_response !== null) {
                if (intval($parsed_response['code']) === 0) {
                    $data = $parsed_response['data'];
                    return true;
                } else if (intval($parsed_response['code']) === 403) {
                    //朝明在线的token失效，尝试重新获取
                    $data = "CODE_403";
                    return false;
                } else {
                    $data = $parsed_response['data'];
                    return false;
                }
            } else {
                $data = "朝明在线接口返回异常";
                return false;
            }
        } else {
            $data = "朝明在线接口请求异常";
            return false;
        }
    }

    /**
     * 获取课程详情
     */
    public function getClassDetails()
    {
        $token = $this->user['token'];
        $school_host = $this->user['school_host'];
        $course_id = $this->request->param("course_id", "");  //课时ID
        $open_id = $this->request->param("open_id", "");  //开放ID

        $res = $this->getClassDetailsRequest($course_id, $open_id, $token, $school_host, $dataClassList);
        if ($res) {
            return $this->success("获取成功", $dataClassList);
        } else if ($dataClassList === "CODE_403") {
            if (empty($this->user['num'])) {
                $type = "account";
                $account = $this->user['account'];
            } else {
                $type = "num";
                $account = $this->user['num'];
            }
            $password = $this->user['password'];
            $school_host = $this->user['school_host'];
            $res = $this->loginFun($type, $account, $password, $school_host, $data);
            if ($res) {
                $school_host = $data['school_host'];
                $token = $data['token'];
                if ($this->getClassDetailsRequest($course_id, $open_id, $token, $school_host, $dataClassListNew)) {
                    return $this->success("获取成功", $dataClassListNew);
                } else {
                    return $this->error("朝明在线账号或密码错误，请重新登录");
                }
            } else {
                return $this->error($data);
            }
        } else {
            return $this->error($dataClassList);
        }
    }
}
