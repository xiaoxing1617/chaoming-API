<?php
declare (strict_types = 1);

namespace app;

use think\App;
use think\exception\ValidateException;
use think\Validate;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    //学生
    const USER_KEY_PREFIX = "__USER__";
    const USER_TOKEN_KEY_PREFIX = "__TOKEN__";
    //配置
    const CONF_KEY_PREFIX = "__CONF__";
    protected $redis;
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    protected $user;

    /**
     * 验证登录的路由
     * @var string[]
     */
    protected $login_check_routes = [
        "Index/taskAnswer",  //查看我的考试列表
        "Index/getMeTestList",  //查看我的考试列表
        "Index/viewTestAnswer",  //查看试卷答案
        "User/getUserInfo",  //获取登录信息
        "Index/getClassList",  //获取课程列表
        "Index/getClassDetails",  //获取课程详情
        "Index/brushClass",  //刷课
        "Index/brushTask",  //刷作业
    ];

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;

        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {
        // 允许所有来源访问
        header('Access-Control-Allow-Origin: *');
        // 允许特定的HTTP方法（GET、POST、PUT、DELETE等）
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
        // 允许的请求头
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
        // 设置为true表示可以发送跨域凭据（例如，使用Cookies）
        header('Access-Control-Allow-Credentials: true');
        $this->redis = cache();

        $str = $this->request->controller()."/".$this->request->action();
        if(in_array($str,$this->login_check_routes)){
            $token = $this->request->param("token","");  //token
            $user_id = $this->redis->get($this::USER_TOKEN_KEY_PREFIX.$token);
            if(!$user_id || empty($user_id)){
                exit($this->error("token不存在或已过期"));
            }
            $user = $this->redis->get($this::USER_KEY_PREFIX.$user_id);
            if(!$user || empty($user)){
                exit($this->error("token对应的用户ID不存在或已被删除"));
            }
            $this->user = json_decode($user,true);
        }
    }

    protected function success($msg="成功",$data=[],$code=1){
        return json_encode([
            "msg"=>$msg,
            "code"=>$code,
            "data"=>$data
        ]);
    }
    protected function error($msg="失败",$data=[],$code=0){
        return json_encode([
            "msg"=>$msg,
            "code"=>$code,
            "data"=>$data
        ]);
    }

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v     = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }


    /**
     * 登录
     * @param string $type num/account
     * @param string $account 账号/学号
     * @param string $password 密码
     * @param string $school_host 学校域名
     */
    protected function loginFun($type="num",$account="",$password='',$school_host="",&$data){
        $get_data = [
            "device"=>"app",
            "password"=>$password,
            "school_host"=>$school_host,
            "type"=>$type
        ];
        if($type=="num"){
            $get_data['num'] = $account;
        }else{
            $get_data['account'] = $account;
        }
        $response = $this->curl_get("https://api.jinkex.com/edu/v1/student/login",$get_data);

        // 处理响应
        if ($response !== false) {
            // 这里可以对响应进行处理，例如解析 JSON 数据
            $parsed_response = json_decode($response, true);
            if ($parsed_response !== null) {
                if(intval($parsed_response['code']) === 0){
                    $this->user = $parsed_response['data'];
                    $this->user['school_host'] = "";
                    $this->user['school_id'] = 0;
                    $this->user['password'] = $password;

                    $school_list = $this->redis->get($this::CONF_KEY_PREFIX . "school_list");
                    if($school_list && !empty($school_list) && $school_list_arr = json_decode($school_list,true)){
                        foreach ($school_list_arr as $item){
                            if($item['domain'] == $school_host){
                                $this->user['school_id'] = $item['id'];
                                $this->user['school_host'] = $item['domain'];
                            }
                        }
                    }
                    $this->redis->tag($this::USER_KEY_PREFIX)->set($this::USER_KEY_PREFIX.$this->user['id'],json_encode($this->user));
                    $data = $this->user;
                    return true;
                }else{
                    $data = $parsed_response['msg'];
                    return false;
                }
            }else{
                $data = "朝明在线登录接口返回异常";
                return false;
            }
        }else{
            $data = "朝明在线登录接口请求异常";
            return false;
        }
    }
    /**
     * 发送请求
     * url：请求地址
     * post：POST数据
     * referer：伪造来源地址
     * cookie：cookie值
     * header：请求头
     * ua：伪造请求浏览器
     * nobaody：请求体
     * addheader：内置请求头 和 自义定请求头 是否合并
     *
     * @return
     */
    function curl_get($url, $post = 0, $referer = 0, $cookie = 0, $header = 0, $ua = 0, $nobaody = 0, $addheader = 0)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $httpheader[] = "Accept: */*";
        $httpheader[] = "Accept-Encoding: gzip,deflate,sdch";
        $httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
        $httpheader[] = "Connection: close";
        if ($addheader) {
            $httpheader = array_merge($httpheader, $addheader);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        if ($header) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }
        if ($cookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if ($referer) {
            if ($referer == 1) {
                curl_setopt($ch, CURLOPT_REFERER, 'http://pay.96xy.cn/');
            } else {
                curl_setopt($ch, CURLOPT_REFERER, $referer);
            }
        }
        if ($ua) {
            curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        } else {
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; U; Android 4.0.4; es-mx; HTC_One_X Build/IMM76D) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0");
        }
        if ($nobaody) {
            curl_setopt($ch, CURLOPT_NOBODY, 1);
        }
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }

}
