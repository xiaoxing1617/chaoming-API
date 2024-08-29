<?php
return [
    //密钥
    "key"=>env('jwt.key', '2af1a55b508baae5f8917cc2b35ce73c'),

    //签发者
    "issuer"=>"admin",

    //受众
    "audience"=>"user",

    //过期时间（单位：秒）
    "expire"=>3600 * 24 * 3,  //3天
];
