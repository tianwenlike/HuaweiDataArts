<?php
namespace App\Libraries\huaweisdk;

// require 'Signer.php';
include_once 'Signer.php';
use Signer;
use Request;
// use App\Libraries\huaweisdk\Signer;



class Huawei
{
    private static $hw_key    = "";
    private static $hw_secret = "";
    private static $hw_url    = "";
    
    // 获取华为连接DataArts示例
    // $data = [
    //     "url"   => "/test",   // 请求地址
    //     "query" => [     // 请求query参数
    //         "type"          => 2,
    //         "busi_hall_id"  => 1,
    //     ],
    //     "body"  => []   // 请求body 参数
    // ];
    // $data = Huawei::getData($data);

    public function getData($param=[],$method="POST"){
        $signer = new Signer();
        $signer->Key    = self::$hw_key;
        $signer->Secret = self::$hw_secret;

        if(!isset($param['url']) || empty($param['url'])){
            // return api_error("请求填写华为地址！");
            echo "请求填写华为地址！";
            exit;
        }

        $req = new Request($method,self::$hw_url.$param['url']);
        $req->headers = array(
            'content-type'  => 'application/json',
            'x-stage'       => 'RELEASE',
        );

        // 请求参数 query
        if(!empty($param['query'])){
            $req->query = $param['query'];
        }

        // 请求参数 body
        if(!empty($param['data']) || !isset($param['data'])){
            $req->body = json_encode($param['data'],JSON_UNESCAPED_UNICODE);
        }

        // 加密
        $curl = $signer->Sign($req);
        $req->headers['x-Authorization'] = $req->headers['Authorization'];
        $header = [];
        foreach ($req->headers as $key => $value) {
            array_push($header, strtolower($key) . ':' . trim($value));
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        // var_dump($header);
        // echo "--------------\n";
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($status == 0) {
            // echo curl_error($curl);
            // 打印报错
            $error = "请求网关错误".curl_error($curl);
            // return api_error($error,9000);
            echo $error;
            exit;
        } else {
            $resData = json_decode($response, true);
            if($resData['errCode']=="DLM.0"){
                // 成功
                // return $response;
                return json_decode($response, true);
                // return json_encode(json_decode($response, true), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
            }else{
                // 失败 请求接口错误
                $error = "".$response;
                // return api_error($error,9000);
                echo $error;
                exit;
            }
        }
        curl_close($curl);
    }



}



?>