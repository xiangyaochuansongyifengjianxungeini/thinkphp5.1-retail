<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件

use think\Config;
use think\exception\HttpResponseException;
use think\Request;
use think\Response;

/**
 * 生成邀请码
 */
function createCode($user_id) {

    static $source_string = 'E5FCDG3HQA4B1NOPIJ2RSTUV67MWX89KLYZ';

    $num = $user_id;

    $code = '';

    while ( $num > 0) {

        $mod = $num % 35;

        $num = ($num - $mod) / 35;

        $code = $source_string[$mod].$code;

    }

    if(empty($code[3]))

        $code = str_pad($code,4,'0',STR_PAD_LEFT);

    return $code;

}

/**
 * 解锁邀请码
 */
function decode($code) {

    static $source_string = 'E5FCDG3HQA4B1NOPIJ2RSTUV67MWX89KLYZ';

    if (strrpos($code, '0') !== false)

        $code = substr($code, strrpos($code, '0')+1);

    $len = strlen($code);

    $code = strrev($code);

    $num = 0;

    for ($i=0; $i < $len; $i++) {

        $num += strpos($source_string, $code[$i]) * pow(35, $i);

    }

    return $num;

}

function result($message, $data=[],$status) {

    $data = [
        'code' => $status,
        'msg' => $message,
        'data' => $data,
    ];

    return json($data);
}