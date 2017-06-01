<?php
namespace framework\driver\sms;

use framework\core\http\Client;

class Yuntongxun extends Sms
{
    protected $appkey;
    protected static $host = 'https://app.cloopen.com:8883/2013-12-26/Accounts/';
    
    public function __construct(array $config)
    {
        $this->appkey = $config['appkey'];
        $this->acckey = $config['acckey'];
        $this->seckey = $config['seckey'];
        $this->template = $config['template'];
    }
    
    public function send($to, $template, $data)
    {
        if (isset($this->template[$template])) {
            $date = date('YmdHis');
            $url = self::$host."$this->acckey/SMS/TemplateSMS?sig=".strtoupper(md5($this->acckey.$this->seckey.$date));
            $client = Client::post($url)->json([
                'to'        => $to,
                'templateId'=> $this->template[$template],
                'appId'     => $this->appkey,
                'datas'     => array_values($data)
            ])->headers([
                'Accept:application/json',
                'Authorization:'.base64_encode("$this->acckey:$date")
            ]); 
            $data = $client->json;
            if (isset($data['statusCode']) && $data['statusCode'] === '000000') {
                return true;
            }
            return error(isset($data['statusMsg']) ? $data['statusCode'].': '.$data['statusMsg'] : $client->error);
        }
        return error('Template not exists');
    }
}