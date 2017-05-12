<?php
namespace framework\driver\storage;

use framework\core\http\Client;

/*
 * Apache:  https://httpd.apache.org/docs/2.4/mod/mod_dav.html
 * Ngnix:   https://nginx.org/en/docs/http/ngx_http_dav_module.html
 * IIS:     https://www.iis.net/learn/install/installing-publishing-technologies/installing-and-configuring-webdav-on-iis
 * Box:     https://www.box.com/dav
 * OneDrive:https://d.docs.live.net
 * Pcloud:  https://webdav.pcloud.com
 * 坚果云:   https://dav.jianguoyun.com/dav
 * Dropbox:
 * GoogleDrive: 
 */

class Webdav extends Storage
{
    protected $server;
    protected $username;
    protected $password;
    protected $public_read = false;
    protected $auto_create_dir = false;
    
    public function __construct($config)
    {
        $this->server = $config['server'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        isset($config['auto_create_dir']) && $this->auto_create_dir = (bool) $config['auto_create_dir'];
    }
    
    public function get($from, $to = null)
    {
        $client_methods['timeout'] = [30];
        if ($to) {
            $client_methods['save'] = [$to];
        }
        return $this->send('GET', $from, null, $client_methods, !$this->public_read);
    }
    
    public function put($from, $to, $is_buffer = false)
    {
        if ($this->ckdir($to)) {
            $client_methods['timeout'] = [30];
            if ($is_buffer) {
                $client_methods['body'] = [$from];
                return $this->send('PUT', $to, null, $client_methods);
            }
            $fp = fopen($from, 'r');
            if ($fp) {
                $client_methods['stream'] = [$fp];
                $return = $this->send('PUT', $to, null, $client_methods);
                fclose($fp);
                return $return;
            }
        }
        return false;
    }

    public function stat($from)
    {
        $stat = $this->send('HEAD', $from, null, null, !$this->public_read);
        return $stat ? [
            'type'  => $stat['headers']['Content-Type'],
            'size'  => (int) $stat['headers']['Content-Length'],
            'mtime' => strtotime($stat['headers']['Last-Modified']),
        ] : false;
    }
    
    public function copy($from, $to)
    {
        return $this->ckdir($to) ? $this->send('COPY', $from, ['Destination: '.$this->url($to)]) : false;
    }
    
    public function move($from, $to)
    {
        return $this->ckdir($to) ? $this->send('MOVE', $from, ['Destination: '.$this->url($to)]) : false;
    }
    
    public function delete($from)
    {
        return $this->send('DELETE', $from);
    }
    
    protected function send($method, $path, $headers = [], $client_methods = [], $auth = true)
    {
        $client = new Client($method, $this->url($path));
        if ($client_methods) {
            foreach ($client_methods as $client_method => $params) {
                $client->$client_method(... (array) $params);
            }
        }
        if ($auth) {
            $headers[] = $this->auth();
        }
        if ($headers) {
            $client->headers($headers);
        }
        $result = $client->getResult();
        if ($result['status'] >= 200 && $result['status'] < 300) {
            switch ($method) {
                case 'GET':
                    return $result['body'];
                case 'PUT':
                    return true;
                case 'HEAD':
                    return $result['headers'];
                case 'COPY':
                    return true;
                case 'MOVE':
                    return true;
                case 'MKCOL':
                    return true;
                case 'DELETE':
                    return true;
            }
        }
        return $this->setError($result);
    }
    
    protected function url($path)
    {
        return $this->server.trim(trim($path), '/');
    }
    
    protected function auth()
    {
        return 'Authorization: Basic '.base64_encode("$this->username:$this->password");
    }
    
    protected function ckdir($path)
    {
        return $this->auto_create_dir || $this->send('MKCOL', dirname($this->url($path)).'/');
    }
    
    protected function setError($result)
    {
        return false;
    }
}
