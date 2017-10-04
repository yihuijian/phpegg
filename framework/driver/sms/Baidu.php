<?php
namespace framework\driver\sms;

use framework\core\http\Client;

class Baidu extends Sms
{
    protected $host = 'sms.bj.baidubce.com';
    protected $version = 'bce-auth-v1';
    protected $expiration = 180;
    
    public function __construct(array $config)
    {
        $this->acckey = $config['acckey'];
        $this->seckey = $config['seckey'];
        $this->template = $config['template'];
        isset($config['host']) && $this->host = $config['host'];
        isset($config['expiration']) && $this->expiration = $config['expiration'];
    }

    public function send($to, $template, $data)
    {
        if (isset($this->template[$template])) {
            $url = '/bce/v2/message';
            $body = json_encode([
                'invoke'            => uniqid(),
                'phoneNumber'       => $to,
                'TemplateCode'      => $this->template[$template],
                'contentVar'        => $data
            ]);
            $client = Client::post('http://'.self::$host.$url)->headers($this->buildHeaders($url, $body))->body($body);
            $data = $client->json;
            if (isset($data['code']) && $data['code'] === '1000') {
                return true;
            }
            return error($data['message'] ?? $client->error);
        }
        return error('Template not exists');
    }
    
    protected function buildHeaders($url, $body)
    {
        $time = gmdate('Y-m-d\TH:i:s\Z');
        $headers = [
            'Host' => self::$host,
            'Content-Type' => 'application/json',
            'Content-Length' => strlen($body),
            'x-bce-date' => $time,
            'x-bce-content-sha256' => hash('sha256', $body)
        ];
        ksort($headers);
        foreach ($headers as $k => $v) {
            $sendheaders[] = "$k: $v";
            $k = strtolower($k);
            $signheaders[] = $k;
            $canonicalheaders[] = "$k:".rawurlencode(trim($v));
        }
        $signkey = hash_hmac('sha256', "$this->version/$this->acckey/$time/$this->expiration", $this->seckey);
        $signature = hash_hmac('sha256', "POST\n$url\n\n".implode("\n", $canonicalheaders), $signkey);
        $sendheaders[] = "Authorization: $this->version/$this->acckey/$time/$this->expiration/".implode(';', $signheaders)."/$signature";
        return $sendheaders;
    }
}