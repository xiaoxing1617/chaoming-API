<?php


namespace app\common;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtAuth
{

    public static function getConfig(){
        return config("jwt");
    }

    /**
     * 生成 JWT Token
     * @param mixed $data 数据
     * @param false|int $expire 过期时间（秒），如果是false则使用系统默认
     * @return string
     */
    public static function createToken($data,$expire=false)
    {
        $issuedAt = time();
        $config = self::getConfig();
        if(!$expire){
            $expire = $config['expire'];  //使用系统默认
        }

        $payload = [
            'iat' => $issuedAt,  // 签发时间
            'iss' => $config['issuer'],  // 签发者
            'aud' => $config['audience'],  // 受众
            'exp' => $issuedAt + $expire,  // 过期时间
            'data' => $data  // 自定义数据部分
        ];

        return JWT::encode($payload, $config['key'], 'HS256');
    }

    /**
     * 验证 JWT Token
     * @param string $token 密文
     * @return null|mixed
     */
    public static function verifyToken($token)
    {
        $config = self::getConfig();
        try {
            $decoded = JWT::decode($token, new Key($config['key'], 'HS256'));
            return $decoded->data;
        } catch (\Exception $e) {
            return null;
        }
    }
}
