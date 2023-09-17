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
        curl_close($curl);
        $data = $response;
        return true;
    }
}
