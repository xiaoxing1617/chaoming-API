<?php


namespace app\common;


class WxApplet
{
    /**
     * 要保存的系统信息字段映射
     * @var array
     */
    public $saveSystemInfoMap = [
        "SDKVersion" => "基础库版本号",
        "appVersion" => "程序版本号（manifest.json中设置）",
        "appVersionCode" => "程序版本批次（manifest.json中设置）",
        "batteryLevel" => "剩余电量百分比（仅 iOS 有效）",
        "benchmarkLevel" => "设备性能等级。取值为：-2 或 0（该设备无法运行小游戏），-1（性能未知），>=1（设备性能值，该值越高，设备性能越好，目前最高不到50）",
        "bluetoothEnabled" => "蓝牙的系统开关",
        "deviceBrand" => "设备品牌",
        "deviceModel" => "设备型号（部分设备无法获取）",
        "deviceOrientation" => "设备方向（竖屏 portrait、横屏 landscape）",
        "deviceType" => "设备类型（phone、pad、pc、unknow）",
        "devicePixelRatio" => "设备像素比",
        "cameraAuthorized" => "允许微信使用摄像头的开关",
        "locationAuthorized" => "允许微信使用定位的开关",
        "fontSizeSetting" => "用户字体大小设置（以“我-设置-通用-字体大小”中的设置为准，单位：px）",
        "hostLanguage" => "宿主语言（小程序宿主语言）",
        "hostName" => "小程序宿主名称，如：WeChat、FeiShu",
        "hostVersion" => "宿主版本。如：微信版本号",
        "locationEnabled" => "地理位置的系统开关",
        "microphoneAuthorized" => "允许微信使用麦克风的开关",
        "notificationAuthorized" => "允许微信通知的开关",
        "osName" => "系统名称（ios、android）",
        "osVersion" => "系统版本。如 ios 版本，android 版本",
        "screenHeight" => "屏幕高度",
        "screenWidth" => "屏幕宽度",
        "statusBarHeight" => "手机状态栏的高度",
        "wifiEnabled" => "Wi-Fi 的系统开关",
    ];
    /**
     * 要保存的位置信息字段映射
     * @var array
     */
    public $saveLocationInfoMap = [
        "latitude"=>"纬度，浮点数，范围为-90~90，负数表示南纬",
        "longitude"=>"经度，浮点数，范围为-180~180，负数表示西经",
        "speed"=>"速度，浮点数，单位m/s",
        "accuracy"=>"位置的精确度",
//        "altitude"=>"高度，单位 m",
        "verticalAccuracy"=>"垂直精度，单位 m（Android 无法获取，返回 0）",
        "horizontalAccuracy"=>"水平精度，单位 m",
    ];

    /**
     * 构造方法
     */
    public function __construct()
    {
        $this->config = config("wxApplet");
    }

    /**
     * 授权获取openid
     * @param string $code
     * @return array
     */
    public function codeAuth($code = "")
    {
        $api = "https://api.weixin.qq.com/sns/jscode2session?appid=" . $this->config['appid'] . '&secret=' . $this->config['secret'] . "&js_code=" . $code . "&grant_type=authorization_code";

        $res = file_get_contents($api);
        if ($data = json_decode($res, 1)) {
            return $data;
        } else {
            return [];
        }
    }

    /**
     * 对传入的系统信息数据进行留存过滤
     * @param $info
     * @return array
     */
    public function extractSaveSystemInfo($info)
    {
        return array_intersect_key($info, array_flip(array_keys($this->saveSystemInfoMap)));
    }

    /**
     * 对传入的位置信息数据进行留存过滤
     * @param $info
     * @return array
     */
    public function extractSaveLocationInfo($info)
    {
        return array_intersect_key($info, array_flip(array_keys($this->saveLocationInfoMap)));
    }
}
