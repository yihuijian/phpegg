<?php
namespace framework\driver\rpc;

use framework\core\Loader;

/* 
 * composer require apache/thrift
 * https://github.com/apache/thrift
 */
use Thrift\Transport\TSocket;
use Thrift\Transport\TBufferedTransport;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Protocol\TMultiplexedProtocol;

class Thrift
{
    protected $prefix;
    protected $services;
    protected $protocol;
    protected $transport;
    protected $tmultiplexed;
    protected $auto_bind_params;
    protected $service_method_params;
    
    public function __construct($config)
    {
        $socket = new TSocket($config['host'], $config['port']);
        if (isset($config['send_timeout'])) {
            $socket->setRecvTimeout($config['send_timeout']);
        }
        if (isset($config['recv_timeout'])) {
            $socket->setRecvTimeout($config['recv_timeout']);
        }
        $this->transport = new TBufferedTransport($socket, 1024, 1024);
        $this->protocol  = new TBinaryProtocol($this->transport);
        $this->transport->open();
        foreach ($config['service_schemes'] as $type => $rules) {
            Loader::add($type, $rules);
        }
        $this->prefix = $config['prefix'] ?? null;
        $this->tmultiplexed = $config['tmultiplexed'] ?? false;
        $this->auto_bind_param = $config['auto_bind_param'] ?? false;
    }

    public function __get($name)
    {
        return $this->query($name);
    }

    public function __call($method, $params)
    {
        return $this->query()->$method(...$params);
    }
    
    public function query($name = null)
    {
        return new query\Thrift($this, $name);
    }
    
    public function call($ns, $method, $params)
    {
        if (isset($this->prefix)) {
            array_unshift($ns, $this->prefix);
        }
        if (!$ns) {
            throw new \Exception('service is empty');
        }
        $class = implode('\\', $ns);
        if (!isset($this->services[$class])) {
            if ($this->tmultiplexed) {
                $name = substr(strrchr($class, '\\'), 1);
                $this->services[$class] = new $class(new TMultiplexedProtocol($this->protocol, $name));
            } else {
                $this->services[$class] = new $class($this->protocol);
            }
        }
        if ($this->auto_bind_params) {
            $this->bindParams($class, $method, $params);
        }
        return $this->services[$class]->$method(...$params);
    }
    
    protected function bindParams($class, $method, &$params)
    {
        if (isset($this->service_method_params[$class][$method])) {
            if (empty($this->service_method_params[$class][$method])) {
                return;
            }
            foreach ($this->service_method_params[$class][$method] as $i => $name) {
               $params[$i] = new $name($params[$i]);
            }
        } else {
            $this->service_method_params[$class][$method] = [];
            foreach ((new \ReflectionMethod($class, $method))->getParameters() as $i => $parameter) {
                if ((string) $parameter->getType() === 'object') {
                    $name = $parameter->getName();
                    $params[$i] = new $name($params[$i]);
                    $this->service_method_params[$class][$method][$i] = $name;
                }
            }
        }
    }
    
    public function __destruct()
    {
        $this->transport && $this->transport->close();
    }
}
