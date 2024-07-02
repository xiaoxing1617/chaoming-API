<?php
namespace app\controller;

use app\BaseController;
use app\Request;

class Index extends BaseController
{
    public function index()
    {
        return '<style type="text/css">*{ padding: 0; margin: 0; } div{ padding: 4px 48px;} a{color:#2E5CD5;cursor: pointer;text-decoration: none} a:hover{text-decoration:underline; } body{ background: #fff; font-family: "Century Gothic","Microsoft yahei"; color: #333;font-size:18px;} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.6em; font-size: 42px }</style><div style="padding: 24px 48px;"> <h1>:) </h1><p> ThinkPHP V' . \think\facade\App::version() . '<br/><span style="font-size:30px;">16载初心不改 - 你值得信赖的PHP框架</span></p><span style="font-size:25px;">[ V6.0 版本由 <a href="https://www.yisu.com/" target="yisu">亿速云</a> 独家赞助发布 ]</span></div><script type="text/javascript" src="https://e.topthink.com/Public/static/client.js"></script><think id="ee9b1aa918103c4fc"></think>';
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
            return $this->error($msg);
        }else{
            return $this->error($answer);
        }
      }

    /**
     * 刷作业
     */
    public function brushTask(){
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
            //没有报错
            if($is_not_submit){
                //没有提交过作业

                if($this->reportWatchTaskAnswer($course_id,$open_id,$id,$token,$school_host,$answer,$data)){
                    if($arr = json_decode($data,1)){
                        if(isset($arr['code']) && $arr['code'] == 0){
                            if(!$this->getTaskAnswer($course_id,$open_id,$id,$token,$school_host,$is_not_submit,$msg,$answer)){
                                return $this->error($msg);
                            }
                            if($this->reportWatchTaskAnswer($course_id,$open_id,$id,$token,$school_host,$answer,$data)){
                                if($arr = json_decode($data,1)){
                                    if(isset($arr['code']) && $arr['code'] == 0){
                                        return $this->success('刷作业成功');
                                    }else{
                                        return $this->error("[2]".$arr['msg']?:'[2]未知错误');
                                    }
                                }else{
                                    return $this->error("[2]朝明在线接口返回异常");
                                }
                            }else{
                                return $this->error("[2]朝明在线接口请求异常");
                            }
                        }else{
                            return $this->error("[1]".$arr['msg']?:'[1]未知错误');
                        }
                    }else{
                        return $this->error("[1]朝明在线接口返回异常");
                    }
                }else{
                    return $this->error("[1]朝明在线接口请求异常");
                }

            }else{
                return $this->error("该作业您已经做过一次了，无法使用刷作业功能了！");
            }
        }else{
            return $this->error($msg);
        }
    }
    /**
     * 刷课时
     */
    public function brushClass(){
        $course_id = intval($this->request->param("course_id",0));
        $open_id = intval($this->request->param("open_id",0));
        $id = intval($this->request->param("id",0));
        $video_time = intval($this->request->param("video_time",0));

        if(empty($course_id) || empty($open_id) || empty($id)){
            return $this->error("请正确传值");
        }
        $token = $this->user['token'];
        $school_host = $this->user['school_host'];

        if($this->reportWatchLesson($course_id,$open_id,$id,$token,$school_host,$video_time,$data)){
            if($arr = json_decode($data,1)){
                if(isset($arr['code']) && $arr['code'] == 0){
                    return $this->success('刷课成功！');
                }else{
                    return $this->error($arr['msg']?:'未知错误');
                }
            }else{
                return $this->error("朝明在线接口返回异常");
            }
        }else{
            return $this->error("朝明在线接口请求异常");
        }
    }
    /**
     * 获取学校列表
     */
    public function getSchoolList(Request $request){
        $key = $this::CONF_KEY_PREFIX . "school_list";
        $school_list = $this->redis->get($key);
        $is_use_cache = intval($request->param("is_use_cache",1));  //是否使用缓存
        if($is_use_cache && $school_list && !empty($school_list) && $school_list_arr = json_decode($school_list,true)){
            return $this->success("(缓存)获取成功",$school_list_arr);
        }

        $res = $this->getSchoolListRequest(100,$data);
        if($res){
            $school_list_arr = [];
            foreach ($data as $item){
                $school_list_arr[] = [
                    "id"=>$item['id'],
                    "as"=>$item['num'],
                    "name"=>$item['name'],
                    "domain"=>$item['domain'],
                    "initials"=>$item['initials'],
                    "studentCount"=>$item['studentCount'],
                ];
            }
            $s = 60 * 60 * 24 * 2;  //至少每3天重新获取一次学校列表数据（60秒 * 60分钟 * 24小时 * 3天）
            $this->redis->tag($this::CONF_KEY_PREFIX)->set($key,json_encode($school_list_arr),$s);
            return $this->success("获取成功",$school_list_arr);
        }else{
            return $this->error($data);
        }
    }

    /**
     * 获取课程列表
     */
    public function getClassList(){
        $token = $this->user['token'];
        $school_host = $this->user['school_host'];

        $res = $this->getClassListRequest($token,$school_host,$dataClassList);
        if($res){
            return $this->success("获取成功",$dataClassList);
        }else if($dataClassList === "CODE_403") {
            if(empty($this->user['num'])){
                $type = "account";
                $account = $this->user['account'];
            }else{
                $type = "num";
                $account = $this->user['num'];
            }
            $password = $this->user['password'];
            $school_host = $this->user['school_host'];
            $res = $this->loginFun($type,$account,$password,$school_host,$data);
            if($res){
                $school_host = $data['school_host'];
                $token = $data['token'];
                if($this->getClassListRequest($token,$school_host,$dataClassListNew)){
                    return $this->success("获取成功",$dataClassListNew);
                }else{
                    return $this->error("朝明在线账号或密码错误，请重新登录");
                }
            }else{
                return $this->error($data);
            }
        }else{
            return $this->error($dataClassList);
        }
    }

    /**
     * 获取课程详情
     */
    public function getClassDetails(){
        $token = $this->user['token'];
        $school_host = $this->user['school_host'];
        $course_id = $this->request->param("course_id","");  //课时ID
        $open_id = $this->request->param("open_id","");  //开放ID

        $res = $this->getClassDetailsRequest($course_id,$open_id,$token,$school_host,$dataClassList);
        if($res){
            return $this->success("获取成功",$dataClassList);
        }else if($dataClassList === "CODE_403") {
            if(empty($this->user['num'])){
                $type = "account";
                $account = $this->user['account'];
            }else{
                $type = "num";
                $account = $this->user['num'];
            }
            $password = $this->user['password'];
            $school_host = $this->user['school_host'];
            $res = $this->loginFun($type,$account,$password,$school_host,$data);
            if($res){
                $school_host = $data['school_host'];
                $token = $data['token'];
                if($this->getClassDetailsRequest($course_id,$open_id,$token,$school_host,$dataClassListNew)){
                    return $this->success("获取成功",$dataClassListNew);
                }else{
                    return $this->error("朝明在线账号或密码错误，请重新登录");
                }
            }else{
                return $this->error($data);
            }
        }else{
            return $this->error($dataClassList);
        }
    }

    //==========辅助请求方法===========
    //获取课程详情
    protected function getClassDetailsRequest($course_id,$open_id,$token,$school_host,&$data){
        $url = "https://api.jinkex.com/edu/v1/classroom/{$course_id}/{$open_id}?token={$token}&school_host={$school_host}";
        $response = $this->curl_get($url);
        // 处理响应
        if ($response !== false) {
            $parsed_response = json_decode($response, true);
            if ($parsed_response !== null) {
                if(intval($parsed_response['code']) === 0) {
                    $data = $parsed_response['data'];
                    return true;
                }else if(intval($parsed_response['code']) === 403){
                    //朝明在线的token失效，尝试重新获取
                    $data = "CODE_403";
                    return false;
                }else{
                    $data = $parsed_response['data'];
                    return false;
                }
            }else{
                $data = "朝明在线接口返回异常";
                return false;
            }
        }else{
            $data = "朝明在线接口请求异常";
            return false;
        }
    }

    //获取课程列表
    protected function getClassListRequest($token,$school_host,&$data){
        $url = "https://api.jinkex.com/edu/v1/studentcourse/getstuentcourse?token={$token}&school_host={$school_host}";
        $response = $this->curl_get($url);
        // 处理响应
        if ($response !== false) {
            $parsed_response = json_decode($response, true);
            if ($parsed_response !== null) {
                if(intval($parsed_response['code']) === 0) {
                    $data = $parsed_response['data'];
                    return true;
                }else if(intval($parsed_response['code']) === 403){
                    //朝明在线的token失效，尝试重新获取
                    $data = "CODE_403";
                    return false;
                }else{
                    $data = $parsed_response['data'];
                    return false;
                }
            }else{
                $data = "朝明在线接口返回异常";
                return false;
            }
        }else{
            $data = "朝明在线接口请求异常";
            return false;
        }
    }

    //获取学校列表
    protected function getSchoolListRequest($limit=100,&$data=[]){
        $post_url = "https://api.jinkex.com/sadmin/v1/school?page=1&limit={$limit}";
        $response = $this->curl_get($post_url);

        // 处理响应
        if ($response !== false) {
            // 这里可以对响应进行处理，例如解析 JSON 数据
            $parsed_response = json_decode($response, true);
            if ($parsed_response !== null) {
                if(intval($parsed_response['code']) !== 0){
                    $data = $parsed_response['msg'];
                    return false;
                }
                if(intval($parsed_response['count']) > $limit){
                    return $this->getSchoolListRequest($limit + 30,$data);
                }else{
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
     * @date 2023/05/12
     * 请求上报课时
     */
    protected function reportWatchLesson($course_id,$open_id,$id,$token,$school_host,$time,&$data)
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
    /**
     * 获取作业答案
     */
    protected function getTaskAnswer($course_id,$open_id,$id,$token,$school_host,&$is_not_submit,&$msg,&$submit=[])
    {
        $apiUrl = 'https://api.jinkex.com/edu/v1/classroom/' .$course_id. '/' . $open_id."/".$id;
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
                    foreach ($data['data']['praxise']['praxiseList'] as $item){
                        $type = $item['type'];
                        switch ($type){
                            case "single":
                                //单选题
                                $is_get = true;
                                if(!isset($item['answer'])){
                                    //没有答案
                                    $is_get = false;
                                    $choose_str = "【无】";
                                    $submit[] = [
                                        "praxiseId"=>$item['id'],
                                        "value"=>1,
                                    ];
                                }else{
                                    $id = intval($item['answer']);
                                    $choose_str = $this->getOption($id);
                                    $submit[] = [
                                        "praxiseId"=>$item['id'],
                                        "value"=>$id,
                                    ];
                                }
                                $answer['list'][] = [
                                    "type"=>$type,
                                    "value"=>$id,
                                    "choose_str"=>$choose_str,
                                    "is_get"=>$is_get,
                                ];
                                $answer['list_str'] .= $choose_str."、";
                                break;
                            case "charge":
                                //判断题
                                $is = isset($item['answer'])?intval($item['answer']):false;
                                $is_get = true;
                                if($is===1){
                                    $is_str = "对";
                                    $submit[] = [
                                        "praxiseId"=>$item['id'],
                                        "value"=>1,
                                    ];
                                }else if($is===-1){
                                    $is_str = "错";
                                    $submit[] = [
                                        "praxiseId"=>$item['id'],
                                        "value"=>-1,
                                    ];
                                }else{
                                    $is_get = false;
                                    $is_str = "【无】";
                                    $submit[] = [
                                        "praxiseId"=>$item['id'],
                                        "value"=>1,
                                    ];
                                }
                                $answer['list'][] = [
                                    "type"=>$type,
                                    "value"=>$is,
                                    "choose_str"=>$is_str,
                                    "is_get"=>$is_get,
                                ];
                                $answer['list_str'] .= $is_str."、";
                                break;
                            case "muti":
                                //多选题
                                if(!isset($item['answer']) || empty($item['answer'])){
                                    $is_get = false;
                                    $choose_str = "【无】";
                                    $answers = "[1]";
                                    $submit[] = [
                                        "praxiseId"=>$item['id'],
                                        "value"=>$answers,
                                    ];
                                }else{
                                    $answers = $item['answer'];
                                    $answers = ltrim($answers,"[");
                                    $answers = rtrim($answers,"]");
                                    $answers = explode(",",$answers);

                                    $tmp_choose_str = "";
                                    foreach ($answers as $i){
                                        $tmp_choose_str .= $this->getOption($i);
                                    }
                                    $choose_str = $tmp_choose_str;
                                    $is_get = true;
                                    $answers = $item['answer'];
                                    $submit[] = [
                                        "praxiseId"=>$item['id'],
                                        "value"=>$answers,
                                    ];
                                }
                                $answer['list'][] = [
                                    "type"=>$type,
                                    "value"=>$answers,
                                    "choose_str"=>$choose_str,
                                    "is_get"=>$is_get,
                                ];
                                $answer['list_str'] .= $choose_str."、";
                                break;
                            default:
                                //未知的题型
                                $answer['list'][] = [
                                    "type"=>$type,
                                ];
                                $answer['list_str'] .= "【未知题型】、";
                                $submit = [];
                                $msg = "含有未知的题型，暂时无法刷作业，请联系开发者解决！！！";
                                return false;
                        }
                    }
                    $answer['list_str'] = rtrim($answer['list_str'],"、");
                    if(intval($data['data']['chapter']['praxise_submit_count'])<=0){
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
     * @date 2023/05/12
     * 请求上报作业答案
     */
    protected function reportWatchTaskAnswer($course_id,$open_id,$id,$token,$school_host,$answer=[],&$msg){
        $api = "https://api.jinkex.com/edu/v1/classroom/".$course_id."/".$open_id."/submit/".$id;

        $curl = curl_init();


        $data = [
            "school_host" =>$school_host,
            "token"=> $token,
            "items"=>json_encode($answer)
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
     * 选项转换
     */
    protected function getOption($num) {
        if ($num <= 26) {
            return chr($num + 64); // A is ASCII 65, so we add 64 to the number
        } else {
            return $num; // Return the number directly if it exceeds 26
        }
    }
}
