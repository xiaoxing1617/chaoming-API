<?php


namespace app\controller;


use app\BaseController;

class User extends BaseController
{
    public function login(){
        $request = $this->request;
        $account = $request->param("account","");  //账号/学号
        $password = $request->param("password","");  //密码
        $school_id = $request->param("school_id",0);  //学校ID
        $type = $request->param("type","num");  //登录方式
        if(empty($account) || empty($password)){
             return $this->error("账号/学号或密码不能为空");
        }
        if($type!="num" && $type!="account"){
            return $this->error("请正确选择登录方式");
        }
        $key = $this::CONF_KEY_PREFIX . "school_list";
        $school_list = $this->redis->get($key);
        $is_use_cache = intval($request->param("is_use_cache",1));  //是否使用缓存
        $school_host = "";
        if($is_use_cache && $school_list && !empty($school_list) && $school_list_arr = json_decode($school_list,true)){
            foreach ($school_list_arr as $item){
                if($item['id'] == $school_id){
                    $school_id = $item['id'];
                    $school_host = $item['domain'];
                }
            }
            if(empty($school_host)){
                return $this->error("请正确选择一个对应的学校");
            }
        }else{
            return $this->error("学校数据列表读取失败");
        }

        $res = $this->loginFun($type,$account,$password,$school_host,$data);
        if($res){
            $token = md5(time() . rand(1000,9999) . rand(1000,9999) . rand(1000,9999));
            $this->redis->tag($this::USER_TOKEN_KEY_PREFIX)->set($this::USER_TOKEN_KEY_PREFIX.$token,$data['id'],60 * 60 * 24);  //1天后过期
            $data['XY_SYSTEM_USER_TOKEN'] = $token;
            $data['password'] = md5($data['password']);
            return $this->success("登录成功",$data);
        }else{
            return $this->error($data);
        }
    }

    /**
     * 获取登录信息
     * @return false|string
     */
    public function getUserInfo(){
        $user = $this->user;
        $user['password'] = md5($this->user['password']);
        return $this->success("登录成功",$user);
    }
}
