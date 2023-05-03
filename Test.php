<?php

namespace App\Controllers\Api\BigScreen;

use App\Controllers\BaseController;
use App\Libraries\huaweisdk\Huawei;

class Test extends BaseController
{
    // 华为连接DataArts测试
    public function index(){
        // $data = [
        //     "url"   => "/test",   // 请求地址
        //     "query" => [     // 请求query参数
        //         "data_time"     => "2023-01-01",
        //         "type"          => 1,
        //     ],
        //     "body"  => [ // 请求body 参数
        //         "data_time"  => "2023",
        //         "type"  =>1
        //     ]   
        // ];
        $data = [
            "url"   => "/test",   // 请求地址
            "body"  => [     // 请求body 参数
                "year"  => "2023",
            ]
        ];
        $data = Huawei::getData($data);
        var_dump($data);die(); 
    }

}