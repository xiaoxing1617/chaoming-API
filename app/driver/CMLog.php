<?php


namespace app\driver;
use app\model\log;
use think\facade\Log as LogFacade;
use think\contract\LogHandlerInterface;

class CMLog implements LogHandlerInterface
{
    /**
     * 构造方法
     */
    public function __construct()
    {
        // 获取日志通道配置信息
        $this->config = LogFacade::getChannelConfig('CMLog');
        $this->log = new Log();
    }
    /**
     * 保存日志
     * @param array $data
     */
    public function save(array $data = []): bool
    {
        foreach ($data as $type => $item ) {
            foreach ( $item as $value ) {
                // 检查静态方法
                if (method_exists($this, $type)) {
                    $this->$type($value);
                }
            }
        }
        return true;
    }
    /**
     * 获取时间
     * @return string
     */
    private function getDate(){
        return date("Y-m-d H:i:s");
    }

    /**
     * 用户登录日志
     * @param $data
     */
    private function user_login($data){
        $this->log->insert([
            "type"=>"user_login",
            "user_id"=>$data['user_id'],
            "data1"=>$data['openid'],//openid（必须会有）
            "data2"=>json_encode($data['saveSystemInfo']),//系统信息
            "data3"=>json_encode($data['locationInfo']),//位置信息
            "add_time"=>$data['add_time'],
        ]);
    }
    /**
     * 用户注册日志
     * @param $data
     */
    private function user_register($data){
        $this->log->insert([
            "type"=>"user_register",
            "user_id"=>$data['user_id'],
            "add_time"=>$data['add_time'],
        ]);
    }
    /**
     * 操作刷作业日志
     * @param $data
     */
    private function brush_task($data){
        $this->log->insert([
            "type"=>"brush_task",
            "user_id"=>$data['user_id'],
            "data1"=>$data['course_id'],//学期ID
            "data2"=>$data['open_id'],//课程ID
            "data3"=>$data['id'],//作业ID
            "add_time"=>$this->getDate(),
        ]);
    }
    /**
     * 操作刷课时日志
     * @param $data
     */
    private function brush_class($data){
        $this->log->insert([
            "type"=>"brush_class",
            "user_id"=>$data['user_id'],
            "data1"=>$data['course_id'],//学期ID
            "data2"=>$data['open_id'],//课程ID
            "data3"=>$data['id'],//课时ID
            "add_time"=>$this->getDate(),
        ]);
    }

    /**
     * 操作刷课时日志
     * @param $data
     */
    private function access_applet($data){
        $this->log->insert([
            "type"=>"access_applet",
            "user_id"=>$data['user_id'],
            "data1"=>$data['openid'],//openid（必须会有）
            "data2"=>json_encode($data['saveSystemInfo']),//系统信息
            "data3"=>json_encode($data['locationInfo']),//位置信息
            "add_time"=>$this->getDate(),
        ]);
    }
}
