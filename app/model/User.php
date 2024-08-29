<?php


namespace app\model;

use think\Model;

class User extends Model
{
    protected static $ownFieldList = [
        "id",
        "uid",
        "last_login_sys_time",
        "user_type",
        "password",
        "user_create_time"
    ];

    public static function buildAddArr($data = [], $pwd = "")
    {
        if (!is_array($data)) {
            return "数据包异常";
        }
        if (empty($pwd)) {
            return "未传入明文密码";
        }
        $return = [];
        $return['password'] = $pwd;
        $fields = self::getTableFields();
        foreach ($fields as $field) {
            //如果是表字段id
            if ($field === "id") {
                $return['id'] = null;
                continue;
            }
            //如果是表字段uid
            if ($field === "uid") {
                //如果传入的数据有字段id
                if (array_key_exists("id",$data)) {
                    $return['uid'] = $data['id'];
                    continue;
                } else {
                    return "数据包中没有[uid]字段";
                }
            }
            //如果是表字段id
            if ($field === "last_login_sys_time") {
                $return['last_login_sys_time'] = date("Y-m-d H:i:s");
                continue;
            }
            //如果是表字段user_type
            if ($field === "user_type") {
                //如果传入的数据有字段userType
                if (array_key_exists("userType",$data)) {
                    $return['user_type'] = $data['userType'];
                    continue;
                } else {
                    return "数据包中没有[user_type]字段";
                }
            }
            //判断这个字段是否在“自己字段列表”
            if (in_array($field,self::$ownFieldList)) {
                continue;
            }
            //判断这个字段是否在数据包里
            if (array_key_exists($field,$data)) {
                $return[$field] = $data[$field];
            }else{
                return "数据包中没有[".$field."]字段";
            }

        }

        return $return;
    }
}
