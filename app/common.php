<?php
// 应用公共文件

use think\facade\Log;

/**
 * 写入表log日志
 * @param string $type
 * @param array $data
 * @return \think\log\Channel|\think\log\ChannelSet
 */
function CMLog($type="info",$data=[]){
   return Log::channel('CMLog')->write($data,$type);
}

/**
 * 马赛克处理（即用特定字符替换）
 * @param string $str 原内容
 * @param int $startPos 起始位置（不含）
 * @param int $maskLen 处理几位
 * @param string $maskChar 填充字符（默认：*）
 * @return string
 */
function maskString($str, $startPos, $maskLen, $maskChar = '*') {
    $strLen = mb_strlen($str); // 获取字符串的长度

    // 如果起始位置超出字符串长度，返回原字符串
    if ($startPos >= $strLen) {
        return $str;
    }

    // 计算实际要打马赛克的长度
    $actualMaskLen = min($maskLen, $strLen - $startPos);

    // 生成马赛克字符串
    $mask = str_repeat($maskChar, $actualMaskLen);

    // 拼接结果字符串
    $maskedStr =
        mb_substr($str, 0, $startPos) . // 前部分
        $mask . // 马赛克部分
        mb_substr($str, $startPos + $actualMaskLen); // 剩余部分

    return $maskedStr;
}
