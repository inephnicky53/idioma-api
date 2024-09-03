<?php


namespace App\Service;


use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\Notifier\TexterInterface;

class SmsService
{
    public function __construct(
        //private readonly TexterInterface $texter
    )
    {
    }

    const SMS_FROM = "IDIOMA";

    public function sendBulk($telephone, $Message){
        $telephone=str_replace('+','',$telephone);
        //$apiKey = "Basic RTJEREZCN0QxQzE0NEQ0M0FFM0MxMTgzQTdBNjIzMkMtMDItQzpkaFQzSlV5ZUhQQklfS0draTNVRVMxYl9WRF9NYw==";
        $token_id = "7C36D1973D7F4BC2907FDE2C26415650-02-4";
        $token_key = "S_wU10!k0QmzcuraZwwn9F*FHmaXe";
        $apiKey = "Basic ". base64_encode("{$token_id}:{$token_key}");
        $headers = [
            'Authorization'=> $apiKey,
            'Content-Type' => "application/json"
        ];
        $param = http_build_query(
            [
                "from" => self::SMS_FROM,
                'to' => $telephone,
                'body' => $Message,
                "encoding" => "UNICODE",
                "auto-unicode" => true,
            ]
        );
        $client = new Client(
            ['headers'=>$headers]
        );
        //dd($client,$param,$apiKey,$Message);
        $request = new Request('GET', "https://api.bulksms.com/v1/messages/send?$param");

        $promise = $client->send($request);

        /*$r = 23;
        $promise->then(
            function (ResponseInterface $res) use (&$r){
            //function ($res) {
                $r = 20;
            },
            function (RequestException $e) use (&$r){
                $r = 22;
            }
        );*/
        //dd($promise->getStatusCode());
        return $promise->getStatusCode();
    }

    public function sendBc($phone, $message){
        $username = "mmuseghe@gmail.com";
        $apiKey = "59fdd5910bde5a08db4b767c5a3f3c03462eee89";


        $body = http_build_query(
            [
                "username" => $username,
                "app_key" => $apiKey,
                "from" => self::SMS_FROM,
                "tel" => trim($phone),
                'message' => $message,
            ]
        );

        $ch = curl_init("https://www.unikron.tech/api/send.jsp?$body");

        $options = array(
            //CURLOPT_URL => $this->URL,              #set URL address
            //CURLOPT_USERAGENT => $this->UserAgent,  #set UserAgent to get right content like a browser
            CURLOPT_RETURNTRANSFER => true,         #redirection result from output to string as curl_exec() result
            //CURLOPT_COOKIEFILE => 'cookies.txt',    #set cookie to skip site ads
            //CURLOPT_COOKIEJAR => 'cookiesjar.txt',  #set cookie to skip site ads
            CURLOPT_FOLLOWLOCATION => true,         #follow by header location
            CURLOPT_HEADER => true,                 #get header (not head) of site
            CURLOPT_FORBID_REUSE => true,           #close connection, connection is not pooled to reuse
            CURLOPT_FRESH_CONNECT => true,          #force the use of a new connection instead of a cached one
            CURLOPT_SSL_VERIFYPEER => false         #can get protected content SSL
        );
        //set array options to object $curl
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        curl_close($ch);

        //dd($response, $phone);
        //return $response;
    }

    public function send($phone, $message)
    {

    }

}
