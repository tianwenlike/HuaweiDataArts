# HuaweiDataArts

# 用于华为SDK php DataArts 封装

# 继承方法
```
use App\Libraries\huaweisdk\Huawei;
```

# Huawei.php 填写key secret 在控制中心获取
```
private static $hw_key    = ""; // key 
private static $hw_secret = ""; // secret
private static $hw_url    = ""; // 地址
```

# 调用
```
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
        $data = Huawei::getData($data);
        var_dump($data);die(); 
    }

}
```


